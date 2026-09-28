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
use local_parent\link_manager;

/**
 * The children linked to the calling parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_children extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * @return array child list
     */
    public static function execute(): array {
        global $USER, $DB, $PAGE;

        self::validate_context(\context_system::instance());
        // No capability check: list_children() only returns rows where the caller
        // IS the parent, so the link table is the authorization.

        $children = [];
        foreach (link_manager::list_children((int) $USER->id) as $studentid) {
            $user = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0],
                'id, firstname, lastname, email', IGNORE_MISSING);
            if (!$user) {
                continue;
            }
            $courses = enrol_get_all_users_courses($studentid, true, 'id');
            $children[] = [
                'id'          => (int) $user->id,
                'fullname'    => fullname($user),
                'email'       => $user->email,
                'coursecount' => count($courses),
            ];
        }
        return ['children' => $children];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'children' => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'Child user id'),
                    'fullname'    => new external_value(PARAM_TEXT, 'Child full name'),
                    'email'       => new external_value(PARAM_TEXT, 'Child email'),
                    'coursecount' => new external_value(PARAM_INT, 'How many courses the child is enrolled in'),
                ])
            ),
        ]);
    }
}
