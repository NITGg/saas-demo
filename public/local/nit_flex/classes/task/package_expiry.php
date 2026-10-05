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

namespace local_nit_flex\task;

use local_nit_flex\service\purchase_service;

/**
 * Hourly: remind students whose package ends soon, then close packages whose time ran out
 * (unused Flex becomes platform income).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_expiry extends \core\task\scheduled_task {

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_expiry', 'local_nit_flex');
    }

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $service = new purchase_service();
        $now = time();
        $days = (int) get_config('local_nit_flex', 'expiry_reminder_days');
        if (get_config('local_nit_flex', 'expiry_reminder_days') === false) {
            $days = 3;
        }
        foreach ($service->due_for_reminder($now, $days) as $row) {
            $DB->set_field('nit_package_purchase', 'expiry_notified', $now, ['id' => $row->id]);
            \local_nit_flex\local\notifier::expiry_reminder($row);
        }
        $closed = $service->expire_due($now);
        if ($closed) {
            mtrace("local_nit_flex: closed {$closed} expired package(s).");
        }
    }
}
