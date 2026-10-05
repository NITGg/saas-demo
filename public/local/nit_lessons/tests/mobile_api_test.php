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

use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_flex\local\mobile_api as flexapi;
use local_nit_lessons\local\mobile_api;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\teacher_service;

/**
 * Mobile API for packages and live lessons: actions offered per role, booking through the API,
 * and the package quote with a bad coupon.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\local\mobile_api
 * @covers     \local_nit_flex\local\mobile_api
 */
final class mobile_api_test extends \advanced_testcase {

    /**
     * Book through the API, see the right actions for each side, accept and cancel early.
     *
     * @return void
     */
    public function test_booking_and_actions(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = (int) $gen->create_user()->id;
        $teacher = (int) $gen->create_user()->id;
        $gen->enrol_user($teacher, $gen->create_course()->id, 'editingteacher');
        (new teacher_service())->save($teacher, true, '', ['Physics'], []);
        set_config('min_booking_minutes', 0, 'local_nit_lessons');
        set_config('cancel_deadline_minutes', 60, 'local_nit_lessons');
        room_factory::set_override(new stub_room());
        purchase::fulfil($student, packages::create((object) [
            'name' => 'P', 'flex_count' => 5, 'price_minor' => 50000, 'expiration_days' => 0,
        ]));

        $this->assertCount(1, mobile_api::get_teachers($student, 'phys')['teachers']);
        $days = mobile_api::get_teacher_slots($teacher)['days'];
        $free = array_values(array_filter($days[1]['slots'], fn($s) => $s['free']));
        $lesson = mobile_api::request_lesson($student, $teacher, 'Physics', $free[0]['time'], 'note');
        $this->assertSame('pending', $lesson['status']);
        $this->assertSame(['cancel_request'], array_column($lesson['actions'], 'action'));

        $asteacher = mobile_api::get_lesson($teacher, $lesson['id']);
        $this->assertSame(['accept', 'reject', 'suggest'], array_column($asteacher['actions'], 'action'));
        $this->assertSame('teacher_respond_lesson', $asteacher['actions'][0]['function']);

        $asteacher = mobile_api::act($teacher, 'teacher_respond_lesson', $lesson['id'], ['value' => 'accept']);
        $this->assertSame('confirmed', $asteacher['status']);
        $this->assertSame(1, flexapi::get_my_flex($student)['reserved_flex']);

        $asstudent = mobile_api::act($student, 'cancel_lesson_student', $lesson['id'], []);
        $this->assertSame('returned', $asstudent['flex_state']);
        $this->assertSame(5, flexapi::get_my_flex($student)['available_flex']);

        $this->expectException(\local_nit_lessons\exception\lesson_exception::class);
        mobile_api::act($student, 'nope', $lesson['id'], []);
    }

    /**
     * A bad coupon does not fail the quote: the price comes back without it.
     *
     * @return void
     */
    public function test_quote_with_bad_coupon(): void {
        $this->resetAfterTest();
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $pkg = packages::create((object) ['name' => 'P', 'flex_count' => 5, 'price_minor' => 50000, 'expiration_days' => 0]);
        $q = flexapi::get_package_quote($student, $pkg, 'NOPE');
        $this->assertNotSame('', $q['coupon_error']);
        $this->assertSame(50000, $q['final_price_minor']);
        $this->assertFalse($q['can_pay_wallet']);
    }
}
