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
 * Core callbacks of local_academy.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Runs before every web service / AJAX external function (web drawer and the
 * mobile app alike). Used for "Prevent messaging between students".
 *
 * @param stdClass $function external function info
 * @param array $params validated parameters
 * @return mixed the result, or false to run the function as usual
 */
function local_academy_override_webservice_execution($function, $params) {
    return \local_academy\local\student_messaging::override_webservice($function, $params);
}

/**
 * Check the files of a file lesson (File / Folder activity) before it is saved:
 * documents and images only, up to the size set by an admin (BUG-039).
 *
 * @param moodleform_mod $formwrapper
 * @param array $data the submitted form data
 * @return array errors keyed by form field
 */
function local_academy_coursemodule_validation($formwrapper, $data) {
    global $USER;
    $modname = \local_academy\local\file_lessons::form_modname($formwrapper);
    return \local_academy\local\file_lessons::validate($modname, (array) $data, (int) $USER->id);
}

/**
 * The file picker of a file lesson shows the lesson limit (BUG-039).
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_academy_coursemodule_standard_elements($formwrapper, $mform) {
    \local_academy\local\file_lessons::limit_picker(\local_academy\local\file_lessons::form_modname($formwrapper), $mform);
}
