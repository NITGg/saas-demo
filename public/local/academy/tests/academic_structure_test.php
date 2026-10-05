<?php
namespace local_academy;

use local_academy\local\academic_structure;
use local_academy\local\user_fields;

/**
 * Unit tests for {@see \local_academy\local\academic_structure} — Years (= the
 * course categories) and the Study systems / Divisions lists, and their sync into
 * the course and student dropdown fields.
 *
 * @package    local_academy
 * @covers     \local_academy\local\academic_structure
 * @covers     \local_academy\observer::category_changed
 */
final class academic_structure_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** Options of a course dropdown field. */
    private function course_options(string $shortname): array {
        global $DB;
        $config = json_decode($DB->get_field('customfield_field', 'configdata', ['shortname' => $shortname]), true);
        return preg_split('/\R/u', $config['options']);
    }

    /** Set a course's dropdown answer (1-based option position). */
    private function answer(int $courseid, string $shortname, int $position): void {
        $this->setAdminUser();
        \core_course\customfield\course_handler::create()->instance_form_save(
            (object) ['id' => $courseid, 'customfield_' . $shortname => $position]);
    }

    /** A course's stored answer position, or false. */
    private function stored(int $courseid, string $shortname) {
        global $DB;
        $fieldid = $DB->get_field('customfield_field', 'id', ['shortname' => $shortname]);
        return $DB->get_field('customfield_data', 'intvalue', ['fieldid' => $fieldid, 'instanceid' => $courseid]);
    }

    public function test_defaults_until_saved(): void {
        unset_config(academic_structure::CONFIG, 'local_academy');
        $this->assertSame(academic_structure::defaults(), academic_structure::get());
        $this->assertSame(['ادبى', 'علمى علوم', 'علمى رياضة'], academic_structure::map()['عام']);
    }

    public function test_normalise_drops_blanks_and_repeats(): void {
        $clean = academic_structure::normalise(['systems' => [
            ['name' => 'عام', 'divisions' => ['ادبى', ' ', 'ادبى', 'علمى']],
            ['name' => ' ', 'divisions' => ['x']],
            ['name' => 'عام', 'divisions' => ['مكرر']],
        ]]);
        $this->assertSame(['systems' => [['name' => 'عام', 'divisions' => ['ادبى', 'علمى']]]], $clean);
    }

    public function test_all_divisions_lists_each_once(): void {
        $divisions = academic_structure::all_divisions(academic_structure::defaults());
        $this->assertSame(1, count(array_keys($divisions, 'ادبى', true)), 'shared by عام and أزهر, listed once');
        $this->assertContains('مسار الاداب والفنون', $divisions);
    }

    public function test_years_are_the_visible_categories_with_their_parents(): void {
        $gen = $this->getDataGenerator();
        $stage = $gen->create_category(['name' => '{mlang en}Secondary{mlang}{mlang ar}الثانوية{mlang}']);
        $third = $gen->create_category(['name' => 'الصف الثالث', 'parent' => $stage->id]);
        $gen->create_category(['name' => 'مخفي', 'visible' => 0]);

        $years = array_column(academic_structure::years(), null, 'id');
        $this->assertArrayHasKey($stage->id, $years);
        $this->assertSame([(int) $stage->id, (int) $third->id], $years[$third->id]['ids']);
        $this->assertSame('{mlang en}Secondary{mlang}{mlang ar}الثانوية{mlang} / الصف الثالث', $years[$third->id]['key']);
        $this->assertNotContains('مخفي', array_column($years, 'name'));
        $this->assertSame((int) $third->id, academic_structure::year_category($years[$third->id]['key']));
        $this->assertSame(0, academic_structure::year_category('غير موجود'));
    }

    public function test_student_year_options_follow_the_categories(): void {
        $gen = $this->getDataGenerator();
        $cat = $gen->create_category(['name' => 'الصف الأول الثانوي']); // Fires course_category_created.
        $this->assertContains('الصف الأول الثانوي', user_fields::menu_options(academic_structure::USER_YEAR));

        $cat->update(['name' => 'الصف الأول']); // Fires course_category_updated.
        $options = user_fields::menu_options(academic_structure::USER_YEAR);
        $this->assertContains('الصف الأول', $options);
        $this->assertNotContains('الصف الأول الثانوي', $options);
    }

    public function test_install_created_the_course_and_student_dropdowns(): void {
        global $DB;
        foreach ([academic_structure::COURSE_SYSTEM, academic_structure::COURSE_DIVISION] as $f) {
            $this->assertSame('select', $DB->get_field('customfield_field', 'type', ['shortname' => $f], MUST_EXIST));
        }
        $this->assertFalse($DB->record_exists('customfield_field', ['shortname' => academic_structure::OLD_COURSE_YEAR]),
            'a course\'s year is its category, there is no course Year field');
        $this->assertSame(['عام', 'أزهر', 'باكالوريا'], $this->course_options(academic_structure::COURSE_SYSTEM));
        $this->assertSame(['عام', 'أزهر', 'باكالوريا'], user_fields::menu_options(academic_structure::USER_SYSTEM));
    }

    public function test_remove_old_course_year_deletes_the_field(): void {
        global $DB;
        $category = $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_category();
        $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_field(
            ['categoryid' => $category->get('id'), 'type' => 'select', 'shortname' => academic_structure::OLD_COURSE_YEAR,
             'configdata' => ['options' => "a\nb"]]);
        academic_structure::remove_old_course_year();
        $this->assertFalse($DB->record_exists('customfield_field', ['shortname' => academic_structure::OLD_COURSE_YEAR]));
    }

    public function test_reordering_keeps_course_answers_and_removing_clears_them(): void {
        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['ادبى', 'علمى', 'فني']]]]);
        $gen = $this->getDataGenerator();
        $second = $gen->create_course();
        $first = $gen->create_course();
        $this->answer($second->id, academic_structure::COURSE_DIVISION, 2); // علمى
        $this->answer($first->id, academic_structure::COURSE_DIVISION, 1);  // ادبى

        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['فني', 'علمى']]]]);

        $this->assertEquals(2, $this->stored($second->id, academic_structure::COURSE_DIVISION), 'still points at علمى');
        $this->assertFalse($this->stored($first->id, academic_structure::COURSE_DIVISION), 'its division was removed');
    }

    public function test_arabic_options_are_not_split_inside_a_letter(): void {
        // "م" is D9 85 in UTF-8; without the /u flag \R treats the 0x85 byte as a line break.
        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['علمى علوم', 'ادبى']]]]);
        $course = $this->getDataGenerator()->create_course();
        $this->answer($course->id, academic_structure::COURSE_DIVISION, 1); // علمى علوم

        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['ادبى', 'علمى علوم']]]]);

        $this->assertSame(['ادبى', 'علمى علوم'], $this->course_options(academic_structure::COURSE_DIVISION));
        $this->assertEquals(2, $this->stored($course->id, academic_structure::COURSE_DIVISION));
        $this->assertSame(['ادبى', 'علمى علوم'], user_fields::menu_options(academic_structure::USER_DIVISION));
    }

    public function test_defaults_are_in_both_languages(): void {
        $first = academic_structure::defaults()['systems'][0];
        $this->assertSame(user_fields::ml('عام', 'General'), $first['name']);
        force_current_language('en');
        $this->assertSame('General', format_string($first['name']));
        force_current_language('');
    }

    public function test_translate_renames_old_arabic_names_and_keeps_every_answer(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        academic_structure::save(['systems' => [['name' => 'عام', 'divisions' => ['ادبى', 'علمى علوم']],
            ['name' => 'نظام من الأدمن', 'divisions' => ['ادبى']]]]);
        $course = $this->getDataGenerator()->create_course();
        $this->answer($course->id, academic_structure::COURSE_DIVISION, 2); // علمى علوم
        $student = $this->getDataGenerator()->create_user();
        profile_save_custom_fields($student->id, ['studysystem' => 'عام', 'division' => 'علمى علوم']);

        academic_structure::translate();

        $general = user_fields::ml('عام', 'General');
        $science = user_fields::ml('علمى علوم', 'Science');
        $this->assertSame([$general, 'نظام من الأدمن'], array_keys(academic_structure::map()), 'admin names kept');
        $this->assertSame([user_fields::ml('ادبى', 'Literary'), $science], $this->course_options(academic_structure::COURSE_DIVISION));
        $this->assertEquals(2, $this->stored($course->id, academic_structure::COURSE_DIVISION), 'course answer kept');
        $values = user_fields::values((int) $student->id);
        $this->assertSame([$general, $science], [$values['studysystem'], $values['division']], 'student answers re-pointed');
    }

    public function test_admin_setting_rejects_a_system_without_divisions(): void {
        $setting = new admin_setting_academicstructure('local_academy/' . academic_structure::CONFIG, '', '', '');
        $this->assertNotSame('', $setting->write_setting(['name' => ['عام', 'أزهر'], 'divisions' => ["ادبى", ""]]));
        $this->assertSame('', $setting->write_setting(['name' => ['عام'], 'divisions' => ["ادبى\nعلمى"]]));
        $this->assertSame(['ادبى', 'علمى'], academic_structure::map()['عام']);
    }
}
