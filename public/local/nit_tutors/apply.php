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
 * "Join as a teacher": the stable address of the teacher sign-up.
 *
 * A visitor gets the academy sign-up page in its teacher mode (/local/academy/register.php?as=teacher,
 * same design as the student sign-up; the account is made by \local_nit_tutors\local\signup). A
 * signed-in user starts or opens their application (/local/nit_tutors/application.php).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$PAGE->set_url(new moodle_url('/local/nit_tutors/apply.php'));
$PAGE->set_context(context_system::instance());

if (isloggedin() && !isguestuser()) {
    \local_nit_tutors\local\application::ensure((int) $USER->id);
    redirect(new moodle_url('/local/nit_tutors/application.php'));
}
redirect(new moodle_url('/local/academy/register.php', ['as' => 'teacher']));
