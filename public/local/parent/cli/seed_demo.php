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
 * DEV ONLY: demo data for the parent dashboard (/parent_dashboard). Never run on production.
 *
 *   php local/parent/cli/seed_demo.php [--reset]
 *
 * Creates, inside a HIDDEN category (so nothing shows on the home page or the
 * catalogue), a demo student and three courses that exercise every state of the
 * dashboard: watched / not-watched videos, graded / handed-in / missed homework,
 * passed / missed exams, an empty lecture and a course with no content yet.
 * Without --reset an existing demo is left alone; --reset deletes it and builds it again.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/** Category idnumber that marks the demo. */
const DEMO_CATEGORY = 'nit_parent_demo';
/** Demo student. */
const DEMO_USERNAME = 'nit_parent_demo';
/** Demo student login (local development only). */
const DEMO_PASSWORD = 'Pd-7kQm2Wx9-Lr4';
const DEMO_STUDENT_PHONE = '01011112222';
const DEMO_PARENT_PHONE = '01233334444';
const DEMO_MOTHER_PHONE = '01555556666';

[$options] = cli_get_params(['reset' => false, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Builds the parent dashboard demo (hidden category + student). --reset rebuilds it.');
    exit(0);
}

\core\cron::setup_user();

$category = $DB->get_record('course_categories', ['idnumber' => DEMO_CATEGORY]);
$student = $DB->get_record('user', ['username' => DEMO_USERNAME, 'deleted' => 0]);
if (($category || $student) && !$options['reset']) {
    if ($student) {
        update_internal_user_password($student, DEMO_PASSWORD);
    }
    cli_writeln('The demo already exists (login password refreshed). Use --reset to rebuild it.');
    print_logins();
    exit(0);
}
if ($category) {
    foreach ($DB->get_records('course', ['category' => $category->id]) as $course) {
        delete_course($course, false);
    }
    core_course_category::get($category->id, MUST_EXIST, true)->delete_full(false);
    cli_writeln('Deleted the old demo courses.');
}
if ($student) {
    delete_user($student);
}

$gen = \core\test\phpunit\phpunit_util::get_data_generator();
$category = $gen->create_category(['name' => 'تجريبي — لوحة ولي الأمر', 'idnumber' => DEMO_CATEGORY, 'visible' => 0]);

$student = $gen->create_user([
    'username' => DEMO_USERNAME,
    'password' => DEMO_PASSWORD,
    'firstname' => 'يوسف',
    'lastname' => 'أحمد',
    'email' => DEMO_USERNAME . '@example.com',
    'phone1' => DEMO_STUDENT_PHONE,
    'lang' => 'ar',
]);
profile_save_custom_fields($student->id, ['parentphone' => DEMO_PARENT_PHONE, 'motherphone' => DEMO_MOTHER_PHONE]);

// Each course: [name, [lecture name => [items]]]. An item is [type, name, result]:
//   video: watched percent (0 = not watched)
//   assign: grade % | 'pending' (handed in, not marked) | null (missed)
//   quiz: grade % | null (missed)
$courses = [
    ['الكورس التأسيسي لطلاب "ثانوية عامة 2027" (محمد صلاح - لغة عربية 3 ثانوي)', [
        'محاضرة تأسيس النحو | طلاب ثانوية عامة' => [
            ['video', 'شرح المبتدأ والخبر', 85],
            ['video', 'شرح كان وأخواتها', 0],
            ['assign', 'واجب المبتدأ والخبر', 90],
            ['quiz', 'امتحان النحو الأول', 35],
            ['quiz', 'امتحان النحو الثاني', 70],
            ['quiz', 'امتحان النحو الثالث', null],
        ],
        'محاضرة تأسيس البلاغة | طلاب ثانوية عامة' => [
            ['video', 'مقدمة في البلاغة', 100],
            ['assign', 'واجب التشبيه', 'pending'],
            ['quiz', 'امتحان البلاغة', null],
        ],
        'محاضرة الأدب | طلاب ثانوية عامة' => [],
    ]],
    ['First Term Bundle (Abdelhamed Hamid | English | 3rd year)', [
        'Unit 1 — Grammar' => [
            ['video', 'Present tenses', 40],
            ['video', 'Past tenses', 12],
            ['assign', 'Grammar homework', null],
            ['quiz', 'Unit 1 quiz', 50],
        ],
        'Unit 2 — Reading' => [
            ['video', 'Reading skills', 0],
            ['quiz', 'Reading quiz', 95],
        ],
    ]],
    ['باقة ال 3 شهور ( أحمد رفعت | الجغرافيا | الصف الثالث الثانوي )', []],
];

$day = DAYSECS;
foreach ($courses as $c => [$name, $lectures]) {
    $course = $gen->create_course([
        'fullname' => $name,
        'shortname' => DEMO_CATEGORY . '_' . ($c + 1),
        'category' => $category->id,
        'numsections' => count($lectures),
        'format' => 'topics',
    ]);
    $gen->enrol_user($student->id, $course->id, 'student');

    $s = 0;
    foreach ($lectures as $lecture => $items) {
        $s++;
        course_update_section($course, $DB->get_record('course_sections', ['course' => $course->id, 'section' => $s]),
            ['name' => $lecture]);
        $added = time() - (count($lectures) - $s + 1) * 7 * $day;
        foreach ($items as [$type, $itemname, $result]) {
            add_item($gen, $course, $s, $type, $itemname, $result, (int) $student->id, $added);
        }
    }
    rebuild_course_cache($course->id, true);
    cli_writeln("Course: {$name}");
}

cli_writeln('');
print_logins();

/**
 * One activity in a lecture, with the student's result on it.
 *
 * @param testing_data_generator $gen
 * @param stdClass $course
 * @param int $section
 * @param string $type video | assign | quiz
 * @param string $name
 * @param int|string|null $result see the course list above
 * @param int $userid
 * @param int $added when the activity was added (the lecture's "بدأ في" date)
 */
function add_item($gen, stdClass $course, int $section, string $type, string $name, $result, int $userid, int $added): void {
    global $DB;
    $base = ['course' => $course->id, 'section' => $section, 'name' => $name];
    if ($type === 'video') {
        $mod = $gen->create_module('vimeo', $base);
        if ($result > 0) {
            $cm = get_coursemodule_from_instance('vimeo', $mod->id, $course->id, false, MUST_EXIST);
            \local_nit_videoprogress\progress::record($userid, $cm, 60, 600, range(0, $result - 1));
        }
    } else if ($type === 'assign') {
        $mod = $gen->create_module('assign', $base + ['grade' => 100, 'assignsubmission_onlinetext_enabled' => 1]);
        if ($result === 'pending' || is_int($result)) {
            $DB->insert_record('assign_submission', (object) ['assignment' => $mod->id, 'userid' => $userid,
                'status' => 'submitted', 'latest' => 1, 'attemptnumber' => 0, 'groupid' => 0,
                'timecreated' => time(), 'timemodified' => time()]);
        }
        if (is_int($result)) {
            grade_update('mod/assign', $course->id, 'mod', 'assign', $mod->id, 0,
                ['userid' => $userid, 'rawgrade' => $result]);
        }
    } else {
        $mod = $gen->create_module('quiz', $base + ['grade' => 100]);
        if (is_int($result)) {
            grade_update('mod/quiz', $course->id, 'mod', 'quiz', $mod->id, 0,
                ['userid' => $userid, 'rawgrade' => $result]);
        }
    }
    $DB->set_field('course_modules', 'added', $added, ['id' => $mod->cmid]);
}

/**
 * Print what to type on the dashboard.
 */
function print_logins(): void {
    cli_writeln('Student login: ' . DEMO_USERNAME . ' / ' . DEMO_USERNAME . '@example.com (password: DEMO_PASSWORD in this file)');
    cli_writeln('Parent dashboard: /parent_dashboard');
    cli_writeln('  student phone: ' . DEMO_STUDENT_PHONE);
    cli_writeln('  parent phone:  ' . DEMO_PARENT_PHONE . '  (or the mother phone ' . DEMO_MOTHER_PHONE . ')');
}
