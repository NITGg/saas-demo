<?php
namespace local_academy;

use local_academy\local\user_fields;

/**
 * Unit tests for {@see \local_academy\local\user_fields} — the registration data
 * as student profile fields.
 *
 * @package    local_academy
 * @covers     \local_academy\local\user_fields
 */
final class user_fields_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_install_created_every_field_in_its_group(): void {
        global $DB;
        foreach (user_fields::definitions() as $shortname => $def) {
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname], '*', MUST_EXIST);
            $this->assertSame($def['type'], $field->datatype, $shortname);
            $this->assertSame(user_fields::ml(...user_fields::CATEGORIES[$def['category']]),
                $DB->get_field('user_info_category', 'name', ['id' => $field->categoryid]), $shortname);
            $this->assertSame(user_fields::ml(...$def['name']), $field->name, $shortname);
        }
        $this->assertSame([user_fields::ml('ذكر', 'Male'), user_fields::ml('أنثى', 'Female')], user_fields::menu_options('gender'));
        $this->assertCount(27, user_fields::menu_options('governorate'));
    }

    public function test_translate_turns_old_arabic_only_texts_into_both_languages_and_keeps_answers(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        // What an earlier version saved: Arabic only.
        $DB->set_field('user_info_category', 'name', 'البيانات الشخصية', ['name' => user_fields::ml('البيانات الشخصية', 'Personal details')]);
        $DB->set_field('user_info_field', 'name', 'النوع', ['shortname' => 'gender']);
        $DB->set_field('user_info_field', 'name', 'اسم من الأدمن', ['shortname' => 'school']);
        user_fields::set_menu_options('gender', ['ذكر', 'أنثى']);
        $student = $this->getDataGenerator()->create_user();
        profile_save_custom_fields($student->id, ['gender' => 'أنثى']);

        user_fields::translate();

        $this->assertTrue($DB->record_exists('user_info_category', ['name' => user_fields::ml('البيانات الشخصية', 'Personal details')]));
        $this->assertSame(user_fields::ml('النوع', 'Gender'), $DB->get_field('user_info_field', 'name', ['shortname' => 'gender']));
        $this->assertSame('اسم من الأدمن', $DB->get_field('user_info_field', 'name', ['shortname' => 'school']), 'admin edit kept');
        $this->assertSame([user_fields::ml('ذكر', 'Male'), user_fields::ml('أنثى', 'Female')], user_fields::menu_options('gender'));
        $this->assertSame(user_fields::ml('أنثى', 'Female'), user_fields::values((int) $student->id)['gender'], 'answer re-pointed');
        force_current_language('en');
        $this->assertSame('Female', format_string(user_fields::values((int) $student->id)['gender']));
        force_current_language('');
    }

    public function test_ensure_keeps_admin_edits_and_moves_parentphone(): void {
        global $DB;
        $DB->set_field('user_info_field', 'name', 'اسم آخر', ['shortname' => 'school']);
        $other = $DB->insert_record('user_info_category', (object) ['name' => 'Other', 'sortorder' => 99]);
        $DB->set_field('user_info_field', 'categoryid', $other, ['shortname' => 'parentphone']);

        user_fields::ensure();

        $this->assertSame('اسم آخر', $DB->get_field('user_info_field', 'name', ['shortname' => 'school']));
        $this->assertSame(1, $DB->count_records('user_info_field', ['shortname' => 'school']));
        $guardian = $DB->get_field('user_info_category', 'id', ['name' => user_fields::ml(...user_fields::CATEGORIES['guardian'])]);
        $this->assertEquals($guardian, $DB->get_field('user_info_field', 'categoryid', ['shortname' => 'parentphone']));
    }

    public function test_values_returns_a_users_answers_only(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $other = $gen->create_user();
        profile_save_custom_fields($student->id, ['school' => 'مدرسة النصر', 'gender' => user_fields::ml('أنثى', 'Female')]);
        profile_save_custom_fields($other->id, ['school' => 'مدرسة أخرى']);

        $values = user_fields::values((int) $student->id);
        $this->assertSame('مدرسة النصر', $values['school']);
        $this->assertSame(user_fields::ml('أنثى', 'Female'), $values['gender']);
        $this->assertSame('', $values['nationalid']);
        $this->assertSame(array_keys(user_fields::definitions()), array_keys($values));
    }
}
