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
 * External functions for mod_games.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // Called by the browser at the end of a round played inside the activity.
    // `ajax => true` is what makes it reachable from /lib/ajax/service.php with
    // the session, rather than needing a web-service token.
    'mod_games_record_play' => [
        'classname'    => \mod_games\external\record_play::class,
        'description'  => 'Record one finished round against a Game activity and move its completion on.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/games:play',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
