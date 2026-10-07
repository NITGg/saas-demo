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
 * Navigation callbacks for local_nit_reports.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course "More" menu: "Reports" (this course preselected) for the course's
 * managers and teachers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_nit_reports_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ((int) $course->id <= 1) {
        return;
    }
    if (!has_capability('local/nit_reports:view', $context) && !has_capability('local/nit_reports:viewteaching', $context)) {
        return;
    }
    $navigation->add(
        get_string('reports', 'local_nit_reports'),
        new moodle_url('/local/nit_reports/index.php', ['report' => 'courses', 'courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_nit_reports',
        new pix_icon('i/report', '')
    );
}
