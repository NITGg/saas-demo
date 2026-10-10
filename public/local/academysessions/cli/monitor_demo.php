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
 * DEV ONLY: demo lessons for trying the live monitoring wall (monitor.php) by hand.
 * Never run on production.
 *
 *   php monitor_demo.php --create         five live sessions titled "ZZ demo …", one per state
 *                                         (running, teacher late, waiting, upcoming, teacher left)
 *   php monitor_demo.php --delete         removes every "ZZ demo …" session
 *   On "ZZ demo running" (watch its card update):
 *     --studentleaves / --studentreturns  the student leaves / comes back
 *     --teacherleaves / --teacherreturns  the teacher leaves the call / comes back
 *     --end                               the teacher ends the lesson
 *
 * Uses the test teacher of local/nit_lessons/cli/seed_demo.php, the test student of
 * local/nit_finance/cli/seed_test_accounts.php, and the lessons course. Open the wall
 * as the test manager (nit_test_manager) or an admin.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_academysessions\session_manager;

[$options] = cli_get_params(['create' => false, 'delete' => false, 'studentleaves' => false, 'studentreturns' => false,
    'teacherleaves' => false, 'teacherreturns' => false, 'end' => false, 'help' => false], ['h' => 'help']);
if ($options['help'] || !array_filter($options)) {
    cli_writeln('Demo lessons for the live monitoring wall: --create | --delete, and on "ZZ demo running": '
        . '--studentleaves | --studentreturns | --teacherleaves | --teacherreturns | --end');
    exit(0);
}

$demo = $DB->sql_like('title', ':t');
$demoparams = ['t' => 'ZZ demo%'];

if ($options['delete']) {
    $ids = $DB->get_fieldset_select('academy_live_sessions', 'id', $demo, $demoparams);
    foreach ($ids as $id) {
        $DB->delete_records('academy_session_attendance', ['sessionid' => $id]);
        $DB->delete_records('academy_session_students', ['sessionid' => $id]);
        $DB->delete_records('academy_live_sessions', ['id' => $id]);
    }
    cli_writeln('Deleted ' . count($ids) . ' demo session(s).');
    exit(0);
}

$teacher = $DB->get_field('user', 'id', ['username' => 'nit_test_lessons_teacher', 'deleted' => 0]);
$student = $DB->get_field('user', 'id', ['username' => 'nit_test_buyer', 'deleted' => 0]);
$courseid = (int) get_config('local_nit_lessons', 'lessons_courseid');
if (!$teacher || !$student || !$courseid) {
    cli_error('Run local/nit_lessons/cli/seed_demo.php and local/nit_finance/cli/seed_test_accounts.php first.');
}

$actions = ['studentleaves', 'studentreturns', 'teacherleaves', 'teacherreturns', 'end'];
$action = current(array_filter($actions, fn($a) => !empty($options[$a])));
if ($action) {
    $id = (int) $DB->get_field('academy_live_sessions', 'id', ['title' => 'ZZ demo running']);
    if (!$id) {
        cli_error('No "ZZ demo running" session: run --create first.');
    }
    $now = time();
    switch ($action) {
        case 'studentleaves':
            session_manager::record_leave($id, (int) $student);
            break;
        case 'studentreturns':
            session_manager::record_attendance($id, (int) $student, true);
            break;
        case 'teacherleaves':
            // What mod_jitsi\local\presence::set() does when the teacher leaves the call.
            $DB->update_record('academy_live_sessions', (object) ['id' => $id, 'teacher_joined_at' => null,
                'teacher_last_leave' => $now]);
            session_manager::record_leave($id, (int) $teacher, $now);
            break;
        case 'teacherreturns':
            $DB->set_field('academy_live_sessions', 'teacher_joined_at', $now, ['id' => $id]);
            session_manager::record_attendance($id, (int) $teacher, true);
            break;
        case 'end':
            session_manager::end_session($id, 'teacher', (int) $teacher);
            break;
    }
    cli_writeln("Done: $action (session $id).");
    exit(0);
}

$now = time();
// No Jitsi room: a room belongs to one session, and linking a real room to a demo
// session would change who may enter it. The cards show "External link".
$make = function(string $title, int $offset, array $fields = []) use ($DB, $teacher, $student, $courseid, $now) {
    $id = (int) session_manager::create_session($courseid, $teacher, $title, $now + $offset, [$student], '', 50);
    if ($fields) {
        $DB->update_record('academy_live_sessions', (object) (['id' => $id] + $fields));
    }
    return $id;
};
$running = $make('ZZ demo running', -12 * MINSECS, ['status' => 'live', 'teacher_joined_at' => $now - 4 * MINSECS,
    'teacher_first_join' => $now - 4 * MINSECS]);
session_manager::record_attendance($running, (int) $teacher);
session_manager::record_attendance($running, (int) $student);
// The teacher came in 4 minutes ago, the student 3.
foreach ([$teacher => 4, $student => 3] as $uid => $ago) {
    $DB->set_field('academy_session_presence', 'joined_at', $now - $ago * MINSECS, ['sessionid' => $running, 'userid' => $uid]);
    $DB->set_field('academy_session_attendance', 'joined_at', $now - $ago * MINSECS, ['sessionid' => $running, 'userid' => $uid]);
}
$make('ZZ demo late teacher', -15 * MINSECS, ['status' => 'live']);
$make('ZZ demo waiting', -2 * MINSECS, ['status' => 'live']);
$make('ZZ demo upcoming', 20 * MINSECS);
$make('ZZ demo teacher left', -25 * MINSECS, ['status' => 'live', 'teacher_first_join' => $now - 24 * MINSECS,
    'teacher_last_leave' => $now - 6 * MINSECS]);
cli_writeln('Created 5 demo sessions. Open /local/academysessions/monitor.php as an admin or the test manager.');
