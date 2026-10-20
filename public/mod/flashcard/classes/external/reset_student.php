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

namespace mod_flashcard\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_flashcard\local\api;

/**
 * Start one student over (the report's "Start this student over"): delete their progress
 * on this set; their next visit puts every word back in deck 1.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reset_student extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Flash Card Set'),
            'userid' => new external_value(PARAM_INT, 'The student to start over'),
        ]);
    }

    /**
     * Reset.
     *
     * @param int $cmid
     * @param int $userid
     * @return array
     */
    public static function execute(int $cmid, int $userid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'userid' => $userid]);
        $api = new api($params['cmid']);
        self::validate_context($api->context);
        require_capability('mod/flashcard:manage', $api->context);

        $deleted = $DB->count_records('flashcard_card', ['flashcardid' => $api->flashcard->id, 'userid' => $params['userid']]);
        $DB->delete_records('flashcard_card', ['flashcardid' => $api->flashcard->id, 'userid' => $params['userid']]);

        $completion = new \completion_info($api->course);
        if (($api->flashcard->completionallgood || $api->flashcard->completionallviewed) && $completion->is_enabled($api->cm)) {
            $completion->update_state($api->cm, COMPLETION_INCOMPLETE, $params['userid']);
        }
        return ['reset' => $deleted > 0];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reset' => new external_value(PARAM_BOOL, 'true = there was progress and it is gone; false = nothing to reset'),
        ]);
    }
}
