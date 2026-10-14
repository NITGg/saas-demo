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

use local_nit_lessons\entity\lesson;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\service\lesson_service;

/**
 * A started lesson whose Jitsi room was removed (its activity or the lessons course deleted):
 * joining must not send anyone to a dead activity page ("Can't find data record").
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\lesson_service::open_room
 * @covers     \local_nit_lessons\room\jitsi_room::session_payload
 */
final class removed_room_test extends \advanced_testcase {

    public function test_teacher_reopens_a_removed_room_and_the_student_waits_for_it(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        room_factory::set_override(null); // The real Jitsi room.
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $course = $gen->create_course(['visible' => 0]);
        set_config('lessons_courseid', $course->id, 'local_nit_lessons');

        $lesson = new lesson(0, (object) [
            'studentid' => $student->id, 'teacherid' => $teacher->id, 'subject' => 'Physics',
            'status' => lesson::STATUS_IN_PROGRESS, 'confirmed_time' => time(), 'actual_start' => time(),
        ]);
        $lesson->create();
        $lessonid = (int) $lesson->get('id');
        $service = new lesson_service();

        // Joining opens the room once and then keeps it.
        $first = $service->open_room((int) $teacher->id, $lessonid);
        $this->assertTrue($DB->record_exists('course_modules', ['id' => $first['cmid']]));
        $this->assertSame($first['cmid'], $service->open_room((int) $student->id, $lessonid)['cmid']);

        // The room's activity is deleted (as when the lessons course is deleted).
        course_delete_module($first['cmid']);

        try {
            $service->open_room((int) $student->id, $lessonid);
            $this->fail('The student was sent to a deleted room.');
        } catch (lesson_exception $e) {
            $this->assertSame('err_roomgone', $e->errorcode);
        }

        $again = $service->open_room((int) $teacher->id, $lessonid);
        $this->assertNotSame($first['cmid'], $again['cmid']);
        $this->assertTrue($DB->record_exists('course_modules', ['id' => $again['cmid']]));
        $this->assertSame($again['cmid'], (int) lesson::get_record(['id' => $lessonid])->get('cmid'));
        $this->assertSame($again['cmid'], $service->open_room((int) $student->id, $lessonid)['cmid']);
    }

    public function test_without_a_lessons_course_the_teacher_is_told_rooms_are_not_set_up(): void {
        $this->resetAfterTest();
        room_factory::set_override(null);
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $course = $gen->create_course();
        set_config('lessons_courseid', $course->id, 'local_nit_lessons');
        delete_course($course, false); // The setting now points at a deleted course.

        $lesson = new lesson(0, (object) [
            'studentid' => $student->id, 'teacherid' => $teacher->id, 'subject' => 'Physics',
            'status' => lesson::STATUS_IN_PROGRESS, 'cmid' => 999999,
        ]);
        $lesson->create();

        $this->expectException(lesson_exception::class);
        $this->expectExceptionMessage(get_string('err_nolessonscourse', 'local_nit_lessons'));
        (new lesson_service())->open_room((int) $teacher->id, (int) $lesson->get('id'));
    }
}
