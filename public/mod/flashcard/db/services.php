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
 * External functions of the Flash Card Set, for the mobile app (docs/apis/flashcard-mobile-api.md).
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // The activity's first screen: settings and the caller's deck table.
    'mod_flashcard_get_flashcard' => [
        'classname'   => \mod_flashcard\external\get_flashcard::class,
        'description' => 'Open a Flash Card Set: its settings and the caller own decks (due / waiting / empty).',
        'type'        => 'write', // Marks the activity viewed for completion.
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // The cards of one Leitner round (deck >= 1) or of free play (deck = 0).
    'mod_flashcard_get_cards' => [
        'classname'   => \mod_flashcard\external\get_cards::class,
        'description' => 'Return the shuffled cards of one due deck of the caller, or of the whole set for free play.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // One "I got it" / "I missed".
    'mod_flashcard_submit_answer' => [
        'classname'   => \mod_flashcard\external\submit_answer::class,
        'description' => 'Record one Leitner answer of the caller and return the updated deck table.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Teacher: the follow-up report.
    'mod_flashcard_get_report' => [
        'classname'    => \mod_flashcard\external\get_report::class,
        'description'  => 'Teacher report: every student with status and hard words, every word with class progress.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/flashcard:manage',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Teacher: start one student over.
    'mod_flashcard_reset_student' => [
        'classname'    => \mod_flashcard\external\reset_student::class,
        'description'  => 'Teacher: delete one student progress on the set so they start again.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/flashcard:manage',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
