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

use local_academy\local\academic_structure;
use local_academy\local\invalid_registration;
use local_academy\local\registration;
use local_academy\local\user_fields;

/**
 * Tests for student self-registration (web register.php + mobile register_student).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\registration
 */
final class registration_test extends \advanced_testcase {

    /**
     * A complete, valid submission.
     *
     * @return array
     */
    private function valid(): array {
        $this->getDataGenerator()->create_category(['name' => 'Year 1']);
        user_fields::ensure();
        $map = academic_structure::map();
        $system = array_key_first($map);
        $years = academic_structure::years();
        return [
            'firstname' => 'Mona', 'secondname' => 'Ali', 'thirdname' => 'Hassan', 'lastname' => 'Saleh',
            'phone' => '01011112222', 'grade' => $years[0]['key'], 'national' => '30101010101010',
            'fatherphone' => '01033334444', 'motherphone' => '01055556666', 'school' => 'Test school',
            'guardianjob' => 'Engineer', 'studysystem' => $system, 'division' => $map[$system][0],
            'governorate' => user_fields::menu_options('governorate')[0],
            'religion' => user_fields::menu_options('religion')[0],
            'gender' => user_fields::menu_options('gender')[0],
            'email' => 'Mona.Saleh@Example.com', 'password' => 'Test@1234',
        ];
    }

    public function test_register_saves_every_field_and_waits_for_email_confirmation(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('registerauth', 'email');
        $data = $this->valid();
        $sink = $this->redirectEmails();

        $user = registration::register($data, true);

        $this->assertSame('mona.saleh@example.com', $user->email);
        $this->assertSame('mona.saleh@example.com', $user->username);
        $this->assertEquals(0, $user->confirmed); // Moodle's email self-registration: not active yet.
        $this->assertEquals(0, $user->suspended);
        $this->assertSame('01011112222', $user->phone1);
        $this->assertTrue(validate_internal_user_password($user, 'Test@1234'));

        $values = user_fields::values((int) $user->id);
        $this->assertSame('30101010101010', $values['nationalid']);
        $this->assertSame($data['division'], $values[academic_structure::USER_DIVISION]);
        $this->assertSame($data['grade'], $values[academic_structure::USER_YEAR]);
        $this->assertSame('Engineer', $values['guardianjob']);
        $this->assertTrue($DB->record_exists('user', ['id' => $user->id, 'auth' => 'email']));

        // Moodle's confirmation email went out; its link (back to the home page) activates the account.
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('mona.saleh@example.com', $messages[0]->to);
        $body = quoted_printable_decode($messages[0]->body);
        $this->assertStringContainsString('/login/confirm.php', $body);
        $this->assertStringContainsString('data=' . $user->secret . '/', $body);
        $this->assertSame(AUTH_CONFIRM_OK, get_auth_plugin('email')->user_confirm($user->username, $user->secret));
        $this->assertEquals(1, $DB->get_field('user', 'confirmed', ['id' => $user->id]));
    }

    public function test_a_wrong_secret_does_not_confirm(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('registerauth', 'email');
        $this->redirectEmails();
        $user = registration::register($this->valid(), true);
        $this->assertNotSame(AUTH_CONFIRM_OK, get_auth_plugin('email')->user_confirm($user->username, 'wrongsecret'));
        $this->assertEquals(0, $DB->get_field('user', 'confirmed', ['id' => $user->id]));
    }

    public function test_validation_reports_each_bad_field(): void {
        $this->resetAfterTest();
        set_config('registerauth', 'email');
        $existing = $this->getDataGenerator()->create_user(['email' => 'taken@example.com']);
        $data = array_merge($this->valid(), [
            'phone' => 'abc', 'national' => '123', 'division' => 'not a division', 'school' => '',
            'email' => 'TAKEN@example.com', 'password' => 'weak',
        ]);
        try {
            registration::register($data, false);
            $this->fail('invalid registration accepted');
        } catch (invalid_registration $e) {
            $this->assertEqualsCanonicalizing(
                ['phone', 'national', 'division', 'school', 'email', 'password', 'agree'], array_keys($e->errors));
            $this->assertSame(get_string('reg_emailtaken', 'local_academy'), $e->errors['email']);
            $this->assertStringNotContainsString('<div>', $e->errors['password']);
        }
        $this->assertSame(1, \core_user::get_user_by_email('taken@example.com') ? 1 : 0);
        $this->assertNotEmpty($existing->id);
    }

    public function test_division_must_belong_to_the_study_system(): void {
        $this->resetAfterTest();
        set_config('registerauth', 'email');
        $data = $this->valid();
        $map = academic_structure::map();
        $systems = array_keys($map);
        $foreign = array_values(array_diff($map[$systems[1]], $map[$systems[0]]));
        $data['studysystem'] = $systems[0];
        $data['division'] = $foreign[0];
        $errors = registration::validate(registration::clean($data), true);
        $this->assertSame(['division'], array_keys($errors));
    }

    public function test_registration_does_not_need_moodle_self_registration(): void {
        $this->resetAfterTest();
        set_config('registerauth', ''); // Moodle's self registration off (as on production).
        $this->assertTrue(registration::enabled());
        $user = registration::register($this->valid(), true);
        $this->assertTrue(is_enabled_auth($user->auth));
        $this->assertEquals(1, $user->confirmed);
    }

    public function test_auth_method_falls_back_to_manual(): void {
        $this->resetAfterTest();
        set_config('registerauth', '');
        set_config('auth', ''); // Only manual + nologin remain enabled.
        $this->assertSame('manual', registration::auth_method());
        set_config('auth', 'email');
        $this->assertSame('email', registration::auth_method());
    }

    public function test_closed_registration_is_refused(): void {
        $this->resetAfterTest();
        set_config('registration', 0, 'local_academy');
        $this->assertFalse(registration::enabled());
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_registrationdisabled', 'local_academy'));
        registration::register($this->valid(), true);
    }

    public function test_arabic_digits_are_accepted(): void {
        $this->resetAfterTest();
        $clean = registration::clean(['phone' => '٠١٠١١١١٢٢٢٢', 'national' => '٣٠١٠١٠١٠١٠١٠١٠']);
        $this->assertSame('01011112222', $clean['phone']);
        $this->assertSame('30101010101010', $clean['national']);
    }

    public function test_username_is_unique(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['username' => 'a@example.com']);
        $this->assertSame('a@example.com_2', registration::unique_username('a@example.com'));
        $this->assertSame('b@example.com', registration::unique_username('b@example.com'));
    }

    public function test_throttle_stops_mass_signups_from_one_address(): void {
        $this->resetAfterTest();
        for ($i = 0; $i < registration::MAX_PER_HOUR; $i++) {
            registration::throttle();
        }
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_toomanyregistrations', 'local_academy'));
        registration::throttle();
    }
}
