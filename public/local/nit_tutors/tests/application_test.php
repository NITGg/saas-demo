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
use local_nit_tutors\local\badge;
use local_nit_tutors\local\fields;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\tutor_role;
use local_nit_tutors\local\video;

/**
 * Teacher applications: what blocks submitting, the review decisions and the Tutor role, the
 * intro video review, qualifications, application fields, the Super Tutor badge, the seat limit.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_tutors\local\application
 * @covers     \local_nit_tutors\local\video
 * @covers     \local_nit_tutors\local\qualifications
 * @covers     \local_nit_tutors\local\fields
 * @covers     \local_nit_tutors\local\badge
 * @covers     \local_nit_tutors\local\tutor_role
 */
final class application_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A user with everything an application needs: bio, a subject, a video link, a qualification.
     *
     * @return int
     */
    public static function complete_applicant(): int {
        global $DB;
        $user = \advanced_testcase::getDataGenerator()->create_user(['description' => 'I teach English.']);
        $uid = (int) $user->id;
        $DB->insert_record('nit_teacher_profile', (object) ['userid' => $uid, 'available' => 1, 'headline' => 'IELTS coach',
            'lesson_flex' => 2, 'timemodified' => time()]);
        $DB->insert_record('nit_teacher_subject', (object) ['teacherid' => $uid, 'subject' => 'English']);
        video::save($uid, video::LINK, 'https://youtu.be/dQw4w9WgXcQ', false);
        qualifications::save($uid, 0, 'BA English', 'Cairo University', 2015, 1);
        return $uid;
    }

    public function test_an_incomplete_application_cannot_be_sent(): void {
        $uid = (int) $this->getDataGenerator()->create_user(['description' => ''])->id;
        $missing = application::missing($uid);
        $this->assertEqualsCanonicalizing(['bio', 'subjects', 'video', 'qualifications'], array_keys($missing));
        try {
            application::submit($uid);
            $this->fail('an incomplete application was sent');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_incomplete', $e->errorcode);
        }
        $this->assertSame(application::DRAFT, application::status($uid));
    }

    public function test_the_admin_rules_change_what_is_required(): void {
        $uid = (int) $this->getDataGenerator()->create_user(['description' => 'Bio'])->id;
        set_config('requirevideo', 0, 'local_nit_tutors');
        set_config('minqualifications', 0, 'local_nit_tutors');
        $this->assertSame(['subjects'], array_keys(application::missing($uid)));
    }

    public function test_a_required_field_blocks_until_answered(): void {
        $uid = self::complete_applicant();
        $fieldid = fields::save((object) ['shortname' => 'langs', 'name' => 'Languages', 'datatype' => 'select',
            'options' => "Arabic\nEnglish", 'required' => 1, 'filter' => 1]);
        $this->assertArrayHasKey('field' . $fieldid, application::missing($uid));
        fields::save_answer($uid, fields::get($fieldid), 'English');
        $this->assertSame([], application::missing($uid));
    }

    public function test_a_select_answer_must_be_one_of_the_options(): void {
        $fieldid = fields::save((object) ['shortname' => 'country', 'name' => 'Country', 'datatype' => 'select',
            'options' => "Egypt\nSaudi Arabia"]);
        $this->expectException(\moodle_exception::class);
        fields::save_answer((int) $this->getDataGenerator()->create_user()->id, fields::get($fieldid), 'Mars');
    }

    public function test_submit_then_approve_gives_the_tutor_role_and_makes_a_teacher(): void {
        $uid = self::complete_applicant();
        $reviewer = (int) get_admin()->id;
        $service = new teacher_service();
        $this->assertFalse($service->is_teacher($uid));

        application::submit($uid);
        $this->assertSame(application::PENDING, application::status($uid));
        $this->assertFalse(application::editable($uid), 'a pending application must wait for the reviewer');

        application::approve($uid, $reviewer, '');
        $this->assertSame(application::APPROVED, application::status($uid));
        $this->assertTrue(tutor_role::holds($uid));
        $this->assertTrue($service->is_teacher($uid));
        $this->assertTrue(\local_academy\teacher_manager::is_teacher($uid));
        $this->assertTrue($service->bookable($uid));
        // The video sent with the application is approved with it.
        $this->assertSame('approved', application::get($uid)->videostatus);
    }

    public function test_the_tutor_role_is_site_level_and_does_not_reach_courses(): void {
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $this->assertFalse(has_capability('moodle/course:update', $coursecontext, $uid));
        $this->assertFalse(has_capability('moodle/grade:viewall', $coursecontext, $uid));
    }

    public function test_reject_needs_a_reason_and_takes_the_role_away(): void {
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        try {
            application::reject($uid, (int) get_admin()->id, '  ');
            $this->fail('rejected without a reason');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notereq', $e->errorcode);
        }
        $this->assertTrue(tutor_role::holds($uid));

        application::suspend($uid, (int) get_admin()->id, 'Too many no-shows');
        $this->assertFalse(tutor_role::holds($uid));
        $this->assertFalse((new teacher_service())->is_teacher($uid));
        $this->assertSame('Too many no-shows', application::get($uid)->reviewnote);
    }

    public function test_reapplying_after_a_rejection_follows_the_setting(): void {
        $uid = self::complete_applicant();
        application::submit($uid);
        application::reject($uid, (int) get_admin()->id, 'Not now');
        set_config('allowreapply', 0, 'local_nit_tutors');
        try {
            application::submit($uid);
            $this->fail('reapplied while reapplying is off');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_noreapply', $e->errorcode);
        }
        set_config('allowreapply', 1, 'local_nit_tutors');
        application::submit($uid);
        $this->assertSame(application::PENDING, application::status($uid));
    }

    public function test_a_course_teacher_is_not_bookable_until_approved(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;
        $this->getDataGenerator()->enrol_user($uid, $this->getDataGenerator()->create_course()->id, 'editingteacher');
        $DB->insert_record('nit_teacher_profile', (object) ['userid' => $uid, 'available' => 1, 'lesson_flex' => 1]);
        $DB->insert_record('nit_teacher_subject', (object) ['teacherid' => $uid, 'subject' => 'Math']);
        $service = new teacher_service();

        $this->assertTrue($service->is_teacher($uid));
        $this->assertFalse($service->bookable($uid));
        $this->assertSame([], array_column($service->browse(), 'id'));

        set_config('requireapproval', 0, 'local_nit_tutors');
        $this->assertTrue($service->bookable($uid));
        $this->assertSame([$uid], array_column($service->browse(), 'id'));
    }

    public function test_an_admin_is_not_made_a_teacher_by_the_capability(): void {
        $this->assertFalse((new teacher_service())->is_teacher((int) get_admin()->id));
    }

    public function test_a_new_video_after_approval_waits_for_review(): void {
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->assertNotNull(video::player($uid));

        video::save($uid, video::LINK, 'https://vimeo.com/76979871', false);
        $this->assertSame('pending', application::get($uid)->videostatus);
        $this->assertNull(video::player($uid), 'visitors must not see a video nobody approved');
        $this->assertNotNull(video::player($uid, false), 'the reviewer sees it');
        $this->assertSame(1, application::updates_waiting());

        video::review($uid, true);
        $this->assertSame('https://player.vimeo.com/video/76979871', video::player($uid)['embed']);
    }

    public function test_saving_the_same_video_keeps_its_approval(): void {
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        video::save($uid, video::LINK, 'https://youtu.be/dQw4w9WgXcQ', false);
        $this->assertSame('approved', application::get($uid)->videostatus);
    }

    /**
     * Video links.
     *
     * @return array
     */
    public static function links(): array {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'vimeo private' => ['https://vimeo.com/123456/abcdef12', 'https://player.vimeo.com/video/123456?h=abcdef12'],
            'vimeo player' => ['https://player.vimeo.com/video/123456', 'https://player.vimeo.com/video/123456'],
            'other site' => ['https://evil.example.com/watch?v=dQw4w9WgXcQ', null],
            'javascript' => ['javascript:alert(1)', null],
            'empty' => ['', null],
        ];
    }

    /**
     * @dataProvider links
     * @param string $url
     * @param string|null $embed
     */
    public function test_only_youtube_and_vimeo_links_embed(string $url, ?string $embed): void {
        $this->assertSame($embed, video::embed_url($url));
    }

    public function test_a_bad_link_is_refused(): void {
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        $this->expectException(\moodle_exception::class);
        video::save($uid, video::LINK, 'https://example.com/v.mp4', false);
    }

    public function test_changing_a_verified_qualification_clears_verified(): void {
        $uid = self::complete_applicant();
        $id = (int) qualifications::list($uid)[0]->id;
        qualifications::verify($id, true);
        qualifications::save($uid, $id, 'BA English', 'Cairo University', 2015, 1);
        $this->assertSame(1, (int) qualifications::list($uid)[0]->verified, 'an unchanged save keeps it');
        qualifications::save($uid, $id, 'MA English', 'Cairo University', 2015, 1);
        $this->assertSame(0, (int) qualifications::list($uid)[0]->verified);
    }

    public function test_a_qualification_of_someone_else_cannot_be_edited(): void {
        $owner = self::complete_applicant();
        $id = (int) qualifications::list($owner)[0]->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;
        try {
            qualifications::save($other, $id, 'Hijacked', '', 0, 1);
            $this->fail('edited another teacher\'s qualification');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame('BA English', qualifications::list($owner)[0]->title);
        }
    }

    /**
     * Seed completed / no-show lessons and ratings for a teacher.
     *
     * @param int $teacherid
     * @param int $completed
     * @param int $absent
     * @param int[] $ratings
     */
    private function history(int $teacherid, int $completed, int $absent, array $ratings): void {
        global $DB;
        $student = (int) $this->getDataGenerator()->create_user()->id;
        foreach (array_merge(array_fill(0, $completed, 'completed'), array_fill(0, $absent, 'teacher_absent')) as $status) {
            $DB->insert_record('nit_lesson', (object) ['studentid' => $student, 'teacherid' => $teacherid,
                'subject' => 'English', 'status' => $status, 'timecreated' => time()]);
        }
        foreach ($ratings as $rating) {
            $DB->insert_record('local_nit_reviews', (object) ['courseid' => 0, 'teacherid' => $teacherid,
                'userid' => (int) $this->getDataGenerator()->create_user()->id, 'rating' => $rating, 'status' => 1,
                'timecreated' => time(), 'timemodified' => time()]);
        }
        // Rows written straight to the table skip local_nit_reviews\api's per-request cache of the
        // averages (its own save() clears it); drop it as a new request would start without it.
        foreach (['aggcache', 'teacheraggcache'] as $name) {
            $cache = new \ReflectionProperty(\local_nit_reviews\api::class, $name);
            $cache->setValue(null, []);
        }
    }

    /**
     * Ids of the badges the teacher wears.
     *
     * @param int $uid
     * @return int[]
     */
    private function wears(int $uid): array {
        return array_map(fn($b) => (int) $b->id, badge::held($uid));
    }

    /**
     * An automatic badge with these rules (0 / 100 = not checked).
     *
     * @param array $rules
     * @return int
     */
    private function autobadge(array $rules): int {
        return badge::save((object) (['name' => 'Rules ' . random_string(4), 'method' => badge::METHOD_AUTO] + $rules));
    }

    public function test_a_new_site_starts_with_the_super_tutor_badge(): void {
        $all = array_values(badge::all());
        $this->assertCount(1, $all);
        $this->assertStringContainsString('Super Tutor', $all[0]->name);
        $this->assertEquals(4.8, (float) $all[0]->minrating);
        $this->assertSame((int) $all[0]->id, badge::ensure_default(), 'not created twice');
    }

    public function test_an_automatic_badge_follows_its_rules_and_is_lost_when_they_stop_holding(): void {
        $id = $this->autobadge(['minrating' => 4.5, 'minreviews' => 3, 'minlessons' => 5, 'maxabsence' => 10]);
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->history($uid, 5, 0, [5, 5, 4]);
        badge::refresh($uid);
        $this->assertContains($id, $this->wears($uid));

        $this->history($uid, 0, 2, []); // 2 no-shows in 7 lessons = 28.6% > 10%.
        badge::refresh_all();
        $this->assertNotContains($id, $this->wears($uid));
    }

    public function test_too_few_ratings_earn_no_badge(): void {
        $id = $this->autobadge(['minrating' => 4, 'minreviews' => 3]);
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->history($uid, 0, 0, [5, 5]);
        badge::refresh($uid);
        $this->assertNotContains($id, $this->wears($uid));
    }

    public function test_a_badge_without_rules_goes_to_every_approved_teacher(): void {
        $id = $this->autobadge([]);
        $uid = self::complete_applicant();
        $this->assertNotContains($id, $this->wears($uid));
        application::approve($uid, (int) get_admin()->id);
        $this->assertContains($id, $this->wears($uid), 'approving refreshes the badges');
    }

    public function test_a_manual_badge_is_only_given_by_hand(): void {
        $id = badge::save((object) ['name' => 'Verified', 'method' => badge::METHOD_MANUAL]);
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->assertNotContains($id, $this->wears($uid));
        badge::set_mode($uid, $id, badge::ON);
        $this->assertContains($id, $this->wears($uid));
    }

    public function test_the_reviewer_can_pin_a_badge_but_not_on_an_unapproved_teacher(): void {
        $id = $this->autobadge(['minlessons' => 1000]);
        $uid = self::complete_applicant();
        badge::set_mode($uid, $id, badge::ON);
        $this->assertNotContains($id, $this->wears($uid), 'only an approved teacher wears it');
        application::approve($uid, (int) get_admin()->id);
        $this->assertContains($id, $this->wears($uid));
        badge::set_mode($uid, $id, badge::OFF);
        $this->assertNotContains($id, $this->wears($uid));
        application::suspend($uid, (int) get_admin()->id, 'x');
        badge::set_mode($uid, $id, badge::ON);
        $this->assertNotContains($id, $this->wears($uid), 'a suspended teacher wears none');
    }

    public function test_an_inactive_or_deleted_badge_is_not_shown(): void {
        $id = $this->autobadge([]);
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->assertContains($id, $this->wears($uid));
        badge::save((object) (['active' => 0] + (array) badge::get($id)));
        $this->assertNotContains($id, $this->wears($uid));
        badge::delete($id);
        $this->assertNull(badge::get($id));
    }

    public function test_badges_keep_the_admins_order(): void {
        $a = $this->autobadge([]);
        $b = $this->autobadge([]);
        $uid = self::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $before = $this->wears($uid);
        $this->assertLessThan(array_search($b, $before), array_search($a, $before));
        badge::move($b, -1);
        $after = $this->wears($uid);
        $this->assertLessThan(array_search($a, $after), array_search($b, $after));
    }

    public function test_approving_past_the_plan_teacher_limit_is_refused(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license'); // One teacher seat.
        if (\local_license\license::max_teachers() !== 1) {
            $this->markTestSkipped('local_license tier settings differ on this site');
        }
        $first = self::complete_applicant();
        application::approve($first, (int) get_admin()->id);
        $this->assertSame(1, \local_license\enforcer::count_teachers(), 'the tutor takes a teacher seat');

        $second = self::complete_applicant();
        try {
            application::approve($second, (int) get_admin()->id);
            $this->fail('approved past the plan limit');
        } catch (\moodle_exception $e) {
            $this->assertSame('limit_teacher', $e->errorcode);
        }
        $this->assertFalse(tutor_role::holds($second));
        // Someone who already holds a seat (a course teacher) can still be approved.
        application::suspend($first, (int) get_admin()->id, 'x');
        $this->getDataGenerator()->enrol_user($second, $this->getDataGenerator()->create_course()->id, 'editingteacher');
        application::approve($second, (int) get_admin()->id);
        $this->assertTrue(tutor_role::holds($second));
    }
}
