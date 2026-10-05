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
 * DEV ONLY: create the local test student (and a test manager for the admin
 * pages) used to try wallets, lesson
 * purchases, codes and video progress by hand. Never run on production.
 *
 *   php admin/cli/... local/nit_finance/cli/seed_test_accounts.php [--courseid=N]
 *
 * Idempotent. With --courseid the student is also enrolled in that course.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

/** Test student login (local development only). */
const TEST_STUDENT_USERNAME = 'nit_test_buyer';
const TEST_STUDENT_PASSWORD = 'Nit-4G5VdloZRH-7a';
/** Test manager login (local development only): system Manager role, for the admin pages. */
const TEST_MANAGER_USERNAME = 'nit_test_manager';
const TEST_MANAGER_PASSWORD = 'Mgr-V3uhdR39vh-4q';

[$options] = cli_get_params(['courseid' => 0, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Creates the dev test student. --courseid=N also enrols them in course N.');
    exit(0);
}

$user = $DB->get_record('user', ['username' => TEST_STUDENT_USERNAME, 'deleted' => 0]);
if (!$user) {
    $id = user_create_user((object) [
        'username' => TEST_STUDENT_USERNAME,
        'password' => TEST_STUDENT_PASSWORD,
        'firstname' => 'طالب',
        'lastname' => 'تجريبي',
        'email' => TEST_STUDENT_USERNAME . '@example.com',
        'phone1' => '01000000099',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'ar',
    ]);
    $user = $DB->get_record('user', ['id' => $id]);
    cli_writeln('Created ' . TEST_STUDENT_USERNAME . " (id {$user->id})");
} else {
    cli_writeln(TEST_STUDENT_USERNAME . " exists (id {$user->id})");
}
// The guardian phone the parent dashboard (local/parent/index.php) checks:
// child 01000000099 + parent 01100000088 opens this student's report.
require_once($CFG->dirroot . '/user/profile/lib.php');
profile_save_custom_fields($user->id, ['parentphone' => '01100000088']);
cli_writeln('Parent phone 01100000088');

if ($options['courseid']) {
    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $options['courseid'], 'enrol' => 'manual']);
    if (!$instance) {
        $course = get_course($options['courseid']);
        $instance = $DB->get_record('enrol', ['id' => $enrol->add_default_instance($course)]);
    }
    $enrol->enrol_user($instance, $user->id, $DB->get_field('role', 'id', ['shortname' => 'student']));
    cli_writeln("Enrolled in course {$options['courseid']}");
}

$manager = $DB->get_record('user', ['username' => TEST_MANAGER_USERNAME, 'deleted' => 0]);
if (!$manager) {
    $id = user_create_user((object) [
        'username' => TEST_MANAGER_USERNAME,
        'password' => TEST_MANAGER_PASSWORD,
        'firstname' => 'مدير',
        'lastname' => 'تجريبي',
        'email' => TEST_MANAGER_USERNAME . '@example.com',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'ar',
    ]);
    $manager = $DB->get_record('user', ['id' => $id]);
    role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $manager->id, context_system::instance());
    cli_writeln('Created ' . TEST_MANAGER_USERNAME . " (id {$manager->id}, system manager)");
} else {
    cli_writeln(TEST_MANAGER_USERNAME . " exists (id {$manager->id})");
}
