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

namespace mod_jitsi\task;

/**
 * Close room stretches whose heartbeat stopped (app killed, signal lost, browser
 * crashed), at their last heartbeat. See \mod_jitsi\local\roomlog.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_stale_presence extends \core\task\scheduled_task {

    /**
     * Name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_closestalepresence', 'jitsi');
    }

    /**
     * Run.
     */
    public function execute() {
        $closed = \mod_jitsi\local\roomlog::close_stale();
        if ($closed) {
            mtrace("Closed {$closed} room stretch(es) with no heartbeat.");
        }
    }
}
