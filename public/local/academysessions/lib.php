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

/**
 * Callbacks of local_academysessions.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course "More" menu: "Live monitoring" (this course preselected) for a manager of
 * the course or its category. Site-wide supervisors use Site administration.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_academysessions_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ((int) $course->id <= SITEID || !has_capability('local/academysessions:monitorlive', $context)) {
        return;
    }
    $navigation->add(
        get_string('monitor', 'local_academysessions'),
        new moodle_url('/local/academysessions/monitor.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_academysessions_monitor',
        new pix_icon('i/show', '')
    );
}
