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
 * DEV ONLY: set up live lessons for trying by hand. Never run on production.
 *
 *  - a test teacher (editing teacher in --courseid, default 4) who takes bookings in "Physics";
 *  - a hidden "Live lessons" course for the Jitsi rooms, set as lessons_courseid (if none is set);
 *  - a demo package "Flex 5" if no package is for sale.
 *
 * Use with the test student of local/nit_finance/cli/seed_test_accounts.php. Idempotent.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

/** Test teacher login (local development only). */
const TEST_TEACHER_USERNAME = 'nit_test_lessons_teacher';
/** Test teacher password (local development only). */
const TEST_TEACHER_PASSWORD = 'Tch-Y373YB979u-5k';

[$options] = cli_get_params(['courseid' => 4, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Creates the live-lessons dev data. --courseid=N: course the test teacher teaches (default 4).');
    exit(0);
}

$teacher = $DB->get_record('user', ['username' => TEST_TEACHER_USERNAME, 'deleted' => 0]);
if (!$teacher) {
    $id = user_create_user((object) [
        'username' => TEST_TEACHER_USERNAME,
        'password' => TEST_TEACHER_PASSWORD,
        'firstname' => 'مدرس',
        'lastname' => 'الحصص',
        'email' => TEST_TEACHER_USERNAME . '@example.com',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'ar',
    ]);
    $teacher = $DB->get_record('user', ['id' => $id]);
    cli_writeln('Created ' . TEST_TEACHER_USERNAME . " (id {$teacher->id})");
} else {
    cli_writeln(TEST_TEACHER_USERNAME . " exists (id {$teacher->id})");
}
$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
enrol_try_internal_enrol((int) $options['courseid'], (int) $teacher->id, $roleid);
(new \local_nit_lessons\service\teacher_service())->save((int) $teacher->id, true, 'مدرس فيزياء', ['فيزياء', 'Physics'],
    [['dayofweek' => 0, 'starttime' => '10:00', 'endtime' => '22:00'],
     ['dayofweek' => 2, 'starttime' => '10:00', 'endtime' => '22:00'],
     ['dayofweek' => 4, 'starttime' => '10:00', 'endtime' => '22:00']]);
cli_writeln("Teacher of course {$options['courseid']}, takes bookings (Sun/Tue/Thu 10:00–22:00)");

if (!\local_nit_lessons\room\jitsi_room::configured()) {
    $course = $DB->get_record('course', ['shortname' => 'nit_live_lessons']);
    if (!$course) {
        $course = create_course((object) [
            'fullname' => '{mlang ar}الحصص المباشرة{mlang}{mlang en}Live lessons{mlang}',
            'shortname' => 'nit_live_lessons',
            'category' => (int) $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}'),
            'visible' => 0,
            'format' => 'topics',
            'numsections' => 0,
        ]);
    }
    set_config('lessons_courseid', $course->id, 'local_nit_lessons');
    cli_writeln("Lesson rooms course: {$course->id}");
}

if (!\local_nit_flex\api\packages::available()) {
    \local_nit_flex\api\packages::create((object) [
        'name' => '{mlang ar}باقة 5 حصص{mlang}{mlang en}5 lessons package{mlang}',
        'description' => '{mlang ar}5 حصص مباشرة مع أي مدرس{mlang}{mlang en}5 live lessons with any teacher{mlang}',
        'flex_count' => 5, 'price_minor' => 50000, 'expiration_days' => 30,
    ]);
    cli_writeln('Created package "5 lessons" (500 EGP)');
}
