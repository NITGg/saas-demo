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
use local_nit_tutors\local\search;

/**
 * The teacher search, its filters, and the public teacher page of a tutor without a course.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_tutors\local\search
 * @covers     \local_nit_tutors\local\public_profile
 * @covers     \local_academy\local\teacher_page
 */
final class search_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/application_test.php');
    }

    /**
     * An approved tutor.
     *
     * @param array $profile overrides of nit_teacher_profile (lesson_flex, headline)
     * @param string $subject
     * @param array $user user fields
     * @return int
     */
    private function tutor(array $profile = [], string $subject = 'English', array $user = []): int {
        global $DB;
        $uid = application_test::complete_applicant();
        if ($user) {
            $DB->update_record('user', (object) (['id' => $uid] + $user));
        }
        $row = $DB->get_record('nit_teacher_profile', ['userid' => $uid]);
        $DB->update_record('nit_teacher_profile', (object) (['id' => $row->id] + $profile));
        $DB->set_field('nit_teacher_subject', 'subject', $subject, ['teacherid' => $uid]);
        application::approve($uid, (int) get_admin()->id);
        return $uid;
    }

    /**
     * Ids found for these filters.
     *
     * @param array $raw
     * @return int[]
     */
    private function ids(array $raw = []): array {
        return array_column(search::run(search::normalise($raw))['teachers'], 'id');
    }

    public function test_only_approved_bookable_teachers_are_listed(): void {
        global $DB;
        $approved = $this->tutor();
        $pending = application_test::complete_applicant();
        application::submit($pending);
        $off = $this->tutor();
        $DB->set_field('nit_teacher_profile', 'available', 0, ['userid' => $off]);
        $suspended = $this->tutor();
        application::suspend($suspended, (int) get_admin()->id, 'x');

        $this->assertSame([$approved], $this->ids());
    }

    public function test_text_matches_name_headline_and_subject_with_arabic_folding(): void {
        $a = $this->tutor(['headline' => 'Grammar specialist'], 'English', ['firstname' => 'Sara', 'lastname' => 'Ali']);
        $b = $this->tutor([], 'الفيزياء', ['firstname' => 'Omar', 'lastname' => 'Hassan']);
        $this->assertSame([$a], $this->ids(['q' => 'grammar']));
        $this->assertSame([$a], $this->ids(['q' => 'sara ali']));
        $this->assertSame([$b], $this->ids(['q' => 'فيزياء']));
        $this->assertSame([], $this->ids(['q' => 'chemistry']));
    }

    public function test_price_subject_and_super_filters(): void {
        $cheap = $this->tutor(['lesson_flex' => 1], 'English');
        $dear = $this->tutor(['lesson_flex' => 5], 'Math');
        $this->assertSame([$cheap], $this->ids(['pricemax' => '2']));
        $this->assertSame([$dear], $this->ids(['pricemin' => '3']));
        $this->assertSame([$dear], $this->ids(['subject' => 'Math']));

        badge::set_mode($dear, badge::ON);
        $this->assertSame([$dear], $this->ids(['super' => 1]));
        // Recommended order: the Super Tutor first.
        $this->assertSame([$dear, $cheap], $this->ids());
        $this->assertSame([$cheap, $dear], $this->ids(['sort' => 'price_asc']));
    }

    public function test_an_admin_filter_field(): void {
        $fid = fields::save((object) ['shortname' => 'country', 'name' => 'Country', 'datatype' => 'select',
            'options' => "Egypt\nSaudi Arabia", 'filter' => 1]);
        $eg = $this->tutor();
        $sa = $this->tutor();
        fields::save_answer($eg, fields::get($fid), 'Egypt');
        fields::save_answer($sa, fields::get($fid), 'Saudi Arabia');
        $this->assertSame([$sa], $this->ids(['f' => [$fid => 'Saudi Arabia']]));
        // A field that is not a filter is ignored rather than trusted.
        $other = fields::save((object) ['shortname' => 'secret', 'name' => 'Secret', 'datatype' => 'select',
            'options' => "x\ny", 'filter' => 0]);
        $this->assertCount(2, $this->ids(['f' => [$other => 'x']]));
    }

    public function test_availability_is_matched_in_the_viewers_timezone(): void {
        global $DB;
        $uid = $this->tutor();
        $DB->set_field('user', 'timezone', 'Africa/Cairo', ['id' => $uid]);
        // Sunday 09:00–11:00 Cairo time.
        $DB->insert_record('nit_teacher_hour', (object) ['teacherid' => $uid, 'dayofweek' => 0, 'starttime' => '09:00',
            'endtime' => '11:00']);
        $service = new teacher_service();
        $cairo = new \DateTimeZone('Africa/Cairo');
        $this->assertTrue(search::available_at($service, $uid, $cairo, 0, 'morning'));
        $this->assertFalse(search::available_at($service, $uid, $cairo, 0, 'evening'));
        $this->assertFalse(search::available_at($service, $uid, $cairo, 1, ''));

        // For a viewer in Tokyo (+9h vs Cairo +2h in January) the same hours are Sunday 16:00–18:00: afternoon.
        $tokyo = new \DateTimeZone('Asia/Tokyo');
        $this->assertTrue(search::available_at($service, $uid, $tokyo, 0, 'afternoon'));
        $this->assertFalse(search::available_at($service, $uid, $tokyo, 0, 'morning'));
    }

    public function test_hours_crossing_midnight_are_split(): void {
        global $DB;
        $uid = $this->tutor();
        $DB->set_field('user', 'timezone', 'UTC', ['id' => $uid]);
        $DB->insert_record('nit_teacher_hour', (object) ['teacherid' => $uid, 'dayofweek' => 6, 'starttime' => '20:00',
            'endtime' => '23:00']);
        $out = search::weekly_intervals(new teacher_service(), $uid, new \DateTimeZone('Asia/Riyadh')); // UTC+3.
        $this->assertSame([[6, 1380, 1440], [0, 0, 120]], $out);
    }

    public function test_suggest_needs_two_characters(): void {
        $this->tutor([], 'English', ['firstname' => 'Sara']);
        $this->assertSame(0, search::suggest('s')['total']);
        $result = search::suggest('sara');
        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString('/local/academy/teacher.php', $result['teachers'][0]['url']);
    }

    public function test_an_approved_tutor_without_a_course_has_a_teacher_page(): void {
        $uid = $this->tutor(['lesson_flex' => 3]);
        $page = \local_academy\local\teacher_page::get($uid);
        $this->assertNotNull($page);
        $this->assertFalse($page['hascourses']);
        $this->assertTrue($page['tutor']['listed']);
        $this->assertSame(3, $page['tutor']['price']);
        $this->assertTrue($page['tutor']['hasvideo']);
        $this->assertSame('BA English', $page['tutor']['quals'][0]['title']);
        $this->assertStringContainsString('teacher=' . $uid, $page['tutor']['bookurl']);
    }

    public function test_an_unapproved_applicant_has_no_teacher_page(): void {
        $uid = application_test::complete_applicant();
        application::submit($uid);
        $this->assertNull(\local_academy\local\teacher_page::get($uid));
    }

    public function test_a_course_teacher_page_hides_an_unapproved_video(): void {
        $uid = application_test::complete_applicant(); // Draft: the video is pending.
        $this->getDataGenerator()->enrol_user($uid, $this->getDataGenerator()->create_course()->id, 'editingteacher');
        $page = \local_academy\local\teacher_page::get($uid);
        $this->assertNotNull($page);
        $this->assertFalse($page['tutor']['hasvideo']);
        $this->assertFalse($page['tutor']['listed']);
        $this->assertSame('', $page['tutor']['bookurl']);
    }
}
