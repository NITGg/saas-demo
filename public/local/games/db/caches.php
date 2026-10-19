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
 * MUC cache definitions for the Games Corner.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // What "Game control" has changed: the catalogue overrides, the per-language
    // names, and the content banks the admin has taken over from the language
    // pack. Every hub page load and every round of every game reads all of it,
    // and it changes only when an admin saves the admin screen - so it is
    // cached whole and purged whole on save.
    'overrides' => [
        'mode'                   => \core_cache\store::MODE_APPLICATION,
        'simplekeys'             => true,
        'simpledata'             => false,
        'staticacceleration'     => true,
        'staticaccelerationsize' => 12,
    ],
];
