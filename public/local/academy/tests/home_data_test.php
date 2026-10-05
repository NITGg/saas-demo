<?php
namespace local_academy;

use local_academy\local\academic_structure;
use local_academy\local\course_fields;
use local_academy\local\home_data;

/**
 * Unit tests for {@see \local_academy\local\home_data} — the JSON behind the
 * home page HTML blocks. Years are course categories.
 *
 * @package    local_academy
 * @covers     \local_academy\local\home_data
 */
final class home_data_test extends \advanced_testcase {

    /** @var \core_course_category the "secondary" stage category */
    private $stage;
    /** @var \core_course_category its "third year" sub-category */
    private $third;
    /** @var \core_course_category another top-level year */
    private $first;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/profile/lib.php');
        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['ادبى', 'علمى']]]]);
        $gen = $this->getDataGenerator();
        $this->stage = $gen->create_category(['name' => 'الثانوية']);
        $this->third = $gen->create_category(['name' => 'الصف الثالث الثانوي', 'parent' => $this->stage->id]);
        $this->first = $gen->create_category(['name' => 'الصف الأول الثانوي']);
    }

    /** Save course custom-field answers as an admin (is-special is locked). */
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

    /** The year key (category path) a student's profile stores for a category. */
    private function year_key(int $categoryid): string {
        foreach (academic_structure::years() as $year) {
            if ($year['id'] === $categoryid) {
                return $year['key'];
            }
        }
        return '';
    }

    public function test_selected_lists_every_visible_course_with_its_category_year(): void {
        $gen = $this->getDataGenerator();
        $physics = $gen->create_course(['fullname' => 'الفيزياء', 'category' => $this->third->id]);
        $hidden = $gen->create_course(['visible' => 0, 'category' => $this->third->id]);
        $plain = $gen->create_course(['category' => $this->first->id]);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $physics->id, 'editingteacher');
        $gen->create_module('page', ['course' => $physics->id]);
        $gen->create_module('page', ['course' => $physics->id]);

        $courses = home_data::selected_courses();
        $ids = array_column($courses, 'id');
        $this->assertEqualsCanonicalizing([(int) $physics->id, (int) $plain->id], $ids, 'every visible course, ticked or not');
        $this->assertNotContains((int) $hidden->id, $ids);
        $card = $courses[array_search((int) $physics->id, $ids, true)];
        $this->assertSame('الفيزياء', $card['fullname']);
        $this->assertSame('الثانوية / الصف الثالث الثانوي', $card['year']);
        $this->assertSame([(int) $this->stage->id, (int) $this->third->id], $card['years'], 'its category and its parent');
        $this->assertSame(1, $card['teachers']);
        $this->assertSame(2, $card['lessons']);
    }

    public function test_suggested_lessons_are_the_special_courses(): void {
        $gen = $this->getDataGenerator();
        $ticked = $gen->create_course(['fullname' => 'مميز', 'category' => $this->third->id]);
        $hidden = $gen->create_course(['visible' => 0, 'category' => $this->third->id]);
        $plain = $gen->create_course(['fullname' => 'عادي', 'category' => $this->third->id]);
        $this->set_fields($ticked->id, [course_fields::SPECIAL => 1]);
        $this->set_fields($hidden->id, [course_fields::SPECIAL => 1]);

        $this->setUser(null);
        $this->assertSame([(int) $ticked->id], home_data::special_course_ids());
        $this->assertSame(['مميز'], array_column(home_data::lessons()['courses'], 'fullname'));
        $this->assertNotEmpty($plain->id);
    }

    public function test_selected_year_filter_lists_the_categories(): void {
        $ids = array_column(home_data::selected()['years'], 'id');
        $this->assertContains((int) $this->stage->id, $ids);
        $this->assertContains((int) $this->third->id, $ids);
        $this->assertContains((int) $this->first->id, $ids);
    }

    public function test_teachers_lists_course_teachers_but_not_admins_or_students(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['category' => $this->third->id]);
        $hiddencourse = $gen->create_course(['visible' => 0]);
        $this->set_fields($course->id, [academic_structure::COURSE_SYSTEM => 1, academic_structure::COURSE_DIVISION => 1]);
        $teacher = $gen->create_user(['firstname' => 'Tarek', 'lastname' => 'Ali']);
        $student = $gen->create_user();
        $hiddenonly = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user(get_admin()->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($hiddenonly->id, $hiddencourse->id, 'teacher');
        profile_save_custom_fields($teacher->id, ['teachertitle' => 'أستاذ الفيزياء']);

        $data = home_data::teachers();
        $this->assertSame([(int) $teacher->id], array_column($data['teachers'], 'id'));
        $this->assertSame('أستاذ الفيزياء', $data['teachers'][0]['title']);
        $this->assertSame([['years' => [(int) $this->stage->id, (int) $this->third->id], 'system' => 'عام', 'division' => 'ادبى']],
            $data['teachers'][0]['courses']);
        $this->assertNull($data['me'], 'not logged in');
    }

    public function test_teachers_returns_the_students_own_year_as_a_category(): void {
        $student = $this->getDataGenerator()->create_user();
        profile_save_custom_fields($student->id, ['year' => $this->year_key((int) $this->third->id),
            'studysystem' => 'عام', 'division' => 'ادبى']);
        $this->setUser($student);
        $this->assertSame(['year' => (int) $this->third->id, 'system' => 'عام', 'division' => 'ادبى'], home_data::teachers()['me']);
    }

    public function test_lessons_for_a_student_are_their_year_and_its_subcategories(): void {
        $gen = $this->getDataGenerator();
        $third = $gen->create_course(['fullname' => 'تالتة', 'category' => $this->third->id]);
        $first = $gen->create_course(['fullname' => 'أولى', 'category' => $this->first->id]);

        $this->setUser(null);
        $this->assertCount(2, home_data::lessons()['courses'], 'a visitor sees every course');

        $student = $gen->create_user();
        profile_save_custom_fields($student->id, ['year' => $this->year_key((int) $this->stage->id)]);
        $this->setUser($student);
        $courses = home_data::lessons()['courses'];
        $this->assertSame(['تالتة'], array_column($courses, 'fullname'), 'the stage includes its third-year sub-category');
        $this->assertSame('٣ ث', $courses[0]['year']);
        $this->assertSame('Free', $courses[0]['price'], 'free course (tests run in English)');
        $this->assertStringContainsString('/enrol/index.php?id=' . $third->id, $courses[0]['enrolurl']);
        $this->assertNotContains((int) $first->id, array_column($courses, 'id'));
    }

    public function test_short_year_in_both_languages(): void {
        $this->assertSame('٢ ث', home_data::short_year('الصف الثاني الثانوي'));
        $this->assertSame('١ ع', home_data::short_year('الصف الاول الاعدادى'));
        $this->assertSame('3 Sec', home_data::short_year('Third Year of Secondary School'));
        $this->assertSame('1 Prep', home_data::short_year('First Year of Middle School'));
        $this->assertSame('First Year', home_data::short_year('First Year'), 'anything else is kept');
        $this->assertSame('', home_data::short_year(''));
    }

    public function test_price_label_in_both_languages(): void {
        force_current_language('ar');
        $this->assertSame('175 جنيه', home_data::price_label('175.00 EGP'));
        $this->assertSame('مجاني', home_data::price_label(''));
        force_current_language('en');
        $this->assertSame('175 EGP', home_data::price_label('175.00 EGP'));
        $this->assertSame('Free', home_data::price_label(''));
        $this->assertSame('99.50 USD', home_data::price_label('99.50 USD'));
        force_current_language('');
    }

    public function test_suggested_lessons_without_special_courses_are_the_newest(): void {
        global $DB;
        $this->setUser(null);
        $old = $this->getDataGenerator()->create_course(['timecreated' => time() - DAYSECS]);
        $new = $this->getDataGenerator()->create_course(['timecreated' => time()]);
        $this->assertSame([(int) $new->id, (int) $old->id], array_column(home_data::lessons()['courses'], 'id'));

        $DB->delete_records('customfield_field', ['shortname' => course_fields::SPECIAL]);
        $this->assertSame([], home_data::special_course_ids());
        $this->assertCount(2, home_data::lessons()['courses']);
    }

    public function test_no_course_at_all_is_empty(): void {
        $this->assertSame([], home_data::selected_courses());
    }
}
