<?php
namespace local_nit_lessons;

use local_nit_lessons\room\jitsi_room;

/**
 * The live-lessons course is hidden from the catalogue, yet its students still reach the room.
 *
 * @package    local_nit_lessons
 * @covers     \local_nit_lessons\room\jitsi_room
 */
final class hidden_lessons_course_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_students_of_the_hidden_lessons_course_can_open_it(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['visible' => 0]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->assertFalse(can_access_course($course, $student->id)); // Moodle's default for a hidden course.

        $method = new \ReflectionMethod(jitsi_room::class, 'let_students_into_hidden_course');
        $method->invoke(null, (int) $course->id);
        $method->invoke(null, (int) $course->id); // Idempotent: every lesson start calls it.

        $this->assertTrue(can_access_course($course, $student->id));
        $outsider = $gen->create_user();
        $this->assertFalse(can_access_course($course, $outsider->id)); // Not enrolled: still shut out.
    }
}
