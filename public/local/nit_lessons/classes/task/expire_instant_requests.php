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

namespace local_nit_lessons\task;

use local_nit_lessons\service\instant_service;

/**
 * Ends instant-lesson requests whose waiting time is up, when no page is polling them.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expire_instant_requests extends \core\task\scheduled_task {

    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_expireinstant', 'local_nit_lessons');
    }

    /**
     * Expire what is due.
     */
    public function execute() {
        $service = new instant_service();
        $count = $service->expire_due(time());
        if ((int) date('i') === 0) {
            $service->cleanup(time()); // Hourly: old watches and 30-day-old request photos.
        }
        mtrace("local_nit_lessons: $count instant request(s) expired");
    }
}
