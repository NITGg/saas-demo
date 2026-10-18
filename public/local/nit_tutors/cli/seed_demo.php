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
 * DEV ONLY: teacher accounts in every application state, for trying local_nit_tutors by hand.
 * Refuses to run unless the site address is localhost. Idempotent (re-run resets the states).
 *
 *  - nit_tutor_approved: approved, Super Tutor, intro video, a verified + an unverified
 *    qualification, weekly hours, 2 Flex per lesson, "Languages: English" — listed in the search;
 *  - nit_tutor_pending: complete application waiting for review (review it as admin or as
 *    nit_test_manager of local/nit_finance/cli/seed_test_accounts.php);
 *  - nit_tutor_new: a fresh applicant with an empty draft (fill /local/nit_tutors/application.php).
 *
 * Plus an application field "Languages spoken" (dropdown, shown on the teacher page, a search
 * filter). Students: nit_test_buyer of local/nit_finance/cli/seed_test_accounts.php.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_nit_tutors\local\application;
use local_nit_tutors\local\badge;
use local_nit_tutors\local\fields;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\video;

/** Password of every account below (local development only). */
const TEST_TUTOR_PASSWORD = 'Tut-8xQm2LpV7c-4n';

$host = parse_url($CFG->wwwroot, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
    cli_error('Refusing to create test accounts on ' . $CFG->wwwroot . ' (localhost only).');
}

$admin = (int) get_admin()->id;
$subject = '{mlang ar}اللغة الإنجليزية{mlang}{mlang en}English{mlang}';

// The demo filter field.
$field = $DB->get_record('nit_tutor_field', ['shortname' => 'languages']);
$fieldid = fields::save((object) ['id' => $field ? $field->id : 0, 'shortname' => 'languages',
    'name' => '{mlang ar}اللغات التي يتحدثها{mlang}{mlang en}Languages spoken{mlang}', 'datatype' => 'select',
    'options' => "{mlang ar}العربية{mlang}{mlang en}Arabic{mlang}\n{mlang ar}الإنجليزية{mlang}{mlang en}English{mlang}\n"
        . "{mlang ar}الفرنسية{mlang}{mlang en}French{mlang}",
    'required' => 0, 'public' => 1, 'filter' => 1, 'active' => 1]);
$field = fields::get($fieldid);

/**
 * The account, created or reset to a clean slate.
 *
 * @param string $username
 * @param string $first
 * @param string $last
 * @param string $bio
 * @return int
 */
function nit_tutors_seed_user(string $username, string $first, string $last, string $bio): int {
    global $DB, $CFG;
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$user) {
        $id = user_create_user((object) ['username' => $username, 'password' => TEST_TUTOR_PASSWORD,
            'firstname' => $first, 'lastname' => $last, 'email' => $username . '@example.com', 'auth' => 'manual',
            'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'lang' => 'ar', 'timezone' => 'Africa/Cairo',
            'description' => $bio, 'descriptionformat' => FORMAT_HTML], true, false);
    } else {
        $id = (int) $user->id;
        update_internal_user_password($user, TEST_TUTOR_PASSWORD);
        $DB->set_field('user', 'description', $bio, ['id' => $id]);
    }
    \local_nit_tutors\local\tutor_role::revoke($id);
    foreach (qualifications::list($id) as $q) {
        qualifications::delete($id, (int) $q->id);
    }
    $DB->delete_records('nit_tutor_data', ['userid' => $id]);
    $DB->delete_records('nit_tutor', ['userid' => $id]);
    $DB->delete_records('nit_teacher_subject', ['teacherid' => $id]);
    $DB->delete_records('nit_teacher_hour', ['teacherid' => $id]);
    $DB->delete_records('nit_teacher_profile', ['userid' => $id]);
    return $id;
}

/**
 * Everything a complete application has.
 *
 * @param int $uid
 * @param string $headline
 * @param string $subject
 * @param int $flex
 */
function nit_tutors_seed_profile(int $uid, string $headline, string $subject, int $flex): void {
    global $DB;
    $DB->insert_record('nit_teacher_profile', (object) ['userid' => $uid, 'available' => 1, 'headline' => $headline,
        'lesson_flex' => $flex, 'timemodified' => time()]);
    $DB->insert_record('nit_teacher_subject', (object) ['teacherid' => $uid, 'subject' => $subject]);
    video::save($uid, video::LINK, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', false);
}

// 1. Approved Super Tutor.
$approved = nit_tutors_seed_user('nit_tutor_approved', 'سارة', 'علي',
    'مدرسة لغة إنجليزية بخبرة 8 سنوات في تحضير IELTS والمحادثة.');
nit_tutors_seed_profile($approved, 'مدربة IELTS — خبرة 8 سنوات', $subject, 2);
$DB->insert_records('nit_teacher_hour', [
    (object) ['teacherid' => $approved, 'dayofweek' => 6, 'starttime' => '17:00', 'endtime' => '21:00'],
    (object) ['teacherid' => $approved, 'dayofweek' => 1, 'starttime' => '09:00', 'endtime' => '12:00'],
    (object) ['teacherid' => $approved, 'dayofweek' => 3, 'starttime' => '18:00', 'endtime' => '22:00'],
]);
qualifications::verify(qualifications::save($approved, 0, 'ليسانس آداب — لغة إنجليزية', 'جامعة القاهرة', 2016, 1), true);
qualifications::save($approved, 0, 'CELTA', 'Cambridge', 2019, 2);
fields::save_answer($approved, $field, '{mlang ar}الإنجليزية{mlang}{mlang en}English{mlang}');
application::submit($approved);
application::approve($approved, $admin, '');
badge::set_mode($approved, badge::ON);

// 2. Waiting for review.
$pending = nit_tutors_seed_user('nit_tutor_pending', 'عمر', 'حسن', 'مدرس رياضيات للمرحلة الثانوية، 5 سنوات خبرة.');
nit_tutors_seed_profile($pending, 'مدرس رياضيات ثانوي', '{mlang ar}الرياضيات{mlang}{mlang en}Mathematics{mlang}', 1);
qualifications::save($pending, 0, 'بكالوريوس تربية — رياضيات', 'جامعة عين شمس', 2018, 1);
fields::save_answer($pending, $field, '{mlang ar}العربية{mlang}{mlang en}Arabic{mlang}');
application::submit($pending);

// 3. A fresh applicant.
$new = nit_tutors_seed_user('nit_tutor_new', 'منى', 'إبراهيم', '');
application::ensure($new);

// The ad-hoc demo tutor of the first manual check is replaced by nit_tutor_approved.
if ($old = $DB->get_record('user', ['username' => 'demo.tutor', 'deleted' => 0])) {
    delete_user($old);
}

cli_writeln("Done (password of all three: see TEST_TUTOR_PASSWORD in this file).");
foreach (['nit_tutor_approved' => $approved, 'nit_tutor_pending' => $pending, 'nit_tutor_new' => $new] as $u => $id) {
    cli_writeln(sprintf('  %-20s id %-5d %s', $u, $id, application::status($id)));
}
