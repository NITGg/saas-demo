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

namespace mod_games\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_games\play_manager;

/**
 * Record one finished round against a Game activity.
 *
 * The corner's own submit_result has already run by the time this is called -
 * the child's points and badges are the corner's business and are saved whether
 * or not the round was played from a course. This function answers the separate
 * question of what happened inside this course module, and moves completion on.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_play extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module id of the Game activity'),
            'correct' => new external_value(PARAM_INT, 'Correct answers this round'),
            'streak'  => new external_value(PARAM_INT, 'Longest run of correct answers this round'),
            'score'   => new external_value(PARAM_INT, 'Round score as the game counts it'),
        ]);
    }

    /**
     * Store the round against this activity.
     *
     * @param int $cmid
     * @param int $correct
     * @param int $streak
     * @param int $score
     * @return array
     */
    public static function execute(int $cmid, int $correct, int $streak, int $score): array {
        global $USER, $DB, $CFG;

        // The AJAX endpoint does not load this the way a module page does, and
        // completion_info is a legacy global class with no autoloader behind it.
        require_once($CFG->libdir . '/completionlib.php');

        [
            'cmid'    => $cmid,
            'correct' => $correct,
            'streak'  => $streak,
            'score'   => $score,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid'    => $cmid,
            'correct' => $correct,
            'streak'  => $streak,
            'score'   => $score,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'games');

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/games:play', $context);

        $game = $DB->get_record('games', ['id' => $cm->instance], '*', MUST_EXIST);

        $record = play_manager::record($game->id, $USER->id, $correct, $streak, $score);

        // Completion is re-read rather than set: the rules live in
        // custom_completion, so this only has to say "something changed".
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
        }

        return [
            'plays'      => (int) $record->plays,
            'points'     => (int) $record->points,
            'bestscore'  => (int) $record->bestscore,
            'beststreak' => (int) $record->beststreak,
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'plays'      => new external_value(PARAM_INT, 'Rounds finished in this activity'),
            'points'     => new external_value(PARAM_INT, 'Correct answers given in this activity'),
            'bestscore'  => new external_value(PARAM_INT, 'Best single-round score in this activity'),
            'beststreak' => new external_value(PARAM_INT, 'Longest run of correct answers in this activity'),
        ]);
    }
}
