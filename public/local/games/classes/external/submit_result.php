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

namespace local_games\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_games\progress;
use local_games\registry;

/**
 * Record one finished round of a game.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_result extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gameid'  => new external_value(PARAM_ALPHANUMEXT, 'Game slug, e.g. math-race'),
            'correct' => new external_value(PARAM_INT, 'Correct answers this round'),
            'wrong'   => new external_value(PARAM_INT, 'Wrong answers this round'),
            'streak'  => new external_value(PARAM_INT, 'Longest run of correct answers this round'),
            'score'   => new external_value(PARAM_INT, 'Round score as the game counts it'),
            'goal'    => new external_value(PARAM_INT, 'How many times the game met its own goal', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Store the round and hand back the new totals.
     *
     * @param string $gameid
     * @param int $correct
     * @param int $wrong
     * @param int $streak
     * @param int $score
     * @param int $goal
     * @return array
     */
    public static function execute(string $gameid, int $correct, int $wrong, int $streak, int $score,
            int $goal = 0): array {
        global $USER;

        [
            'gameid'  => $gameid,
            'correct' => $correct,
            'wrong'   => $wrong,
            'streak'  => $streak,
            'score'   => $score,
            'goal'    => $goal,
        ] = self::validate_parameters(self::execute_parameters(), [
            'gameid'  => $gameid,
            'correct' => $correct,
            'wrong'   => $wrong,
            'streak'  => $streak,
            'score'   => $score,
            'goal'    => $goal,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/games:play', $context);

        // PARAM_ALPHANUMEXT keeps dashes, so the slug arrives intact; the
        // registry is what decides whether it is a real, finished game.
        if (!registry::is_live($gameid)) {
            throw new \moodle_exception('errorunknowngame', 'local_games');
        }

        $result = progress::submit((int) $USER->id, $gameid, $correct, $wrong, $streak, $score, $goal);

        // The browser needs the human-readable badge names to celebrate with.
        $newbadges = [];
        foreach ($result['newbadges'] as $badge) {
            $newbadges[] = [
                'key'  => $badge,
                'name' => get_string('badge_' . registry::key($badge), 'local_games'),
            ];
        }
        $result['newbadges'] = $newbadges;

        return $result;
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'points'     => new external_value(PARAM_INT, 'Lifetime points across the whole corner'),
            'badges'     => new external_value(PARAM_INT, 'How many badges the user now holds'),
            'gamepoints' => new external_value(PARAM_INT, 'Lifetime points in this game'),
            'bestscore'  => new external_value(PARAM_INT, 'Best single-round score in this game'),
            'newbadges'  => new external_multiple_structure(
                new external_single_structure([
                    'key'  => new external_value(PARAM_ALPHANUMEXT, 'Badge key'),
                    'name' => new external_value(PARAM_TEXT, 'Badge name in the current language'),
                ]),
                'Badges won by this round'
            ),
        ]);
    }
}
