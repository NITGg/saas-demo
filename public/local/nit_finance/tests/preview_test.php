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

namespace local_nit_finance;

use local_nit_finance\exception\finance_exception;
use local_nit_finance\local\catalog;
use local_nit_finance\local\mobile_api;
use local_nit_finance\local\preview;
use local_nit_finance\local\purchases;
use local_nit_finance\local\wallets;

/**
 * Free preview of a paid video lesson: who gets it, the settings value, the app API.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_finance\local\preview
 * @covers     \local_nit_finance\local\mobile_api
 */
final class preview_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass a Vimeo lesson sold on its own (50 EGP) with 3 free minutes */
    private $video;
    /** @var \cm_info */
    private $cm;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $gen->create_and_enrol($this->course, 'editingteacher');
        $this->video = $gen->create_module('vimeo', ['course' => $this->course->id, 'videoid' => '123456']);
        catalog::set_price((int) $this->video->cmid, (int) $this->course->id, 5000);
        preview::set((int) $this->video->cmid, (int) $this->course->id, 180);
        $this->cm = get_fast_modinfo($this->course)->get_cm($this->video->cmid);
    }

    public function test_parse_minutes(): void {
        $this->assertSame(0, preview::parse_minutes(''));
        $this->assertSame(180, preview::parse_minutes(' 3 '));
        $this->assertSame(150, preview::parse_minutes('2.5'));
        $this->assertSame(150, preview::parse_minutes('2,5'));
        $this->assertSame(0, preview::parse_minutes('0'));
        $this->assertNull(preview::parse_minutes('-1'));
        $this->assertNull(preview::parse_minutes('abc'));
        $this->assertNull(preview::parse_minutes((string) (preview::MAX_MINUTES + 1)));
        $this->assertSame('3', preview::format_minutes(180));
        $this->assertSame('2.5', preview::format_minutes(150));
        $this->assertSame('', preview::format_minutes(0));
    }

    public function test_set_updates_and_clears(): void {
        $cmid = (int) $this->video->cmid;
        preview::set($cmid, (int) $this->course->id, 60);
        $this->assertSame(60, preview::seconds($cmid));
        $this->assertSame([$cmid => 60], preview::course_seconds((int) $this->course->id));
        preview::set($cmid, (int) $this->course->id, 0);
        $this->assertSame(0, preview::seconds($cmid));
        $this->assertSame([], preview::course_seconds((int) $this->course->id));
    }

    public function test_enrolled_student_gets_preview_until_they_buy(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertSame(180, preview::for_user((int) $student->id, $this->cm));

        wallets::move(wallets::STUDENT, (int) $student->id, 5000, wallets::KIND_TOPUP);
        purchases::buy_with_wallet((int) $student->id, (int) $this->cm->id);
        $this->assertSame(0, preview::for_user((int) $student->id, $this->cm));
    }

    public function test_user_not_enrolled_gets_preview(): void {
        $visitor = $this->getDataGenerator()->create_user();
        $this->assertSame(180, preview::for_user((int) $visitor->id, $this->cm));
    }

    public function test_no_preview_for_staff_open_lesson_or_unset_preview(): void {
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->assertSame(0, preview::for_user((int) $teacher->id, $this->cm), 'staff open everything');

        // An unpriced lesson is already open for an enrolled student.
        $student = $gen->create_and_enrol($this->course, 'student');
        catalog::set_price((int) $this->cm->id, (int) $this->course->id, 0);
        $this->assertSame(0, preview::for_user((int) $student->id, $this->cm));

        // Priced again, but the teacher removed the preview.
        catalog::set_price((int) $this->cm->id, (int) $this->course->id, 5000);
        preview::set((int) $this->cm->id, (int) $this->course->id, 0);
        $this->assertSame(0, preview::for_user((int) $student->id, $this->cm));
        $this->assertSame(0, preview::for_user(0, $this->cm), 'nobody logged in');
    }

    public function test_only_video_lessons_have_a_preview(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        catalog::set_price((int) $page->cmid, (int) $this->course->id, 5000);
        preview::set((int) $page->cmid, (int) $this->course->id, 180);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $cm = get_fast_modinfo($this->course)->get_cm($page->cmid);
        $this->assertSame(0, preview::for_user((int) $student->id, $cm));
    }

    public function test_label(): void {
        $this->assertSame('1 minute', preview::label(60));
        $this->assertSame('2 minutes', preview::label(120));
        $this->assertSame('5 minutes', preview::label(300));
        $this->assertSame('2:30 minutes', preview::label(150));
    }

    public function test_mobile_api_reports_and_plays_the_preview(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $access = mobile_api::get_lesson_access((int) $student->id, (int) $this->cm->id);
        $this->assertFalse($access['open']);
        $this->assertSame(180, $access['preview_seconds']);

        $play = mobile_api::get_lesson_preview((int) $student->id, (int) $this->cm->id);
        $this->assertSame('vimeo', $play['provider']);
        $this->assertSame(180, $play['preview_seconds']);
        $this->assertStringContainsString('player.vimeo.com/video/123456', $play['embedurl']);
    }

    public function test_mobile_api_refuses_preview_once_bought(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        wallets::move(wallets::STUDENT, (int) $student->id, 5000, wallets::KIND_TOPUP);
        purchases::buy_with_wallet((int) $student->id, (int) $this->cm->id);
        $this->assertSame(0, mobile_api::get_lesson_access((int) $student->id, (int) $this->cm->id)['preview_seconds']);

        $this->expectException(finance_exception::class);
        mobile_api::get_lesson_preview((int) $student->id, (int) $this->cm->id);
    }

    public function test_deleting_the_activity_removes_the_preview(): void {
        course_delete_module((int) $this->cm->id);
        $this->assertSame(0, preview::seconds((int) $this->cm->id));
    }
}
