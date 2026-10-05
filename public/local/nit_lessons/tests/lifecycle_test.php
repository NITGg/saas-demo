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

namespace local_nit_lessons;

use local_nit_finance\api\wallet;
use local_nit_finance\local\wallets;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\teacher_service;

/**
 * End-to-end tests for the lesson lifecycle and its Flex/money effects.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\lesson_service
 * @covers     \local_nit_lessons\service\teacher_service
 */
final class lifecycle_test extends \advanced_testcase {
    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $teacher;

    /**
     * A student with a 10-Flex package (100 EGP per Flex), a bookable Physics teacher and
     * time gates that pass instantly.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->student = $gen->create_user();
        $this->teacher = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($this->teacher->id, $course->id, 'editingteacher');
        set_config('subjects', 'Physics', 'local_nit_lessons');
        (new teacher_service())->save((int) $this->teacher->id, true, '', ['Physics'], []);
        foreach (['min_booking_minutes' => 0, 'start_allowed_minutes' => 100000,
                'complete_allowed_minutes' => 0, 'cancel_deadline_minutes' => 0,
                'absence_report_minutes' => 0] as $k => $v) {
            set_config($k, $v, 'local_nit_lessons');
        }
        set_config('teacher_percent', 40, 'local_nit_finance');
        room_factory::set_override(new stub_room());
        $packageid = packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        purchase::fulfil((int) $this->student->id, $packageid);
    }

    protected function tearDown(): void {
        room_factory::set_override(null);
        parent::tearDown();
    }

    /**
     * Noon in the teacher's timezone, $days from today (inside the default 08–20 window).
     *
     * @param int $days
     * @return int
     */
    private function noon(int $days = 1): int {
        return (new \DateTimeImmutable('today', teacher_service::timezone((int) $this->teacher->id)))
            ->modify("+{$days} day")->setTime(12, 0)->getTimestamp();
    }

    /**
     * Request and confirm a lesson.
     *
     * @param int $days
     * @return array
     */
    private function confirmed(int $days = 1): array {
        $lesson = lessons::request((int) $this->student->id, (int) $this->teacher->id, 'Physics', $this->noon($days), 'note');
        return lessons::teacher_respond((int) $this->teacher->id, $lesson['id'], 'accept');
    }

    /**
     * Request → accept → start → complete: one Flex used, teacher wallet +40%, platform +60%.
     *
     * @return void
     */
    public function test_happy_path_pays_the_teacher(): void {
        $sid = (int) $this->student->id;
        $tid = (int) $this->teacher->id;
        $lesson = $this->confirmed();
        $this->assertSame('reserved', $lesson['flex_state']);
        $this->assertSame(1, purchase::active($sid)['reserved_flex']);

        lessons::start($tid, $lesson['id']);
        $lesson = lessons::complete($tid, $lesson['id'], 'done');
        $this->assertSame('completed', $lesson['status']);
        $this->assertSame(9, purchase::active($sid)['remaining_flex']);
        $this->assertSame(4000, wallet::teacher($tid)['available_balance_minor']);
        $this->assertSame(4000, wallets::balance(wallets::TEACHER, $tid));
        $this->assertSame(6000, wallets::balance(wallets::PLATFORM));
    }

    /**
     * Teacher cancelling a confirmed lesson returns the Flex and pays nobody.
     *
     * @return void
     */
    public function test_teacher_cancel_returns_flex(): void {
        $lesson = $this->confirmed();
        lessons::cancel_as_teacher((int) $this->teacher->id, $lesson['id'], 'unavailable');
        $active = purchase::active((int) $this->student->id);
        $this->assertSame(10, $active['remaining_flex']);
        $this->assertSame(0, $active['reserved_flex']);
        $this->assertSame(0, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
    }

    /**
     * Student absent: the Flex is used and the platform keeps the whole value.
     *
     * @return void
     */
    public function test_student_absent_pays_platform_only(): void {
        global $DB;
        $lesson = $this->confirmed();
        $DB->set_field('nit_lesson', 'confirmed_time', time() - 60, ['id' => $lesson['id']]);
        $lesson = lessons::report_student_absent((int) $this->teacher->id, $lesson['id']);
        $this->assertSame('student_absent', $lesson['status']);
        $this->assertSame(9, purchase::active((int) $this->student->id)['remaining_flex']);
        $this->assertSame(0, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertSame(10000, wallets::balance(wallets::PLATFORM));
    }

    /**
     * A late cancel uses the Flex (platform keeps it); an early cancel returns it.
     *
     * @return void
     */
    public function test_cancel_early_and_late(): void {
        set_config('cancel_deadline_minutes', 100000, 'local_nit_lessons');
        $lesson = $this->confirmed(1);
        $lesson = lessons::cancel_as_student((int) $this->student->id, $lesson['id']);
        $this->assertSame('consumed', $lesson['flex_state']);
        $this->assertSame(10000, wallets::balance(wallets::PLATFORM));

        set_config('cancel_deadline_minutes', 60, 'local_nit_lessons');
        $lesson = $this->confirmed(2);
        $lesson = lessons::cancel_as_student((int) $this->student->id, $lesson['id']);
        $this->assertSame('returned', $lesson['flex_state']);
        $this->assertSame(9, purchase::active((int) $this->student->id)['remaining_flex']);
    }

    /**
     * Admin reversal returns the Flex and takes both shares back.
     *
     * @return void
     */
    public function test_reverse_takes_shares_back(): void {
        $tid = (int) $this->teacher->id;
        $lesson = $this->confirmed();
        lessons::start($tid, $lesson['id']);
        lessons::complete($tid, $lesson['id']);
        lessons::reverse_flex($lesson['id'], 2, 'refund');
        $this->assertSame(10, purchase::active((int) $this->student->id)['remaining_flex']);
        $this->assertSame(0, wallets::balance(wallets::TEACHER, $tid));
        $this->assertSame(0, wallets::balance(wallets::PLATFORM));
        $this->assertSame(0, wallet::available_balance($tid));
    }

    /**
     * Booking rules: teacher must take bookings, teach the subject, and be free in their hours.
     *
     * @return void
     */
    public function test_booking_rules(): void {
        $sid = (int) $this->student->id;
        $tid = (int) $this->teacher->id;
        $cases = [
            'err_subjectnotoffered' => fn() => lessons::request($sid, $tid, 'Chemistry', $this->noon(), 'n'),
            'err_outsidehours' => fn() => lessons::request($sid, $tid, 'Physics', $this->noon() + 10 * HOURSECS, 'n'),
            'err_noterequired' => fn() => lessons::request($sid, $tid, 'Physics', $this->noon(), ''),
        ];
        foreach ($cases as $code => $fn) {
            try {
                $fn();
                $this->fail("$code expected");
            } catch (lesson_exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }
        lessons::request($sid, $tid, 'Physics', $this->noon(), 'n');
        try {
            lessons::request($sid, $tid, 'Physics', $this->noon(), 'again');
            $this->fail('time conflict expected');
        } catch (lesson_exception $e) {
            $this->assertSame('err_timeconflict', $e->errorcode);
        }
        (new teacher_service())->save($tid, false, '', ['Physics'], []);
        $this->expectExceptionMessage(get_string('err_teachernotbookable', 'local_nit_lessons'));
        lessons::request($sid, $tid, 'Physics', $this->noon(3), 'n');
    }

    /**
     * Free slots follow the teacher's hours and skip booked times.
     *
     * @return void
     */
    public function test_slots_follow_hours(): void {
        $tid = (int) $this->teacher->id;
        $service = new teacher_service();
        $tomorrow = (int) (new \DateTimeImmutable('today', teacher_service::timezone($tid)))->modify('+1 day')->format('w');
        $service->save($tid, true, '', ['Physics'], [['dayofweek' => $tomorrow, 'starttime' => '10:00', 'endtime' => '13:00']]);
        $days = $service->slots($tid);
        $this->assertCount(2, $days); // Tomorrow and the same weekday next week.
        $this->assertCount(3, $days[0]['slots']);
        $this->confirmed(1); // Books 12:00 tomorrow.
        $free = array_filter($service->slots($tid)[0]['slots'], fn($s) => $s['free']);
        $this->assertCount(2, $free);
    }

    /**
     * A student with no Flex cannot request a lesson.
     *
     * @return void
     */
    public function test_request_requires_flex(): void {
        $poor = $this->getDataGenerator()->create_user();
        $this->expectException(lesson_exception::class);
        lessons::request((int) $poor->id, (int) $this->teacher->id, 'Physics', $this->noon(), 'note');
    }
}
