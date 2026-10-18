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

use local_nit_lessons\service\teacher_service;
use local_nit_tutors\local\application;
use local_nit_tutors\local\pending;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\teacher_input;
use local_nit_tutors\local\video;

/**
 * An approved teacher's changes wait for review; the public keeps the approved version.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_tutors\local\pending
 * @covers     \local_nit_tutors\local\teacher_input
 * @covers     \local_nit_tutors\local\application
 */
final class pending_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/application_test.php');
    }

    /**
     * An approved teacher (application_test::complete_applicant()).
     *
     * @return int
     */
    private function approved(): int {
        $uid = application_test::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        return $uid;
    }

    /**
     * The teacher's saved data as the profile form posts it, with changes.
     *
     * @param int $uid
     * @param array $override
     * @return array
     */
    private function in(int $uid, array $override = []): array {
        $q = qualifications::list($uid)[0];
        return $override + [
            'headline' => 'IELTS coach', 'bio' => 'I teach English.', 'subjects' => ['English'],
            'videotype' => video::LINK, 'videourl' => 'https://youtu.be/dQw4w9WgXcQ',
            'qualid' => [(int) $q->id], 'qualtitle' => [$q->title], 'qualinstitution' => [$q->institution],
            'qualyear' => [(int) $q->year], 'qualdelete' => [], 'field' => [],
        ];
    }

    /**
     * No uploads.
     *
     * @return array
     */
    private function nofiles(): array {
        return ['videofile' => null, 'qualfile' => [], 'fieldfile' => []];
    }

    /**
     * A small real file standing in for an upload.
     *
     * @param string $name
     * @return array
     */
    private function upload(string $name): array {
        $path = make_request_directory() . '/' . $name;
        file_put_contents($path, 'video');
        return ['name' => $name, 'tmp_name' => $path, 'size' => filesize($path)];
    }

    public function test_a_change_waits_and_the_public_keeps_the_approved_version(): void {
        $uid = $this->approved();
        $this->assertTrue(teacher_input::save($uid, $this->in($uid, ['headline' => 'Grammar expert', 'bio' => 'New bio',
            'videourl' => 'https://vimeo.com/76979871']), $this->nofiles()));

        $this->assertSame('IELTS coach', (new teacher_service())->profile($uid)['headline']);
        $this->assertStringContainsString('I teach English.', \core_user::get_user($uid)->description);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', video::player($uid)['embed']);
        $this->assertSame('Grammar expert', pending::get($uid)['headline']);
        $this->assertTrue(application::has_updates($uid));
        $this->assertSame(1, application::updates_waiting());

        application::review_updates($uid, true);
        $this->assertNull(pending::get($uid));
        $this->assertSame('Grammar expert', (new teacher_service())->profile($uid)['headline']);
        $this->assertStringContainsString('New bio', \core_user::get_user($uid)->description);
        $this->assertSame('https://player.vimeo.com/video/76979871', video::player($uid)['embed'],
            'the approved video is shown at once');
        $this->assertFalse(application::has_updates($uid));
    }

    public function test_a_refused_change_is_thrown_away(): void {
        $uid = $this->approved();
        $files = $this->nofiles();
        $files['videofile'] = $this->upload('intro.mp4');
        teacher_input::save($uid, $this->in($uid, ['videotype' => video::UPLOAD, 'videourl' => '']), $files);
        $this->assertNotNull(pending::file($uid, pending::AREA_VIDEO, 0));
        $this->assertNull(video::file($uid), 'the upload is not live');

        application::review_updates($uid, false, 'Too dark');
        $this->assertNull(pending::get($uid));
        $this->assertNull(pending::file($uid, pending::AREA_VIDEO, 0));
        $this->assertSame(video::LINK, application::get($uid)->videotype);
        $this->assertFalse(application::has_updates($uid));
    }

    public function test_an_approved_upload_goes_live(): void {
        $uid = $this->approved();
        $files = $this->nofiles();
        $files['videofile'] = $this->upload('intro.mp4');
        $files['qualfile'][0] = $this->upload('ba.pdf');
        teacher_input::save($uid, $this->in($uid, ['videotype' => video::UPLOAD, 'videourl' => '']), $files);

        application::review_updates($uid, true);
        $this->assertSame('intro.mp4', video::file($uid)->get_filename());
        $this->assertSame('approved', application::get($uid)->videostatus);
        $this->assertNotNull(video::player($uid));
        $q = qualifications::list($uid)[0];
        $this->assertSame('ba.pdf', qualifications::file($uid, (int) $q->id)->get_filename());
        $this->assertSame(1, (int) $q->verified);
    }

    public function test_saving_without_a_change_sends_nothing_and_a_revert_withdraws_the_change(): void {
        $uid = $this->approved();
        $this->assertFalse(teacher_input::save($uid, $this->in($uid), $this->nofiles()));
        $this->assertNull(pending::get($uid));

        teacher_input::save($uid, $this->in($uid, ['subjects' => []]), $this->nofiles());
        $this->assertNotNull(pending::get($uid));
        $this->assertFalse(teacher_input::save($uid, $this->in($uid), $this->nofiles()));
        $this->assertNull(pending::get($uid), 'back to the approved version: nothing to review');
    }

    public function test_an_applicant_saves_live(): void {
        $uid = application_test::complete_applicant();
        teacher_input::save($uid, $this->in($uid, ['headline' => 'Grammar expert']), $this->nofiles());
        $this->assertNull(pending::get($uid));
        $this->assertSame('Grammar expert', (new teacher_service())->profile($uid)['headline']);
    }

    public function test_suspending_keeps_a_waiting_change_as_their_data(): void {
        $uid = $this->approved();
        teacher_input::save($uid, $this->in($uid, ['headline' => 'Grammar expert']), $this->nofiles());
        application::suspend($uid, (int) get_admin()->id, 'Paused');
        $this->assertNull(pending::get($uid));
        $this->assertSame('Grammar expert', (new teacher_service())->profile($uid)['headline']);
    }

    public function test_the_tab_counts_leave_out_deleted_accounts(): void {
        $kept = $this->approved();
        $gone = $this->approved();
        teacher_input::save($gone, $this->in($gone, ['headline' => 'x']), $this->nofiles());
        delete_user(\core_user::get_user($gone));
        $this->assertSame(1, application::counts()[application::APPROVED]);
        $this->assertSame(0, application::updates_waiting());
        $this->assertSame(1, application::search(application::APPROVED, '')['total']);
        $this->assertSame($kept, (int) application::search(application::APPROVED, '')['rows'][0]->userid);
    }
}
