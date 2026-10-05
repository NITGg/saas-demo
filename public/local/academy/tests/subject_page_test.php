<?php
namespace local_academy;

use local_academy\local\academic_structure;
use local_academy\local\course_fields;
use local_academy\local\subject_page;

/**
 * Unit tests for {@see \local_academy\local\subject_page} — the public subject
 * page (a year = course category + the course field "Subject").
 *
 * @package    local_academy
 * @covers     \local_academy\local\subject_page
 */
final class subject_page_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/profile/lib.php');
        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['علمى']]]]);
    }

    /** Give a course a subject (as an admin). */
    private function set_subject(int $courseid, int $subject): void {
        $this->setAdminUser();
        \core_course\customfield\course_handler::create()->instance_form_save(
            (object) ['id' => $courseid, 'customfield_' . course_fields::SUBJECT => $subject]);
        $this->setUser(null);
    }

    public function test_page_lists_the_courses_and_teachers_of_the_subject_in_that_year(): void {
        $gen = $this->getDataGenerator();
        $year = $gen->create_category(['name' => 'الصف الثالث الثانوي']);
        $other = $gen->create_category(['name' => 'الصف الأول الثانوي']);
        $mine = $gen->create_course(['category' => $year->id, 'fullname' => 'Mine']);
        $otheryear = $gen->create_course(['category' => $other->id]);
        $othersubject = $gen->create_course(['category' => $year->id]);
        $this->set_subject($mine->id, 4);
        $this->set_subject($otheryear->id, 4);
        $this->set_subject($othersubject->id, 5);
        $teacher = $gen->create_user(['firstname' => 'Ahmed']);
        $gen->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $gen->enrol_user($gen->create_user()->id, $othersubject->id, 'editingteacher');

        $page = subject_page::get((int) $year->id, 4);

        $this->assertSame(['teachers' => 1, 'courses' => 1], $page['counts']);
        $this->assertSame([(int) $mine->id], array_column($page['courses'], 'id'));
        $this->assertSame([(int) $teacher->id], array_column($page['teachers'], 'id'));
        $this->assertSame('الصف الثالث الثانوي', $page['year']);
        $this->assertStringContainsString('German', $page['name'], 'the 4th default subject');
    }

    public function test_no_page_without_a_visible_course(): void {
        $gen = $this->getDataGenerator();
        $year = $gen->create_category();
        $hidden = $gen->create_course(['category' => $year->id, 'visible' => 0]);
        $this->set_subject($hidden->id, 1);

        $this->assertNull(subject_page::get((int) $year->id, 1), 'only a hidden course');
        $this->assertNull(subject_page::get((int) $year->id, 2), 'no course of that subject');
        $this->assertNull(subject_page::get((int) $year->id, 999), 'not in the list');
        $this->assertNull(subject_page::get(999999, 1), 'no such category');
    }

    public function test_url(): void {
        $this->assertSame('/local/academy/subject.php?year=6&subject=3',
            subject_page::url(6, 3)->out_as_local_url(false));
    }
}
