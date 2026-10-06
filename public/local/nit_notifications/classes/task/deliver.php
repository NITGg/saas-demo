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

use local_nit_notifications\sender;

/**
 * Delivers a big notification in batches: one batch per run, then queues
 * itself again until every recipient was tried.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deliver extends \core\task\adhoc_task {

    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_deliver', 'local_nit_notifications');
    }

    /**
     * Run one batch.
     */
    public function execute() {
        $notifid = (int) ($this->get_custom_data()->notifid ?? 0);
        if (!$notifid) {
            return;
        }
        $left = sender::deliver($notifid, sender::BATCH);
        mtrace("local_nit_notifications: notification $notifid, $left recipient(s) left");
        if ($left > 0) {
            $next = new self();
            $next->set_custom_data(['notifid' => $notifid]);
            \core\task\manager::queue_adhoc_task($next);
        }
    }
}
