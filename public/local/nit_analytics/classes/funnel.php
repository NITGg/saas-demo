<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_nit_analytics;

/**
 * The acquisition funnel per campaign source / medium / campaign: landings → sign-ups → buyers →
 * purchases and revenue. Landings are counted without cookies (one per visit that arrives with UTM
 * parameters); sign-ups and buyers use the attribution stored on the account at sign-up.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class funnel {
    /** @var string Table of landings. */
    const LANDING_TABLE = 'local_nit_analytics_landing';

    /**
     * Log one landing (a visit that arrived with UTM parameters).
     *
     * @param array $utm cleaned UTM set
     * @param int $time
     * @return void
     */
    public static function log_landing(array $utm, int $time): void {
        global $DB;
        try {
            $DB->insert_record(self::LANDING_TABLE, (object) [
                'source' => $utm['utm_source'] ?? '',
                'medium' => $utm['utm_medium'] ?? '',
                'campaign' => $utm['utm_campaign'] ?? '',
                'timecreated' => $time,
            ]);
        } catch (\Throwable $e) {
            debugging('local_nit_analytics: could not log landing: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * SQL joining a user's attribution preferences (alias ps / pm / pc on u.id).
     *
     * @return array [joins sql, params]
     */
    private static function attribution_joins(): array {
        return ["LEFT JOIN {user_preferences} ps ON ps.userid = u.id AND ps.name = :ps
                 LEFT JOIN {user_preferences} pm ON pm.userid = u.id AND pm.name = :pm
                 LEFT JOIN {user_preferences} pc ON pc.userid = u.id AND pc.name = :pc", [
            'ps' => tracker::PREF_PREFIX . 'utm_source',
            'pm' => tracker::PREF_PREFIX . 'utm_medium',
            'pc' => tracker::PREF_PREFIX . 'utm_campaign',
        ]];
    }

    /**
     * Funnel rows since a time, biggest first by sign-ups then landings. Unattributed traffic has
     * empty source/medium/campaign (direct, or accounts created before tracking / by an admin).
     *
     * @param int $since unix time
     * @return array[] each {source, medium, campaign, landings, signups, buyers, purchases,
     *                 revenue: array<currency,int minor>}
     */
    public static function rows(int $since): array {
        global $DB, $CFG;
        $rows = [];
        $row = function (string $s, string $m, string $c) use (&$rows): string {
            $key = $s . "\x1f" . $m . "\x1f" . $c;
            if (!isset($rows[$key])) {
                $rows[$key] = ['source' => $s, 'medium' => $m, 'campaign' => $c, 'landings' => 0, 'signups' => 0,
                    'buyers' => 0, 'purchases' => 0, 'revenue' => []];
            }
            return $key;
        };

        $landings = $DB->get_recordset_sql(
            "SELECT source, medium, campaign, COUNT(1) AS n
               FROM {" . self::LANDING_TABLE . "}
              WHERE timecreated >= :since
           GROUP BY source, medium, campaign", ['since' => $since]);
        foreach ($landings as $r) {
            $rows[$row((string) $r->source, (string) $r->medium, (string) $r->campaign)]['landings'] = (int) $r->n;
        }
        $landings->close();

        [$joins, $params] = self::attribution_joins();
        $signups = $DB->get_recordset_sql(
            "SELECT COALESCE(ps.value, '') AS source, COALESCE(pm.value, '') AS medium,
                    COALESCE(pc.value, '') AS campaign, COUNT(u.id) AS n
               FROM {user} u
                    $joins
              WHERE u.deleted = 0 AND u.id <> :guest AND u.timecreated >= :since
           GROUP BY COALESCE(ps.value, ''), COALESCE(pm.value, ''), COALESCE(pc.value, '')",
            $params + ['guest' => (int) $CFG->siteguest, 'since' => $since]);
        foreach ($signups as $r) {
            $rows[$row((string) $r->source, (string) $r->medium, (string) $r->campaign)]['signups'] = (int) $r->n;
        }
        $signups->close();

        $buys = $DB->get_recordset_sql(
            "SELECT COALESCE(ps.value, '') AS source, COALESCE(pm.value, '') AS medium,
                    COALESCE(pc.value, '') AS campaign, c.currency, COUNT(DISTINCT c.userid) AS buyers,
                    COUNT(c.id) AS purchases, SUM(c.amount_minor) AS revenue
               FROM {" . conversions::TABLE . "} c
               JOIN {user} u ON u.id = c.userid
                    $joins
              WHERE c.timecreated >= :since
           GROUP BY COALESCE(ps.value, ''), COALESCE(pm.value, ''), COALESCE(pc.value, ''), c.currency",
            $params + ['since' => $since]);
        $buyers = [];
        foreach ($buys as $r) {
            $key = $row((string) $r->source, (string) $r->medium, (string) $r->campaign);
            $rows[$key]['purchases'] += (int) $r->purchases;
            $rows[$key]['revenue'][(string) $r->currency] = (int) $r->revenue;
            $buyers[$key] = ($buyers[$key] ?? 0) + (int) $r->buyers;
        }
        $buys->close();
        // A buyer paying in two currencies would be counted twice above; count distinct buyers per row.
        foreach (array_keys($buyers) as $key) {
            $rows[$key]['buyers'] = self::distinct_buyers($rows[$key], $since);
        }

        $rows = array_values($rows);
        usort($rows, fn($a, $b) => [$b['signups'], $b['landings'], $a['source']]
            <=> [$a['signups'], $a['landings'], $b['source']]);
        return $rows;
    }

    /**
     * Distinct buyers of one funnel row since a time.
     *
     * @param array $row
     * @param int $since
     * @return int
     */
    private static function distinct_buyers(array $row, int $since): int {
        global $DB;
        [$joins, $params] = self::attribution_joins();
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.userid)
               FROM {" . conversions::TABLE . "} c
               JOIN {user} u ON u.id = c.userid
                    $joins
              WHERE c.timecreated >= :since AND COALESCE(ps.value, '') = :s
                    AND COALESCE(pm.value, '') = :m AND COALESCE(pc.value, '') = :c",
            $params + ['since' => $since, 's' => $row['source'], 'm' => $row['medium'], 'c' => $row['campaign']]);
    }

    /**
     * Revenue of a row as text, e.g. "1,250.00 EGP; 300.00 SAR".
     *
     * @param array<string,int> $revenue currency => minor units
     * @return string
     */
    public static function format_revenue(array $revenue): string {
        ksort($revenue);
        $parts = [];
        foreach ($revenue as $currency => $minor) {
            $parts[] = number_format($minor / 100, 2) . ' ' . $currency;
        }
        return implode('; ', $parts);
    }

    /**
     * A conversion rate as a percentage string, or '' when the base is zero.
     *
     * @param int $part
     * @param int $whole
     * @return string
     */
    public static function rate(int $part, int $whole): string {
        return $whole > 0 ? round($part * 100 / $whole, 1) . '%' : '';
    }

    /**
     * The rows as flat records for the table and the CSV export.
     *
     * @param array[] $rows from rows()
     * @param string $none label for unattributed traffic
     * @return array[]
     */
    public static function export_rows(array $rows, string $none): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'source' => $r['source'] !== '' ? $r['source'] : $none,
                'medium' => $r['medium'],
                'campaign' => $r['campaign'],
                'landings' => $r['landings'],
                'signups' => $r['signups'],
                'signuprate' => self::rate($r['signups'], $r['landings']),
                'buyers' => $r['buyers'],
                'buyerrate' => self::rate($r['buyers'], $r['signups']),
                'purchases' => $r['purchases'],
                'revenue' => self::format_revenue($r['revenue']),
            ];
        }
        return $out;
    }
}
