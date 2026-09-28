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

namespace local_parent\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_parent\child_report;

/**
 * One child's marks: the course total for every course they are enrolled in.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class child_grades extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'studentid' => new external_value(PARAM_INT, 'The child user id'),
        ]);
    }

    /**
     * @param int $studentid
     * @return array
     */
    public static function execute(int $studentid): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), ['studentid' => $studentid]);
        self::validate_context(\context_user::instance($studentid));
        child_report::guard((int) $USER->id, $studentid);

        return ['courses' => child_report::grades($studentid)];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'   => new external_value(PARAM_INT, 'Course id'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                    'grade'      => new external_value(PARAM_TEXT, 'Formatted course total, or -'),
                    'percentage' => new external_value(PARAM_FLOAT, 'Course total as a percentage, or null', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }
}
