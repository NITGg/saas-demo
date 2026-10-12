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

/**
 * Fixed-window counters in the database, so every web node agrees. One row per
 * (key, window); the increment is a single SQL statement, so concurrent hits are
 * all counted. Expired rows are removed by the daily cleanup.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rate_limit {
    /** Counters table. */
    private const TABLE = 'local_nit_asst_rate';

    /**
     * Count one hit; allowed is false once the window holds more than $limit hits.
     *
     * @param string $key e.g. "ip:msg:<hash>"
     * @param int $limit
     * @param int $windowseconds
     * @param int|null $now
     * @return array ['allowed' => bool, 'count' => int]
     */
    public static function hit(string $key, int $limit, int $windowseconds, ?int $now = null): array {
        global $DB;
        $now ??= time();
        $key = substr($key, 0, 191);
        $start = intdiv($now, $windowseconds) * $windowseconds;
        $where = ['ratekey' => $key, 'windowstart' => $start];
        if (!$DB->record_exists(self::TABLE, $where)) {
            try {
                $DB->insert_record(self::TABLE, (object) ($where + ['hits' => 0, 'expires' => $start + $windowseconds]));
            } catch (\dml_write_exception $e) {
                // Created at the same moment by another request (unique key) — just count.
                unset($e);
            }
        }
        $DB->execute('UPDATE {' . self::TABLE . '} SET hits = hits + 1 WHERE ratekey = ? AND windowstart = ?', [$key, $start]);
        $count = (int) $DB->get_field(self::TABLE, 'hits', $where);
        return ['allowed' => $count <= $limit, 'count' => $count];
    }

    /**
     * Delete expired windows.
     *
     * @param int|null $now
     * @return int rows deleted (approximate)
     */
    public static function cleanup(?int $now = null): int {
        global $DB;
        $now ??= time();
        $n = $DB->count_records_select(self::TABLE, 'expires < ?', [$now]);
        $DB->delete_records_select(self::TABLE, 'expires < ?', [$now]);
        return $n;
    }
}
