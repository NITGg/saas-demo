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
use local_games\registry;
use mod_games\content;

/**
 * The content a Game activity is played from, for a native client.
 *
 * Same response as local_games_get_content, with the activity's content - its
 * own rows, the corner's default, or both, as the teacher chose - in the slot the
 * game reads.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_content extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Game activity'),
        ]);
    }

    /**
     * The activity's game content.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'games');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/games:view', $context);

        $game = $DB->get_record('games', ['id' => $cm->instance], '*', MUST_EXIST);

        if (!registry::is_live($game->gameid)) {
            throw new \moodle_exception('errorunknowngame', 'local_games');
        }

        return \local_games\external\get_content::response($game->gameid, content::payload($game));
    }

    /**
     * Returns: exactly local_games_get_content's structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return \local_games\external\get_content::execute_returns();
    }
}
