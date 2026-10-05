<?php
namespace local_academy;

use local_academy\local\course_fields;

/**
 * Unit tests for {@see \local_academy\local\course_fields::ensure()} — the
 * course settings group "custom fields" and its "is-special" checkbox.
 *
 * @package    local_academy
 * @covers     \local_academy\local\course_fields
 */
final class course_fields_test extends \advanced_testcase {

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // The plugin's install step already created the fields; start from none.
        $DB->delete_records('customfield_data');
        $DB->delete_records('customfield_field');
        $DB->delete_records('customfield_category', ['component' => 'core_course', 'area' => 'course']);
    }

    /** Course custom-field rows with this short name. */
    private function fields(string $shortname): array {
        global $DB;
        return $DB->get_records('customfield_field', ['shortname' => $shortname]);
    }

    public function test_creates_group_and_checkbox(): void {
        global $DB;
        $categoryid = course_fields::ensure();

        $category = $DB->get_record('customfield_category', ['id' => $categoryid], '*', MUST_EXIST);
        $this->assertSame('custom fields', $category->name);
        $this->assertSame('core_course', $category->component);
        $this->assertSame('course', $category->area);

        $fields = $this->fields(course_fields::SPECIAL);
        $this->assertCount(1, $fields);
        $field = reset($fields);
        $this->assertSame('checkbox', $field->type);
        $this->assertEquals($categoryid, $field->categoryid);
        $config = json_decode($field->configdata, true);
        $this->assertSame(1, $config['locked'], 'only managers may tick it');
        $this->assertSame(0, $config['visibility'], 'not printed on course listings');
    }

    public function test_running_twice_creates_nothing_new(): void {
        global $DB;
        $first = course_fields::ensure();
        $second = course_fields::ensure();
        $this->assertSame($first, $second);
        $this->assertCount(1, $this->fields(course_fields::SPECIAL));
        $this->assertSame(1, $DB->count_records('customfield_category', ['name' => 'custom fields']));
    }

    public function test_renamed_group_is_kept_and_not_recreated(): void {
        global $DB;
        $categoryid = course_fields::ensure();
        $DB->set_field('customfield_category', 'name', 'إعدادات الأكاديمية', ['id' => $categoryid]);

        $this->assertSame($categoryid, course_fields::ensure());
        $this->assertFalse($DB->record_exists('customfield_category', ['name' => 'custom fields']));
        $this->assertCount(1, $this->fields(course_fields::SPECIAL));
    }

    public function test_reuses_an_existing_group_with_that_name(): void {
        $existing = $this->getDataGenerator()->get_plugin_generator('core_customfield')
            ->create_category(['name' => 'custom fields']);
        $this->assertEquals($existing->get('id'), course_fields::ensure());
    }

    public function test_a_ticked_course_stores_the_value(): void {
        course_fields::ensure();
        $this->setAdminUser(); // The field is locked: only managers can set it.
        $course = $this->getDataGenerator()->create_course();
        $handler = \core_course\customfield\course_handler::create();
        $handler->instance_form_save((object) ['id' => $course->id, 'customfield_' . course_fields::SPECIAL => 1]);

        global $DB;
        $field = $this->fields(course_fields::SPECIAL);
        $this->assertEquals(1, $DB->get_field('customfield_data', 'intvalue',
            ['fieldid' => reset($field)->id, 'instanceid' => $course->id]));
    }
}
