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

namespace local_nit_reports\report;

use local_nit_reports\data;
use local_nit_reports\scope;

/**
 * Teacher dues (local_nit_finance): for each teacher, what they earned in the
 * period (activity sales and private lessons, after the platform's share), what
 * was taken back, what was paid out to them, what they asked for and is not paid
 * yet, and their wallet balance now. A teacher sees only their own line.
 *
 * Earnings follow local_nit_finance as it is today: online course and subscription
 * sales are not yet shared with teachers (see the task plan, finance tasks).
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_dues extends base {

    public static function key(): string {
        return 'teacher_dues';
    }

    public static function level(): string {
        return scope::TEACHING;
    }

    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_earning');
    }

    public function filters(): array {
        return $this->s->teacher_only() ? ['period'] : ['period', 'q'];
    }

    public function columns(): array {
        return [
            'teacher' => self::str('col_teacher'),
            'operations' => self::str('col_operations'),
            'percent' => self::str('col_teacherpercent'),
            'activities' => self::str('col_earnedactivities'),
            'lessons' => self::str('col_earnedlessons'),
            'earned' => self::str('col_earned'),
            'platform' => self::str('col_platformshare'),
            'reversed' => self::str('col_reversed'),
            'paid' => self::str('col_paidout'),
            'lastpaid' => self::str('col_lastpaid'),
            'requested' => self::str('col_requested'),
            'balance' => self::str('col_balance'),
        ];
    }

    public function sortable(): array {
        return array_fill_keys(array_keys($this->columns()), true);
    }

    /**
     * The teachers listed.
     *
     * @return int[]
     */
    private function teacher_ids(): array {
        global $DB, $CFG;
        if ($this->s->teacher_only()) {
            return [$this->s->userid];
        }
        if ($this->s->sitewide) {
            $ids = array_merge(
                $DB->get_fieldset_sql('SELECT DISTINCT teacherid FROM {nit_earning}'),
                $DB->get_fieldset_sql("SELECT userid FROM {nit_wallet} WHERE ownertype = 'teacher'")
            );
        } else {
            [$cwhere, $cparams] = $this->s->course_sql('c.id', scope::MANAGE);
            [$members, $params] = data::members_sql(data::teacher_roles(), $cwhere, $cparams);
            $ids = $DB->get_fieldset_sql("SELECT DISTINCT m.userid FROM ($members) m", $params);
        }
        $admins = array_map('intval', explode(',', $CFG->siteadmins));
        $ids = array_values(array_diff(array_unique(array_map('intval', $ids)), $admins));
        if ($ids && $this->f->q !== '') {
            [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tq');
            [$search, $sp] = data::user_search_sql('u', $this->f->q);
            $ids = array_map('intval', $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE u.id $in AND $search", $params + $sp));
        }
        return $ids;
    }

    /**
     * Each teacher's figures.
     *
     * @param int[] $ids
     * @return array<int,array>
     */
    private function figures(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'td');
        [$period, $pp] = $this->f->period_sql('timecreated');
        $earned = [];
        foreach ($DB->get_recordset_sql("SELECT teacherid, source, SUM(teacher_amount_minor) AS total FROM {nit_earning}
                WHERE teacherid $in AND $period GROUP BY teacherid, source", $params + $pp) as $r) {
            $earned[(int) $r->teacherid][$r->source] = (int) $r->total;
        }
        // Count, share range and the platform's part of the same earnings (taken-back ones included, like "earned").
        [$speriod, $sp] = $this->f->period_sql('timecreated', 's');
        $stats = $DB->get_records_sql("SELECT teacherid, COUNT(1) AS n, MIN(teacher_percent) AS minpct,
                    MAX(teacher_percent) AS maxpct, SUM(platform_amount_minor) AS platform
               FROM {nit_earning} WHERE teacherid $in AND $speriod GROUP BY teacherid", $params + $sp);
        $lastpaid = $DB->get_records_sql_menu("SELECT teacherid, MAX(timeprocessed) FROM {nit_withdrawal}
            WHERE teacherid $in AND status = 'paid' GROUP BY teacherid", $params);
        [$rperiod, $rp] = $this->f->period_sql('timereversed', 'r');
        $reversed = $DB->get_records_sql_menu("SELECT teacherid, SUM(teacher_amount_minor) FROM {nit_earning}
            WHERE teacherid $in AND status = 'reversed' AND $rperiod GROUP BY teacherid", $params + $rp);
        [$wperiod, $wp] = $this->f->period_sql('timeprocessed', 'w');
        $paid = $DB->get_records_sql_menu("SELECT teacherid, SUM(amount_minor) FROM {nit_withdrawal}
            WHERE teacherid $in AND status = 'paid' AND $wperiod GROUP BY teacherid", $params + $wp);
        $requested = $DB->get_records_sql_menu("SELECT teacherid, SUM(amount_minor) FROM {nit_withdrawal}
            WHERE teacherid $in AND status IN ('pending', 'approved') GROUP BY teacherid", $params);
        $balance = $DB->get_records_sql_menu("SELECT userid, SUM(balance_minor) FROM {nit_wallet}
            WHERE userid $in AND ownertype = 'teacher' GROUP BY userid", $params);
        $out = [];
        foreach ($ids as $id) {
            $e = $earned[$id] ?? [];
            $st = $stats[$id] ?? null;
            $out[$id] = [
                'operations' => (int) ($st->n ?? 0),
                'minpct' => $st ? (float) $st->minpct : null,
                'maxpct' => $st ? (float) $st->maxpct : null,
                'activities' => (int) ($e['cm'] ?? 0),
                'lessons' => (int) ($e['lesson'] ?? 0),
                'platform' => (int) ($st->platform ?? 0),
                'reversed' => (int) ($reversed[$id] ?? 0),
                'paid' => (int) ($paid[$id] ?? 0),
                'lastpaid' => (int) ($lastpaid[$id] ?? 0),
                'requested' => (int) ($requested[$id] ?? 0),
                'balance' => (int) ($balance[$id] ?? 0),
            ];
            $out[$id]['earned'] = $out[$id]['activities'] + $out[$id]['lessons'];
        }
        return $out;
    }

    public function summary(): array {
        $fig = $this->figures($this->teacher_ids());
        $sum = fn($k) => array_sum(array_column($fig, $k));
        return [
            ['id' => 'col_earned', 'label' => self::str('col_earned'), 'value' => data::minor($sum('earned'))],
            ['id' => 'col_paidout', 'label' => self::str('col_paidout'), 'value' => data::minor($sum('paid'))],
            ['id' => 'col_requested', 'label' => self::str('col_requested'), 'value' => data::minor($sum('requested'))],
            ['id' => 'col_balance', 'label' => self::str('col_balance'), 'value' => data::minor($sum('balance'))],
        ];
    }

    public function rows(int $page, int $perpage): array {
        $ids = $this->teacher_ids();
        $fig = $this->figures($ids);
        $names = data::user_names($ids);
        uasort($fig, fn($a, $b) => $b['earned'] <=> $a['earned']);
        $rows = [];
        $money = ['activities', 'earned', 'lessons', 'platform', 'reversed', 'paid', 'requested', 'balance'];
        foreach ($fig as $id => $f) {
            $row = ['teacher' => $names[$id] ?? '#' . $id, 'operations' => self::num($f['operations']),
                'percent' => $f['minpct'] === null ? '—' : ($f['minpct'] == $f['maxpct'] ? self::pct($f['minpct'])
                    : self::pct($f['minpct']) . ' – ' . self::pct($f['maxpct'])),
                'lastpaid' => self::date($f['lastpaid'])];
            foreach ($money as $k) {
                $row[$k] = data::minor($f[$k]);
            }
            $row['_sort'] = array_intersect_key($f, array_flip(array_merge($money, ['operations', 'lastpaid'])))
                + ['percent' => $f['maxpct'] ?? -1.0];
            $rows[] = $row;
        }
        return $this->finish($rows, $page, $perpage);
    }
}
