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

namespace local_nit_assistant\task;

use local_nit_assistant\runtime\rate_limit;

/**
 * Daily: drop expired rate-limit windows and old idempotency records.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \core\task\scheduled_task {
    /** Idempotency records are kept this long (seconds). */
    public const IDEM_TTL = 7 * DAYSECS;

    /**
     * {@inheritDoc}
     */
    public function get_name(): string {
        return get_string('task_cleanup', 'local_nit_assistant');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): void {
        global $DB;
        $rates = rate_limit::cleanup();
        $DB->delete_records_select('local_nit_asst_idem', 'timecreated < ?', [time() - self::IDEM_TTL]);
        mtrace("local_nit_assistant: removed {$rates} rate-limit windows");
    }
}
