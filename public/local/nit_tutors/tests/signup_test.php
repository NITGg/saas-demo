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

namespace local_nit_tutors;

use local_nit_tutors\local\application;
use local_nit_tutors\local\invalid_signup;
use local_nit_tutors\local\profile_editor;
use local_nit_tutors\local\signup;

/**
 * "Join as a teacher": the account + draft application, and the application form's save.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_tutors\local\signup
 * @covers     \local_nit_tutors\local\profile_editor
 */
final class signup_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('registerauth', '');
        set_config('passwordpolicy', 0);
        require_once(__DIR__ . '/application_test.php');
    }

    /**
     * Valid sign-up values.
     *
     * @return array
     */
    private function values(): array {
        return ['firstname' => 'Sara', 'lastname' => 'Ali', 'email' => 'Sara@Example.com', 'phone' => '٠١٠ 1234-5678',
            'password' => 'Secret#123'];
    }

    public function test_register_creates_an_account_and_a_draft_application(): void {
        $user = signup::register($this->values(), true);
        $this->assertSame('sara@example.com', $user->email);
        $this->assertSame('01012345678', $user->phone1);
        $this->assertSame(1, (int) $user->confirmed);
        $this->assertSame(application::DRAFT, application::status((int) $user->id));
        $this->assertFalse((new \local_nit_lessons\service\teacher_service())->is_teacher((int) $user->id),
            'an applicant is not a teacher until approved');
    }

    public function test_register_refuses_bad_values(): void {
        $this->getDataGenerator()->create_user(['email' => 'taken@example.com']);
        try {
            signup::register(['email' => 'taken@example.com', 'phone' => '12', 'firstname' => '', 'lastname' => 'X',
                'password' => 'x'] + $this->values(), false);
            $this->fail('invalid values were accepted');
        } catch (invalid_signup $e) {
            $this->assertEqualsCanonicalizing(['firstname', 'email', 'phone', 'agree'], array_keys($e->errors));
        }
        $this->assertFalse(\core_user::get_user_by_email('sara@example.com'));
    }

    public function test_register_is_closed_with_the_academy_registration(): void {
        set_config('registration', 0, 'local_academy');
        $this->expectException(\moodle_exception::class);
        signup::register($this->values(), true);
    }

    public function test_the_form_save_stores_every_part(): void {
        global $DB;
        set_config('subjects', "English\nMath", 'local_nit_lessons');
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        $this->setUser($uid);
        $data = (object) [
            'headline' => 'IELTS coach',
            'bio' => "Line one\nLine <two>",
            'subjects' => ['English'],
            'videotype' => 'link',
            'videourl' => 'https://youtu.be/dQw4w9WgXcQ',
            'qualid' => [0, 0],
            'qualtitle' => ['BA English', ''],
            'qualinstitution' => ['Cairo University', ''],
            'qualyear' => [2015, 0],
            'qualdelete' => [0, 0],
        ];
        profile_editor::save($uid, $data);

        $this->assertSame([], application::missing($uid));
        $this->assertSame('IELTS coach', (new \local_nit_lessons\service\teacher_service())->profile($uid)['headline']);
        $this->assertStringContainsString('Line &lt;two&gt;', $DB->get_field('user', 'description', ['id' => $uid]));
        $this->assertSame('pending', application::get($uid)->videostatus);
        $this->assertCount(1, \local_nit_tutors\local\qualifications::list($uid), 'an empty row is not saved');

        // A forged qualification id of another teacher is ignored.
        $other = application_test::complete_applicant();
        $foreign = (int) \local_nit_tutors\local\qualifications::list($other)[0]->id;
        $data->qualid = [$foreign];
        $data->qualtitle = ['Hijacked'];
        $data->qualinstitution = [''];
        $data->qualyear = [0];
        $data->qualdelete = [0];
        profile_editor::save($uid, $data);
        $this->assertSame('BA English', \local_nit_tutors\local\qualifications::list($other)[0]->title);
    }
}
