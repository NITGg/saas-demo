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
use local_games\mlang;
use local_games\registry;

/**
 * One game: what to put on its start card, and where this learner stands in it.
 *
 * The mobile equivalent of the top half of play.php. It deliberately does not
 * carry the content banks - those are shared between games and come from
 * local_games_get_content once, rather than with every game the child opens.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_game extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gameid' => new external_value(PARAM_ALPHANUMEXT, 'Game slug, e.g. math-race'),
        ]);
    }

    /**
     * Describe one game.
     *
     * @param string $gameid
     * @return array
     */
    public static function execute(string $gameid): array {
        global $USER;

        ['gameid' => $gameid] = self::validate_parameters(self::execute_parameters(), ['gameid' => $gameid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/games:play', $context);

        // PARAM_ALPHANUMEXT keeps dashes, so the slug arrives intact; the
        // registry is what decides whether it is a real, finished game.
        if (!registry::is_live($gameid)) {
            throw new \moodle_exception('errorunknowngame', 'local_games');
        }

        $game = registry::get_game($gameid);
        $key  = registry::key($gameid);

        $played = progress::get_user_progress((int) $USER->id);
        $earned = progress::get_user_badges((int) $USER->id);
        $row    = $played[$gameid] ?? null;

        $badges = [];
        foreach ($game['badges'] ?? [] as $badge => $rule) {
            $badgekey = registry::key($badge);
            $badges[] = [
                'key'      => $badge,
                'name'     => get_string('badge_' . $badgekey, 'local_games'),
                'hint'     => get_string('badgehint_' . $badgekey, 'local_games'),
                'earned'   => in_array($badge, $earned, true),
                // The rule itself, so an app can show how close a child is
                // instead of only whether they got there. Every threshold is
                // optional and they are ANDed together; -1 means "not part of
                // this badge's rule", which a zero could not say.
                'streak'   => isset($rule['streak']) ? (int) $rule['streak'] : -1,
                'correct'  => isset($rule['correct']) ? (int) $rule['correct'] : -1,
                'maxwrong' => isset($rule['maxwrong']) ? (int) $rule['maxwrong'] : -1,
                'goal'     => isset($rule['goal']) ? (int) $rule['goal'] : -1,
            ];
        }

        return [
            'id'          => $gameid,
            'emoji'       => $game['emoji'],
            'category'    => $game['category'],
            'level'       => (int) $game['level'],
            'name'        => mlang::display(registry::name($gameid)),
            'description' => mlang::display(registry::description($gameid)),
            'readytitle'  => get_string('js_' . $key . '_ready', 'local_games'),
            'howto'       => get_string('js_' . $key . '_howto', 'local_games'),
            'plays'       => $row ? (int) $row->plays : 0,
            'points'      => $row ? (int) $row->points : 0,
            'bestscore'   => $row ? (int) $row->bestscore : 0,
            'beststreak'  => $row ? (int) $row->beststreak : 0,
            'badges'      => $badges,
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'          => new external_value(PARAM_ALPHANUMEXT, 'Game slug'),
            'emoji'       => new external_value(PARAM_TEXT, 'Card face'),
            'category'    => new external_value(PARAM_ALPHANUMEXT, 'Section the game sits in'),
            'level'       => new external_value(PARAM_INT, 'Difficulty, 1 to 3'),
            'name'        => new external_value(PARAM_TEXT, 'Game name in the current language'),
            'description' => new external_value(PARAM_TEXT, 'One-line description'),
            'readytitle'  => new external_value(PARAM_TEXT, 'Heading for the start screen'),
            'howto'       => new external_value(PARAM_TEXT, 'How the game is played, in one short paragraph'),
            'plays'       => new external_value(PARAM_INT, 'Rounds this user has finished'),
            'points'      => new external_value(PARAM_INT, 'Lifetime points in this game'),
            'bestscore'   => new external_value(PARAM_INT, 'Best single-round score'),
            'beststreak'  => new external_value(PARAM_INT, 'Longest run of correct answers'),
            'badges'      => new external_multiple_structure(
                new external_single_structure([
                    'key'      => new external_value(PARAM_ALPHANUMEXT, 'Badge key'),
                    'name'     => new external_value(PARAM_TEXT, 'Badge name in the current language'),
                    'hint'     => new external_value(PARAM_TEXT, 'What has to be done to earn it'),
                    'earned'   => new external_value(PARAM_BOOL, 'Whether this user already holds it'),
                    'streak'   => new external_value(PARAM_INT, 'Longest run needed, or -1 when the rule does not use it'),
                    'correct'  => new external_value(PARAM_INT, 'Correct answers needed, or -1'),
                    'maxwrong' => new external_value(PARAM_INT, 'Most wrong answers allowed, or -1'),
                    'goal'     => new external_value(PARAM_INT, 'Times the game own goal must be met, or -1'),
                ])
            ),
        ]);
    }
}
