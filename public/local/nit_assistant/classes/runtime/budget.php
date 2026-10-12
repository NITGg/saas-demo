<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\runtime;

use local_nit_assistant\llm\usage;

/**
 * Daily cost budget. Before each model call reserve() adds an upper-bound
 * estimate only if committed + estimate stays within the budget; settle() swaps
 * it for the real cost. Both run under a lock, so parallel turns can never
 * overspend by more than one call's estimate.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class budget {
    /** Daily usage table. */
    private const TABLE = 'local_nit_asst_usage';

    /**
     * Today in the site's timezone (YYYY-MM-DD).
     *
     * @param int|null $now
     * @return string
     */
    public static function day(?int $now = null): string {
        $d = new \DateTime('@' . ($now ?? time()));
        $d->setTimezone(\core_date::get_server_timezone_object());
        return $d->format('Y-m-d');
    }

    /**
     * Reserve $estimate USD; null when it would exceed $limit (or the limit is 0).
     *
     * @param float $estimate
     * @param float $limit daily budget in USD
     * @param int|null $now
     * @return array|null ['day' => string, 'estimate' => float]
     */
    public static function reserve(float $estimate, float $limit, ?int $now = null): ?array {
        global $DB;
        if ($limit <= 0) {
            return null;
        }
        $day = self::day($now);
        return self::locked($day, static function () use ($DB, $day, $estimate, $limit): ?array {
            $row = self::row($day);
            if ((float) $row->committed + $estimate > $limit) {
                return null;
            }
            $DB->execute(
                'UPDATE {' . self::TABLE . '}
                    SET reserved = reserved + ?, committed = committed + ?, timemodified = ?
                  WHERE id = ?',
                [$estimate, $estimate, time(), $row->id]
            );
            return ['day' => $day, 'estimate' => $estimate];
        });
    }

    /**
     * Replace a reservation with the actual cost (0 = the call failed before any usage)
     * and log the call (cost per model / user / conversation).
     *
     * @param array $reservation from reserve()
     * @param float $actual
     * @param usage|null $usage
     * @param array|null $meta ['kind' => chat|brief|tags|followup, 'model' => string, 'convid' => ?int, 'userid' => int]
     * @return void
     */
    public static function settle(array $reservation, float $actual, ?usage $usage = null, ?array $meta = null): void {
        global $DB;
        $day = $reservation['day'];
        self::locked($day, static function () use ($DB, $day, $reservation, $actual, $usage): void {
            $row = self::row($day);
            $DB->execute('UPDATE {' . self::TABLE . '}
                             SET reserved = reserved - ?, spent = spent + ?, committed = committed + ?,
                                 tokensin = tokensin + ?, tokensout = tokensout + ?, cacheread = cacheread + ?,
                                 cachewrite = cachewrite + ?, timemodified = ?
                           WHERE id = ?', [
                $reservation['estimate'], $actual, $actual - $reservation['estimate'],
                $usage?->input ?? 0, $usage?->output ?? 0, $usage?->cacheread ?? 0, $usage?->cachewrite ?? 0,
                time(), $row->id,
            ]);
        });
        if ($usage && $meta) {
            $DB->insert_record('local_nit_asst_event', (object) [
                'day' => $day, 'kind' => $meta['kind'], 'model' => substr((string) $meta['model'], 0, 100),
                'convid' => $meta['convid'] ?? null, 'userid' => (int) ($meta['userid'] ?? 0),
                'inputtokens' => $usage->input, 'outputtokens' => $usage->output,
                'cacheread' => $usage->cacheread, 'cachewrite' => $usage->cachewrite,
                'cost' => $actual, 'timecreated' => time(),
            ]);
        }
    }

    /**
     * Cheap pre-check before a turn (reserve() is the real gate).
     *
     * @param float $limit
     * @param int|null $now
     * @return bool
     */
    public static function left(float $limit, ?int $now = null): bool {
        global $DB;
        if ($limit <= 0) {
            return false;
        }
        $committed = $DB->get_field(self::TABLE, 'committed', ['day' => self::day($now)]);
        return $committed === false || (float) $committed < $limit;
    }

    /**
     * True exactly once per day — the caller sends the single "budget reached" alert.
     *
     * @param int|null $now
     * @return bool
     */
    public static function claim_alert(?int $now = null): bool {
        global $DB;
        $day = self::day($now);
        return self::locked($day, static function () use ($DB, $day): bool {
            $row = self::row($day);
            if ((int) $row->alerted) {
                return false;
            }
            $DB->set_field(self::TABLE, 'alerted', 1, ['id' => $row->id]);
            return true;
        });
    }

    /**
     * The day's row, created on first use.
     *
     * @param string $day
     * @return \stdClass
     */
    private static function row(string $day): \stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['day' => $day]);
        if (!$row) {
            $id = $DB->insert_record(self::TABLE, (object) ['day' => $day, 'timemodified' => time()]);
            $row = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        }
        return $row;
    }

    /**
     * Run $fn while holding the day's budget lock.
     *
     * @param string $day
     * @param callable $fn
     * @return mixed
     */
    private static function locked(string $day, callable $fn): mixed {
        $factory = \core\lock\lock_config::get_lock_factory('local_nit_assistant');
        $lock = $factory->get_lock('budget_' . $day, 10);
        if (!$lock) {
            throw new \moodle_exception('error_busy', 'local_nit_assistant');
        }
        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }
}
