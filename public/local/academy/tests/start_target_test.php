<?php
namespace local_academy;

/**
 * Unit tests for {@see \local_academy\start_target::url_for_user()} — where the
 * home page's "ابدأ رحلتك" button sends each kind of user.
 *
 * @package    local_academy
 * @covers     \local_academy\start_target
 */
final class start_target_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** Records that $userid opened $cm at $time, as core does on an activity view. */
    private function opened(int $userid, \stdClass $cm, int $time): void {
        global $DB;
        $DB->insert_record('block_recentlyaccesseditems', (object) [
            'userid' => $userid, 'courseid' => $cm->course, 'cmid' => $cm->cmid, 'timeaccess' => $time,
        ]);
    }

    public function test_visitor_goes_to_registration(): void {
        $this->assertSame('/local/academy/register.php', start_target::url_for_user(0)->out_as_local_url(false));
    }

    public function test_guest_user_goes_to_registration(): void {
        $guest = guest_user();
        $this->assertSame('/local/academy/register.php',
            start_target::url_for_user((int) $guest->id)->out_as_local_url(false));
    }

    public function test_user_without_courses_goes_to_catalogue(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame('/course/index.php', start_target::url_for_user((int) $user->id)->out_as_local_url(false));
    }

    public function test_enrolled_user_without_activity_goes_to_my_courses(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($user->id, $course->id, 'student');
        $this->assertSame('/my/courses.php', start_target::url_for_user((int) $user->id)->out_as_local_url(false));
    }

    public function test_returns_most_recent_activity(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($user->id, $course->id, 'student');
        $older = $gen->create_module('page', ['course' => $course->id]);
        $newer = $gen->create_module('page', ['course' => $course->id]);
        $this->opened((int) $user->id, $older, 1000);
        $this->opened((int) $user->id, $newer, 2000);

        $url = start_target::url_for_user((int) $user->id);
        $this->assertSame('/mod/page/view.php', $url->get_path(false));
        $this->assertEquals($newer->cmid, $url->get_param('id'));
    }

    public function test_skips_activity_the_user_can_no_longer_open(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($user->id, $course->id, 'student');
        $visible = $gen->create_module('page', ['course' => $course->id]);
        $hidden = $gen->create_module('page', ['course' => $course->id]);
        $this->opened((int) $user->id, $visible, 1000);
        $this->opened((int) $user->id, $hidden, 2000);
        set_coursemodule_visible($hidden->cmid, 0);

        $url = start_target::url_for_user((int) $user->id);
        $this->assertEquals($visible->cmid, $url->get_param('id'));
    }

    public function test_deleted_activity_falls_back_to_my_courses(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($user->id, $course->id, 'student');
        $page = $gen->create_module('page', ['course' => $course->id]);
        $this->opened((int) $user->id, $page, 1000);
        // Remove the activity but keep a stale history row pointing at it.
        global $DB;
        $DB->delete_records('course_modules', ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $this->assertSame('/my/courses.php', start_target::url_for_user((int) $user->id)->out_as_local_url(false));
    }

    public function test_another_users_history_is_not_used(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $other = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($other->id, $course->id, 'student');
        $page = $gen->create_module('page', ['course' => $course->id]);
        $this->opened((int) $other->id, $page, 1000);

        $this->assertSame('/course/index.php', start_target::url_for_user((int) $user->id)->out_as_local_url(false));
    }
}
