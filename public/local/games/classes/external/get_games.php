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
 * The whole catalogue, with this learner's progress against it.
 *
 * The mobile equivalent of index.php: everything a hub screen needs in one
 * call - the sections, the cards, the totals and the badge shelf.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_games extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Build the catalogue.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/games:play', $context);

        $totals   = progress::get_totals((int) $USER->id);
        $played   = progress::get_user_progress((int) $USER->id);
        $earned   = progress::get_user_badges((int) $USER->id);
        $allgames = registry::get_games();

        $categories = [];
        foreach (registry::get_categories() as $catkey => $catemoji) {
            $cards = [];

            foreach ($allgames as $id => $game) {
                if ($game['category'] !== $catkey) {
                    continue;
                }
                $row = $played[$id] ?? null;

                $cards[] = [
                    'id'         => $id,
                    'emoji'      => $game['emoji'],
                    'name'       => mlang::display(registry::name($id)),
                    'description' => mlang::display(registry::description($id)),
                    'level'      => (int) $game['level'],
                    // The web hub renders this as one to three stars. An app is
                    // free to draw it any way it likes, so the number travels
                    // rather than the stars.
                    'live'       => $game['status'] === registry::STATUS_LIVE,
                    'plays'      => $row ? (int) $row->plays : 0,
                    'points'     => $row ? (int) $row->points : 0,
                    'bestscore'  => $row ? (int) $row->bestscore : 0,
                    'beststreak' => $row ? (int) $row->beststreak : 0,
                ];
            }

            if (!$cards) {
                continue;
            }

            // A section only carries an explanatory line if the language pack
            // has written one for it. Asking rather than hard-coding which
            // sections have notes means a translator can add or drop one
            // without a code change - and means this cannot throw over a
            // string that was never written.
            $notekey = 'cat_' . $catkey . '_note';
            $hasnote = get_string_manager()->string_exists($notekey, 'local_games');

            $categories[] = [
                'key'   => $catkey,
                'emoji' => $catemoji,
                'name'  => get_string('cat_' . $catkey, 'local_games'),
                'note'  => $hasnote ? get_string($notekey, 'local_games') : '',
                'games' => $cards,
            ];
        }

        // Every badge the corner can give, with the earned ones marked.
        $badges = [];
        foreach ($allgames as $id => $game) {
            foreach ($game['badges'] ?? [] as $badge => $unusedrule) {
                $badgekey = registry::key($badge);
                $badges[] = [
                    'key'    => $badge,
                    'gameid' => $id,
                    'name'   => get_string('badge_' . $badgekey, 'local_games'),
                    'hint'   => get_string('badgehint_' . $badgekey, 'local_games'),
                    'earned' => in_array($badge, $earned, true),
                ];
            }
        }

        return [
            // The headings the hub page is written in. They travel with the
            // catalogue so a client can draw the whole screen from this one
            // call, in the caller's language, without a second round trip for
            // half a dozen strings.
            'title'        => get_string('hubtitle', 'local_games'),
            'intro'        => get_string('hubintro', 'local_games'),
            'pointslabel'  => get_string('yourpoints', 'local_games'),
            'badgeslabel'  => get_string('yourbadges', 'local_games'),
            'playlabel'    => get_string('play', 'local_games'),
            'soonlabel'    => get_string('comingsoon', 'local_games'),
            'bestscorelabel' => get_string('bestscore', 'local_games', '{score}'),
            'points'     => $totals['points'],
            'badges'     => $totals['badges'],
            'plays'      => $totals['plays'],
            'categories' => $categories,
            'badgeshelf' => $badges,
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'title'        => new external_value(PARAM_TEXT, 'Screen heading in the current language'),
            'intro'        => new external_value(PARAM_TEXT, 'One line under the heading'),
            'pointslabel'  => new external_value(PARAM_TEXT, 'Label for the points counter'),
            'badgeslabel'  => new external_value(PARAM_TEXT, 'Label for the badge counter'),
            'playlabel'    => new external_value(PARAM_TEXT, 'Label for the button on a playable card'),
            'soonlabel'    => new external_value(PARAM_TEXT, 'Label shown instead on a card that is not built yet'),
            'bestscorelabel' => new external_value(PARAM_TEXT,
                'Best-score line with a {score} placeholder to substitute, e.g. "Best: {score}"'),
            'points' => new external_value(PARAM_INT, 'Lifetime points across the whole corner'),
            'badges' => new external_value(PARAM_INT, 'How many badges the user holds'),
            'plays'  => new external_value(PARAM_INT, 'Rounds finished across the whole corner'),
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'key'   => new external_value(PARAM_ALPHANUMEXT, 'Section key'),
                    'emoji' => new external_value(PARAM_TEXT, 'Section icon'),
                    'name'  => new external_value(PARAM_TEXT, 'Section name in the current language'),
                    'note'  => new external_value(PARAM_TEXT, 'Optional line under the section heading; empty when there is none'),
                    'games' => new external_multiple_structure(
                        new external_single_structure([
                            'id'          => new external_value(PARAM_ALPHANUMEXT, 'Game slug, e.g. math-race'),
                            'emoji'       => new external_value(PARAM_TEXT, 'Card face'),
                            'name'        => new external_value(PARAM_TEXT, 'Game name in the current language'),
                            'description' => new external_value(PARAM_TEXT, 'One-line description'),
                            'level'       => new external_value(PARAM_INT, 'Difficulty, 1 to 3'),
                            'live'        => new external_value(PARAM_BOOL, 'False for a game that is planned but not built'),
                            'plays'       => new external_value(PARAM_INT, 'Rounds this user has finished'),
                            'points'      => new external_value(PARAM_INT, 'Lifetime points in this game'),
                            'bestscore'   => new external_value(PARAM_INT, 'Best single-round score'),
                            'beststreak'  => new external_value(PARAM_INT, 'Longest run of correct answers'),
                        ])
                    ),
                ])
            ),
            'badgeshelf' => new external_multiple_structure(
                new external_single_structure([
                    'key'    => new external_value(PARAM_ALPHANUMEXT, 'Badge key'),
                    'gameid' => new external_value(PARAM_ALPHANUMEXT, 'Game that awards it'),
                    'name'   => new external_value(PARAM_TEXT, 'Badge name in the current language'),
                    'hint'   => new external_value(PARAM_TEXT, 'What has to be done to earn it'),
                    'earned' => new external_value(PARAM_BOOL, 'Whether this user already holds it'),
                ])
            ),
        ]);
    }
}
