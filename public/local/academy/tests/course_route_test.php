<?php
namespace local_academy;

use local_academy\local\course_route;

/**
 * Unit tests for {@see \local_academy\local\course_route::target()} — who opening
 * course/view.php is sent to the course details page (local/academy/course.php).
 *
 * @package    local_academy
 * @covers     \local_academy\local\course_route
 */
final class course_route_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /** The local URL target() gives, or null. */
    private function target(bool $onesection = false): ?string {
        $url = course_route::target((int) $this->course->id, $onesection);
        return $url ? $url->out_as_local_url(false) : null;
    }

    public function test_a_visitor_sees_the_details_page_instead_of_the_log_in(): void {
        $this->setUser(null);
        $this->assertSame('/local/academy/course.php?id=' . $this->course->id, $this->target());
        $this->setGuestUser();
        $this->assertSame('/local/academy/course.php?id=' . $this->course->id, $this->target());
    }

    public function test_learners_see_the_details_page(): void {
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);
        $this->assertSame('/local/academy/course.php?id=' . $this->course->id, $this->target(), 'enrolled');

        $this->setUser($gen->create_user());
        $this->assertSame('/local/academy/course.php?id=' . $this->course->id, $this->target(), 'not enrolled');
    }

    public function test_staff_keep_the_real_course(): void {
        $gen = $this->getDataGenerator();
        $this->setAdminUser();
        $this->assertNull($this->target(), 'site admin');
        foreach (['editingteacher', 'teacher'] as $role) {
            $user = $gen->create_user();
            $gen->enrol_user($user->id, $this->course->id, $role);
            $this->setUser($user);
            $this->assertNull($this->target(), $role);
        }
    }

    public function test_left_alone(): void {
        $this->setUser(null);
        $this->assertNull($this->target(true), 'a single section');
        $this->assertNull(course_route::target(SITEID, false), 'the site home');
        $this->assertNull(course_route::target(0, false), 'a course asked for by name');
        $this->assertNull(course_route::target((int) $this->course->id + 1000, false), 'a missing course');
    }
}
