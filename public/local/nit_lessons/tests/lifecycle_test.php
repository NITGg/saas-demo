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
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;

/**
 * End-to-end tests for the lesson lifecycle and its Flex/finance effects.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\lesson_service
 */
final class lifecycle_test extends \advanced_testcase {
    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $teacher;

    /**
     * Set up two users, a package for the student, and time gates that pass instantly.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->student = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();
        foreach (['min_booking_minutes' => 0, 'start_allowed_minutes' => 100000,
                'complete_allowed_minutes' => 0, 'cancel_deadline_minutes' => 0,
                'absence_report_minutes' => 0] as $k => $v) {
            set_config($k, $v, 'local_nit_lessons');
        }
        set_config('teacher_percent', 40, 'local_nit_finance');
        set_config('platform_percent', 60, 'local_nit_finance');
        $packageid = packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        purchase::fulfil((int) $this->student->id, $packageid);
    }

    /**
     * Request → accept → start → complete consumes one Flex and distributes 40/60.
     *
     * @return void
     */
    public function test_happy_path_completes_and_distributes(): void {
        $sid = (int) $this->student->id;
        $tid = (int) $this->teacher->id;

        $lesson = lessons::request($sid, $tid, 'Maths', time() + 600, 'note');
        $this->assertSame('pending', $lesson['status']);

        $lesson = lessons::teacher_respond($tid, $lesson['id'], 'accept');
        $this->assertSame('confirmed', $lesson['status']);
        $this->assertSame('reserved', $lesson['flex_state']);
        $this->assertSame(1, purchase::active($sid)['reserved_flex']);

        $lesson = lessons::start($tid, $lesson['id']);
        $this->assertSame('in_progress', $lesson['status']);

        $lesson = lessons::complete($tid, $lesson['id'], 'done');
        $this->assertSame('completed', $lesson['status']);
        $this->assertSame('consumed', $lesson['flex_state']);

        $active = purchase::active($sid);
        $this->assertSame(9, $active['remaining_flex']);
        $this->assertSame(1, $active['consumed_flex']);
        $this->assertSame(4000, wallet::teacher($tid)['available_balance_minor']);
    }

    /**
     * Teacher cancelling a confirmed lesson returns the Flex and creates no earning.
     *
     * @return void
     */
    public function test_teacher_cancel_returns_flex(): void {
        $sid = (int) $this->student->id;
        $tid = (int) $this->teacher->id;
        $lesson = lessons::request($sid, $tid, 'Maths', time() + 600, 'note');
        $lesson = lessons::teacher_respond($tid, $lesson['id'], 'accept');
        lessons::cancel_as_teacher($tid, $lesson['id'], 'unavailable');

        $active = purchase::active($sid);
        $this->assertSame(10, $active['remaining_flex']);
        $this->assertSame(0, $active['reserved_flex']);
        $this->assertSame(0, wallet::teacher($tid)['available_balance_minor']);
    }

    /**
     * Reporting a student absent consumes the Flex and distributes revenue.
     *
     * @return void
     */
    public function test_student_absent_consumes_and_distributes(): void {
        $sid = (int) $this->student->id;
        $tid = (int) $this->teacher->id;
        $lesson = lessons::request($sid, $tid, 'Maths', time() + 600, 'note');
        $lesson = lessons::teacher_respond($tid, $lesson['id'], 'accept');
        $lesson = lessons::report_student_absent($tid, $lesson['id']);
        $this->assertSame('student_absent', $lesson['status']);
        $this->assertSame(9, purchase::active($sid)['remaining_flex']);
        $this->assertSame(4000, wallet::teacher($tid)['available_balance_minor']);
    }

    /**
     * A student with no Flex cannot request a lesson.
     *
     * @return void
     */
    public function test_request_requires_flex(): void {
        $poor = $this->getDataGenerator()->create_user();
        $this->expectException(\local_nit_lessons\exception\lesson_exception::class);
        lessons::request((int) $poor->id, (int) $this->teacher->id, 'Maths', time() + 600, 'note');
    }
}
