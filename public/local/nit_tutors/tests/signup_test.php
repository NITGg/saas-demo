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
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\signup;
use local_nit_tutors\local\teacher_input;
use local_nit_tutors\local\video;

/**
 * "Join as a teacher": the step-by-step sign-up (account + whole application), the teacher's data
 * (teacher_input: read, validate, save with files), the account frame, the settings tab, and the
 * account page's photo upload.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_tutors\local\signup
 * @covers     \local_nit_tutors\local\teacher_input
 * @covers     \local_academy\local\account_photo
 */
final class signup_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('registerauth', '');
        set_config('passwordpolicy', 0);
        set_config('subjects', "English\nMath", 'local_nit_lessons');
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

    /**
     * A complete teacher input (teacher_input::from_request() shape).
     *
     * @param array $override
     * @return array
     */
    private function teacherin(array $override = []): array {
        return $override + [
            'headline' => 'IELTS coach', 'bio' => "Line one\nLine <two>", 'subjects' => ['English'],
            'videotype' => 'link', 'videourl' => 'https://youtu.be/dQw4w9WgXcQ',
            'qualid' => [0, 0], 'qualtitle' => ['BA English', ''], 'qualinstitution' => ['Cairo University', ''],
            'qualyear' => [2015, 0], 'qualdelete' => [], 'field' => [],
        ];
    }

    /**
     * A small real file on disk standing in for an upload.
     *
     * @param string $name
     * @param string $content
     * @return array {name, tmp_name, size}
     */
    private function upload(string $name, string $content = 'pdf'): array {
        $path = make_request_directory() . '/' . $name;
        file_put_contents($path, $content);
        return ['name' => $name, 'tmp_name' => $path, 'size' => strlen($content)];
    }

    /** @return array no files */
    private function nofiles(): array {
        return ['videofile' => null, 'qualfile' => [], 'fieldfile' => []];
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

    public function test_the_step_by_step_signup_sends_the_whole_application(): void {
        $files = $this->nofiles();
        $files['qualfile'][0] = $this->upload('degree.pdf');
        $user = signup::register_teacher($this->values(), 'Secret#123', true, $this->teacherin(), $files);
        $uid = (int) $user->id;

        $this->assertSame(application::PENDING, application::status($uid), 'sent for review straight away');
        $this->assertSame('IELTS coach', (new \local_nit_lessons\service\teacher_service())->profile($uid)['headline']);
        $this->assertSame('pending', application::get($uid)->videostatus);
        $quals = qualifications::list($uid);
        $this->assertCount(1, $quals, 'the empty second row is not saved');
        $this->assertSame('degree.pdf', qualifications::file($uid, (int) $quals[0]->id)->get_filename());
    }

    public function test_the_signup_creates_nothing_while_any_step_is_wrong(): void {
        try {
            signup::register_teacher($this->values(), 'Other#123', true,
                $this->teacherin(['bio' => '', 'subjects' => [], 'videourl' => 'https://example.com/v']), $this->nofiles());
            $this->fail('an incomplete sign-up was accepted');
        } catch (invalid_signup $e) {
            $this->assertEqualsCanonicalizing(['password2', 'bio', 'subjects', 'video'], array_keys($e->errors));
        }
        $this->assertFalse(\core_user::get_user_by_email('sara@example.com'), 'no half-made account');
    }

    public function test_validate_requires_everything_only_when_sending(): void {
        $empty = $this->teacherin(['bio' => '', 'subjects' => [], 'videotype' => '', 'videourl' => '',
            'qualtitle' => [''], 'qualinstitution' => [''], 'qualyear' => [0], 'qualid' => [0]]);
        $this->assertSame([], teacher_input::validate($empty, $this->nofiles(), 0, false), 'a plain save may be partial');
        $this->assertEqualsCanonicalizing(['bio', 'subjects', 'video', 'quals'],
            array_keys(teacher_input::validate($empty, $this->nofiles(), 0, true)));
    }

    public function test_validate_refuses_bad_values_and_files(): void {
        $files = $this->nofiles();
        $files['qualfile'][0] = $this->upload('virus.exe');
        $errors = teacher_input::validate($this->teacherin(['subjects' => ['Astrology'], 'videourl' => 'javascript:alert(1)',
            'qualyear' => [1700, 0]]), $files, 0, false);
        $this->assertArrayHasKey('subjects', $errors);
        $this->assertArrayHasKey('video', $errors);
        $this->assertArrayHasKey('qual0', $errors);

        $errors = teacher_input::validate($this->teacherin(), $files, 0, false);
        $this->assertStringContainsString('.pdf', $errors['qual0'], 'the wrong file type is named');
    }

    public function test_save_stores_every_part_and_ignores_another_teachers_qualification(): void {
        global $DB;
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        teacher_input::save($uid, $this->teacherin(), $this->nofiles());
        $this->assertSame([], application::missing($uid));
        $this->assertStringContainsString('Line &lt;two&gt;', $DB->get_field('user', 'description', ['id' => $uid]));

        $other = application_test::complete_applicant();
        $foreign = (int) qualifications::list($other)[0]->id;
        teacher_input::save($uid, $this->teacherin(['qualid' => [$foreign], 'qualtitle' => ['Hijacked'],
            'qualinstitution' => [''], 'qualyear' => [0]]), $this->nofiles());
        $this->assertSame('BA English', qualifications::list($other)[0]->title);
    }

    public function test_an_uploaded_video_replaces_the_link(): void {
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        teacher_input::save($uid, $this->teacherin(), $this->nofiles());
        $files = $this->nofiles();
        $files['videofile'] = $this->upload('intro.mp4', 'video');
        teacher_input::save($uid, $this->teacherin(['videotype' => 'upload', 'videourl' => '']), $files);
        $this->assertSame('intro.mp4', video::file($uid)->get_filename());
        $this->assertSame(video::UPLOAD, application::get($uid)->videotype);
    }

    public function test_an_approved_teachers_new_qualification_waits_for_review(): void {
        $uid = application_test::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->assertTrue((bool) qualifications::list($uid)[0]->verified, 'approving verifies the qualifications');
        $this->assertFalse(application::has_updates($uid));

        $in = $this->teacherin(['qualid' => [(int) qualifications::list($uid)[0]->id, 0],
            'qualtitle' => ['BA English', 'CELTA'], 'qualinstitution' => ['Cairo University', 'Cambridge'],
            'qualyear' => [2015, 2019], 'videourl' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->assertTrue(teacher_input::save($uid, $in, $this->nofiles()), 'the reviewers are told');
        $this->assertTrue(application::has_updates($uid));
        $this->assertSame(1, application::updates_waiting());

        try {
            application::review_updates($uid, false, '');
            $this->fail('rejected without a reason');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notereq', $e->errorcode);
        }
        application::review_updates($uid, false, 'Attach the CELTA certificate');
        $this->assertSame(application::APPROVED, application::status($uid), 'still approved');
        $this->assertTrue(application::has_updates($uid), 'the new qualification stays unverified');

        application::review_updates($uid, true);
        $this->assertFalse(application::has_updates($uid));
    }

    public function test_the_account_nav_offers_applying_then_the_teacher_profile(): void {
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        $this->setUser($uid);
        $nav = \local_academy\local\account_shell::nav('tutor');
        $this->assertSame('tutor', array_key_last($nav), 'not a teacher yet: "Apply to teach" comes last');
        $this->assertTrue($nav['tutor']['on']);
        $this->assertStringContainsString('/local/nit_tutors/application.php', $nav['tutor']['url']);

        application::ensure($uid);
        $this->assertSame(['profile', 'tutor'], array_slice(array_keys(\local_academy\local\account_shell::nav('profile')), 0, 2),
            'an applicant gets "Teacher profile" right under Profile');
    }

    public function test_the_settings_tab_keeps_values_in_range(): void {
        \local_nit_tutors\form\settings_form::store((object) ['requireapproval' => 0, 'requirevideo' => 0,
            'minqualifications' => 99, 'videomaxmb' => 0, 'allowreapply' => 1]);
        $this->assertFalse(application::approval_required());
        $this->assertSame(10, application::setting_int('minqualifications', 1));
        $this->assertSame(1, application::setting_int('videomaxmb', 200));
        $this->assertSame(0, \local_nit_tutors\form\settings_form::current()->requirevideo);
    }

    public function test_a_teacher_or_student_can_change_their_own_photo(): void {
        global $CFG, $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $png = make_request_directory() . '/me.png';
        $img = imagecreatetruecolor(40, 40);
        imagepng($img, $png);
        $this->assertNull(\local_academy\local\account_photo::save_file((int) $user->id, $png, filesize($png)));
        $this->assertGreaterThan(0, (int) $DB->get_field('user', 'picture', ['id' => $user->id]));

        $txt = make_request_directory() . '/me.png';
        file_put_contents($txt, 'not an image');
        $this->assertNotNull(\local_academy\local\account_photo::save_file((int) $user->id, $txt, 12));

        $CFG->disableuserimages = 1;
        $this->assertNotNull(\local_academy\local\account_photo::save_file((int) $user->id, $png, filesize($png)));
    }
}
