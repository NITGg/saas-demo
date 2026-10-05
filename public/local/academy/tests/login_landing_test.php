<?php
namespace local_academy;

/**
 * Where a student lands after logging in: the home page, not the catalogue.
 *
 * @package    local_academy
 * @covers     \local_academy\observer::land_on_home
 */
final class login_landing_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Run the hook with a wanted URL and return what is left of it.
     *
     * @param \stdClass $user
     * @param string $path wanted page, relative to wwwroot
     * @return string|null
     */
    private function after_login(\stdClass $user, string $path): ?string {
        global $CFG, $SESSION;
        $SESSION->wantsurl = $CFG->wwwroot . $path;
        observer::land_on_home($user);
        return $SESSION->wantsurl ?? null;
    }

    public function test_student_goes_home_instead_of_the_catalogue(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->assertNull($this->after_login($student, '/local/nit_category/index.php?id=3'));
        $this->assertNull($this->after_login($student, '/course/index.php'));
        $this->assertNull($this->after_login($student, '/local/academy/start.php'));
    }

    public function test_a_page_the_student_logged_in_for_is_kept(): void {
        global $CFG;
        $student = $this->getDataGenerator()->create_user();
        $this->assertSame($CFG->wwwroot . '/local/academy/course.php?id=5',
            $this->after_login($student, '/local/academy/course.php?id=5'));
    }

    public function test_teachers_and_admins_keep_moodle_behaviour(): void {
        global $CFG;
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $gen->create_course()->id, 'editingteacher');
        $this->assertSame($CFG->wwwroot . '/course/index.php', $this->after_login($teacher, '/course/index.php'));
        $this->assertSame($CFG->wwwroot . '/course/index.php', $this->after_login(get_admin(), '/course/index.php'));
    }
}
