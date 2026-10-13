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

namespace local_academy;

use local_academy\local\forgot_password;
use local_academy\task\password_reset_email;

/**
 * Password-reset requests do the same work for every email (BUG-020): the email
 * is sent by a background task, so the answer time does not reveal accounts.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\password_reset_manager
 * @covers     \local_academy\local\forgot_password
 * @covers     \local_academy\task\password_reset_email
 */
final class password_reset_test extends \advanced_testcase {

    public function test_app_code_request_is_the_same_for_known_and_unknown_emails(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'mona@example.com']);
        $sink = $this->redirectEmails();

        $known = password_reset_manager::request_otp('Mona@example.com');
        $unknown = password_reset_manager::request_otp('nobody@example.com');

        $this->assertSame($known, $unknown);
        $this->assertSame(0, $sink->count(), 'No email is sent while the visitor waits.');
        $this->assertCount(2, \core\task\manager::get_adhoc_tasks(password_reset_email::class));

        $this->runAdhocTasks(password_reset_email::class);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('mona@example.com', $messages[0]->to);
        $this->assertTrue($DB->record_exists('academy_password_otps', ['userid' => $user->id, 'verified' => 0]));
        $this->assertSame(1, $DB->count_records('academy_password_otps'));
    }

    public function test_app_code_request_limit_counts_unknown_emails_too(): void {
        // Before, only registered emails hit the limit — "too many requests" itself gave them away.
        $this->resetAfterTest();
        for ($i = 0; $i < password_reset_manager::REQUEST_MAX; $i++) {
            password_reset_manager::request_otp('nobody@example.com');
        }
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_toomanyrequests', 'local_academy'));
        password_reset_manager::request_otp('nobody@example.com');
    }

    public function test_website_request_is_taken_only_when_the_form_would_accept_it(): void {
        $this->resetAfterTest();
        set_config('protectusernames', 1);
        $this->assertSame(['', 'a@example.com'], forgot_password::request('', ' a@example.com '));
        $this->assertSame(['student1', ''], forgot_password::request('student1', ''));
        $this->assertNull(forgot_password::request('student1', 'a@example.com')); // Both: Moodle's error.
        $this->assertNull(forgot_password::request('', ''));
        $this->assertNull(forgot_password::request('', 'not an email'));

        // Moodle says whether the account exists anyway: nothing to hide.
        set_config('protectusernames', 0);
        $this->assertNull(forgot_password::request('', 'a@example.com'));
    }

    public function test_website_task_sends_moodles_reset_link_to_existing_accounts_only(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'mona@example.com']);
        $unconfirmed = $this->getDataGenerator()->create_user(['email' => 'new@example.com', 'confirmed' => 0]);
        $sink = $this->redirectEmails();

        password_reset_email::queue('link', '', 'mona@example.com');
        password_reset_email::queue('link', '', 'nobody@example.com');
        password_reset_email::queue('link', '', 'new@example.com');
        $this->runAdhocTasks(password_reset_email::class);

        $messages = $sink->get_messages();
        $this->assertEqualsCanonicalizing(['mona@example.com', 'new@example.com'], array_column($messages, 'to'));
        $this->assertTrue($DB->record_exists('user_password_resets', ['userid' => $user->id]));
        // The unconfirmed account gets the confirmation email again, as on Moodle's own form.
        $this->assertFalse($DB->record_exists('user_password_resets', ['userid' => $unconfirmed->id]));
    }
}
