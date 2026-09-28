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
 * Event observers for local_parent.
 *
 * A student names their parent's phone in a custom profile field at sign-up;
 * we watch user creation/update to turn that into a (pending) link. Both events
 * are needed: on e-mail sign-up the profile field is saved just AFTER the user
 * is created, which fires user_updated — user_created alone can miss it.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_parent\observer::user_created',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => '\local_parent\observer::user_updated',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_parent\observer::quiz_attempt_submitted',
    ],
    [
        'eventname' => '\core\event\user_graded',
        'callback'  => '\local_parent\observer::user_graded',
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_parent\observer::course_completed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => '\local_parent\observer::user_enrolment_created',
    ],
];
