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

use local_nit_assistant\followup\manager;
use local_nit_assistant\inbox\alerts;

/**
 * Daily: draft follow-ups (when switched on) and tell staff what is waiting —
 * follow-up drafts to approve, and leads nobody has contacted for two days.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class followups extends \core\task\scheduled_task {
    /**
     * {@inheritDoc}
     */
    public function get_name(): string {
        return get_string('task_followups', 'local_nit_assistant');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): void {
        global $DB;
        $now = time();
        if (manager::enabled()) {
            $made = manager::create_drafts($now);
            mtrace("local_nit_assistant: {$made} follow-up drafts");
            alerts::digest('followups', manager::waiting(), '/local/nit_assistant/followups.php');
        }
        $leads = $DB->count_records_select('local_nit_asst_lead', "status = 'new' AND timecreated < ?", [$now - 2 * DAYSECS]);
        alerts::digest('leadswaiting', $leads, '/local/nit_assistant/leads.php');
    }
}
