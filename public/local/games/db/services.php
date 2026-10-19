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
 * External functions for the Games Corner.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // The catalogue, with this learner's progress against it. The mobile
    // equivalent of the hub page.
    'local_games_get_games' => [
        'classname'   => \local_games\external\get_games::class,
        'description' => 'List every game in the corner with the caller own progress, totals and badges.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/games:play',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // One game: its start-card text, its badges and where the caller stands.
    'local_games_get_game' => [
        'classname'   => \local_games\external\get_game::class,
        'description' => 'Describe one game and the caller own progress in it.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/games:play',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // The shared banks the games are made of. Cacheable against the revision
    // it returns, so an app fetches them once rather than per game.
    'local_games_get_content' => [
        'classname'   => \local_games\external\get_content::class,
        'description' => 'Return the word, question, statement, clue and colour banks plus every game string.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/games:play',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Called by the browser at the end of a round. `ajax => true` is what makes
    // it reachable from /lib/ajax/service.php without a web-service token.
    'local_games_submit_result' => [
        'classname'   => \local_games\external\submit_result::class,
        'description' => 'Record the result of one finished game round and return the updated totals.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/games:play',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

// A service of our own, next to the official mobile one.
//
// The four functions above are already in MOODLE_OFFICIAL_MOBILE_SERVICE, but
// that service belongs to the Moodle App: turning it on hands a caller every
// core function that app needs, and its token comes from a login flow built
// for it. A native Games Academy client wants neither. This service carries
// the corner and nothing else, so a token minted for it can reach the corner
// and nothing else - and it gives the app a shortname to ask
// /login/token.php for.
$services = [
    'Games Corner' => [
        'shortname'       => 'local_games',
        'functions'       => [
            'local_games_get_games',
            'local_games_get_game',
            'local_games_get_content',
            'local_games_submit_result',
            // Who am I, what is this site called, which language am I in. The
            // first call any client makes, and the one that tells it whether a
            // stored token is still good.
            'core_webservice_get_site_info',
        ],
        // On from the moment the plugin installs: a service that ships
        // disabled is a support ticket waiting to happen.
        'enabled'         => 1,
        // Anyone holding local/games:play may get a token. Flip this to 1 in
        // Site administration if tokens should only go to named accounts.
        'restrictedusers' => 0,
        // The corner is emoji and text; it serves no files in either direction.
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
