<?php
namespace local_academy;

use local_academy\local\academic_structure;
use local_academy\local\home_data;
use local_academy\local\teacher_page;

/**
 * Unit tests for {@see \local_academy\local\teacher_page} — the data behind the
 * public teacher page (local/academy/teacher.php) the home page cards link to.
 *
 * @package    local_academy
 * @covers     \local_academy\local\teacher_page
 */
final class teacher_page_test extends \advanced_testcase {

    /** @var \core_course_category */
    private $third;
    /** @var \core_course_category */
    private $second;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/profile/lib.php');
        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['ادبى', 'علمى']]]]);
        $gen = $this->getDataGenerator();
        $this->third = $gen->create_category(['name' => 'الصف الثالث الثانوي']);
        $this->second = $gen->create_category(['name' => 'الصف الثاني الثانوي']);
    }

    /** Save course custom-field answers as an admin. */
    private function set_fields(int $courseid, array $values): void {
        global $USER;
        $previous = $USER;
        $this->setAdminUser();
        $data = ['id' => $courseid];
        foreach ($values as $shortname => $value) {
            $data['customfield_' . $shortname] = $value;
        }
        \core_course\customfield\course_handler::create()->instance_form_save((object) $data);
        $this->setUser($previous);
    }

    /** create_course() stamps the current time; set the creation time the order is tested on. */
    private function created(\stdClass $course, int $time): void {
        global $DB;
        $DB->set_field('course', 'timecreated', $time, ['id' => $course->id]);
    }

    public function test_only_teachers_of_a_visible_course_have_a_page(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['category' => $this->third->id]);
        $hidden = $gen->create_course(['visible' => 0]);
        $student = $gen->create_user();
        $hiddenonly = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($hiddenonly->id, $hidden->id, 'editingteacher');
        $gen->enrol_user(get_admin()->id, $course->id, 'editingteacher');

        $this->assertNull(teacher_page::get((int) $student->id), 'a student');
        $this->assertNull(teacher_page::get((int) $hiddenonly->id), 'teaches only a hidden course');
        $this->assertNull(teacher_page::get((int) get_admin()->id), 'a site admin');
        $this->assertNull(teacher_page::get(0));
        $this->assertNull(teacher_page::get(999999), 'no such user');

        $suspended = $gen->create_user(['suspended' => 1]);
        $gen->enrol_user($suspended->id, $course->id, 'teacher');
        $this->assertNull(teacher_page::get((int) $suspended->id), 'a suspended account');
    }

    public function test_page_lists_the_teachers_courses_years_and_counts(): void {
        $gen = $this->getDataGenerator();
        $older = $gen->create_course(['fullname' => 'الشهر الأول', 'category' => $this->third->id]);
        $this->created($older, 1000);
        $newer = $gen->create_course(['fullname' => 'الشهر الثاني', 'category' => $this->third->id]);
        $this->created($newer, 2000);
        $other = $gen->create_course(['fullname' => 'تانية', 'category' => $this->second->id]);
        $this->created($other, 1500);
        $notmine = $gen->create_course(['category' => $this->second->id]);
        $this->set_fields($older->id, [academic_structure::COURSE_SYSTEM => 1, academic_structure::COURSE_DIVISION => 2]);

        $teacher = $gen->create_user(['firstname' => 'Mohammed', 'lastname' => 'Salah', 'description' => 'مدرس فيزياء']);
        profile_save_custom_fields($teacher->id, ['teachertitle' => 'Physics teacher']);
        foreach ([$older, $newer, $other] as $course) {
            $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        }
        // Two students, one of them in two of the courses; a student elsewhere does not count.
        $s1 = $gen->create_user();
        $s2 = $gen->create_user();
        $gen->enrol_user($s1->id, $older->id, 'student');
        $gen->enrol_user($s1->id, $other->id, 'student');
        $gen->enrol_user($s2->id, $newer->id, 'student');
        $gen->enrol_user($gen->create_user()->id, $notmine->id, 'student');

        $page = teacher_page::get((int) $teacher->id);
        $this->assertSame('Mohammed Salah', $page['name']);
        $this->assertSame('Physics teacher', $page['title']);
        $this->assertSame('مدرس فيزياء', $page['bio']);
        $this->assertSame([(int) $newer->id, (int) $other->id, (int) $older->id], array_column($page['courses'], 'id'), 'newest first');
        $this->assertSame((int) $this->third->id, $page['courses'][0]['yearid']);
        $this->assertSame('٣ ث', $page['courses'][0]['year']);
        $this->assertSame('علمى', $page['courses'][2]['division']);

        $this->assertSame([(int) $this->third->id, (int) $this->second->id], array_column($page['years'], 'id'));
        $this->assertSame('الصف الثالث الثانوي - علمى', $page['years'][0]['label'], 'with the divisions its courses are for');
        $this->assertSame(2, $page['years'][0]['courses']);
        $this->assertSame('الصف الثاني الثانوي', $page['years'][1]['label']);
        $this->assertSame(['courses' => 3, 'years' => 2, 'students' => 2], $page['counts']);
    }

    public function test_enrolled_flag_follows_the_viewer(): void {
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course(['category' => $this->third->id]);
        $this->created($mine, 2000);
        $notmine = $gen->create_course(['category' => $this->third->id]);
        $this->created($notmine, 1000);
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $gen->enrol_user($teacher->id, $notmine->id, 'editingteacher');
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $mine->id, 'student');

        $this->setUser(null);
        $this->assertSame([false, false], array_column(teacher_page::get((int) $teacher->id)['courses'], 'enrolled'), 'a visitor');
        $this->setUser($student);
        $this->assertSame([true, false], array_column(teacher_page::get((int) $teacher->id)['courses'], 'enrolled'));
    }

    public function test_home_teacher_cards_link_to_the_teacher_page(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['category' => $this->third->id]);
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');

        $url = home_data::teachers()['teachers'][0]['url'];
        $this->assertStringEndsWith('/local/academy/teacher.php?id=' . $teacher->id, $url);
    }
}
