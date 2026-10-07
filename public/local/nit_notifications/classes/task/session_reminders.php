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

namespace local_nit_notifications\task;

use local_nit_notifications\auto;

/**
 * Sends the reminders before live sessions and private lessons start.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_reminders extends \core\task\scheduled_task {

    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sessionreminders', 'local_nit_notifications');
    }

    /**
     * Send what is due.
     */
    public function execute() {
        $count = auto::send_session_reminders(time());
        mtrace("local_nit_notifications: $count session/lesson reminder(s) sent");
    }
}
