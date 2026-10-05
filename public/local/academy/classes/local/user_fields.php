<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_academy\local;

/**
 * The student data the registration wizard collects, as user profile fields.
 *
 * Three groups (Site administration → Users → User profile fields):
 *  - البيانات الشخصية: second / third name, national ID, gender, religious
 *    education, governorate, school;
 *  - البيانات الدراسية: year, study system, division (their options come from
 *    academic_structure);
 *  - بيانات ولي الأمر: father phone, mother phone, parent phone (the existing
 *    "parentphone" field, moved here), guardian job.
 *
 * First / last name, email and phone are Moodle's own user fields. Fields are
 * created only when missing, so an admin's later edits (labels, options, order)
 * stay.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_fields {

    /** Profile-field groups: key => [Arabic, English] name, in display order. */
    public const CATEGORIES = [
        'personal' => ['البيانات الشخصية', 'Personal details'],
        'study' => ['البيانات الدراسية', 'Study details'],
        'guardian' => ['بيانات ولي الأمر', 'Guardian details'],
        'teacher' => ['بيانات المدرس', 'Teacher details'],
    ];

    /**
     * Arabic + English in one text: the multilang2 filter shows the page language's part.
     *
     * @param string $ar
     * @param string $en
     * @return string
     */
    public static function ml(string $ar, string $en): string {
        return '{mlang ar}' . $ar . '{mlang}{mlang en}' . $en . '{mlang}';
    }

    /**
     * The fields, in display order within each group. Names and fixed options are
     * [Arabic, English] pairs (the Arabic alone is what earlier versions stored).
     *
     * @return array<string, array{category:string, name:string[], type:string, options?:array<int, string[]>}>
     */
    public static function definitions(): array {
        return [
            'secondname' => ['category' => 'personal', 'name' => ['الاسم الثاني', 'Second name'], 'type' => 'text'],
            'thirdname' => ['category' => 'personal', 'name' => ['الاسم الثالث', 'Third name'], 'type' => 'text'],
            'nationalid' => ['category' => 'personal', 'name' => ['الرقم القومي', 'National ID'], 'type' => 'text'],
            'gender' => ['category' => 'personal', 'name' => ['النوع', 'Gender'], 'type' => 'menu',
                'options' => [['ذكر', 'Male'], ['أنثى', 'Female']]],
            'religion' => ['category' => 'personal', 'name' => ['مادة التربية الدينية', 'Religious education'], 'type' => 'menu',
                'options' => [['التربية الدينية الاسلامية', 'Islamic religious education'],
                    ['التربية الدينية المسيحية', 'Christian religious education']]],
            'governorate' => ['category' => 'personal', 'name' => ['المحافظة', 'Governorate'], 'type' => 'menu', 'options' => [
                ['القاهرة', 'Cairo'], ['الجيزة', 'Giza'], ['الإسكندرية', 'Alexandria'], ['الدقهلية', 'Dakahlia'],
                ['البحر الأحمر', 'Red Sea'], ['البحيرة', 'Beheira'], ['الفيوم', 'Faiyum'], ['الغربية', 'Gharbia'],
                ['الإسماعيلية', 'Ismailia'], ['المنوفية', 'Monufia'], ['المنيا', 'Minya'], ['القليوبية', 'Qalyubia'],
                ['الوادي الجديد', 'New Valley'], ['السويس', 'Suez'], ['أسوان', 'Aswan'], ['أسيوط', 'Asyut'],
                ['بني سويف', 'Beni Suef'], ['بورسعيد', 'Port Said'], ['دمياط', 'Damietta'], ['الشرقية', 'Sharqia'],
                ['جنوب سيناء', 'South Sinai'], ['كفر الشيخ', 'Kafr El Sheikh'], ['مطروح', 'Matrouh'], ['الأقصر', 'Luxor'],
                ['قنا', 'Qena'], ['شمال سيناء', 'North Sinai'], ['سوهاج', 'Sohag']]],
            'school' => ['category' => 'personal', 'name' => ['اسم المدرسة', 'School name'], 'type' => 'text'],
            academic_structure::USER_YEAR => ['category' => 'study', 'name' => ['الصف الدراسي', 'School year'], 'type' => 'menu'],
            academic_structure::USER_SYSTEM => ['category' => 'study', 'name' => ['النظام الدراسي', 'Study system'], 'type' => 'menu'],
            academic_structure::USER_DIVISION => ['category' => 'study', 'name' => ['الشعبة', 'Division'], 'type' => 'menu'],
            'fatherphone' => ['category' => 'guardian', 'name' => ['رقم هاتف الأب', "Father's phone"], 'type' => 'text'],
            'motherphone' => ['category' => 'guardian', 'name' => ['رقم هاتف الأم', "Mother's phone"], 'type' => 'text'],
            'parentphone' => ['category' => 'guardian', 'name' => ['رقم هاتف ولي الأمر', "Guardian's phone"], 'type' => 'text'],
            'guardianjob' => ['category' => 'guardian', 'name' => ['مهنة ولي الأمر', "Guardian's job"], 'type' => 'text'],
            // Teachers: the line under the name on the home page teacher cards.
            'teachertitle' => ['category' => 'teacher', 'name' => ['لقب المدرس (مثال: أستاذ اللغة العربية)',
                'Teacher title (e.g. Arabic teacher)'], 'type' => 'text'],
        ];
    }

    /**
     * Turn the groups, field names and fixed options that earlier versions saved in
     * Arabic only into Arabic + English, and re-point the answers to the new option
     * texts. Anything an admin has changed since is left alone.
     *
     * @return void
     */
    public static function translate(): void {
        global $DB;
        foreach (self::CATEGORIES as [$ar, $en]) {
            $DB->set_field('user_info_category', 'name', self::ml($ar, $en), ['name' => $ar]);
        }
        // "parentphone" was made by another plugin with an English name.
        $old = ['parentphone' => 'Parent / guardian phone'];
        foreach (self::definitions() as $shortname => $def) {
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
            if (!$field) {
                continue;
            }
            if (in_array($field->name, [$def['name'][0], $old[$shortname] ?? null], true)) {
                $DB->set_field('user_info_field', 'name', self::ml(...$def['name']), ['id' => $field->id]);
            }
            if (empty($def['options']) || $field->datatype !== 'menu') {
                continue;
            }
            $map = [];
            foreach ($def['options'] as [$ar, $en]) {
                $map[$ar] = self::ml($ar, $en);
            }
            $options = array_map(static fn($o) => $map[$o] ?? $o, self::menu_options($shortname));
            self::set_menu_options($shortname, $options);
            foreach ($map as $ar => $both) {
                $DB->set_field('user_info_data', 'data', $both, ['fieldid' => $field->id, 'data' => $ar]);
            }
        }
    }

    /**
     * Create the groups and fields where missing.
     *
     * The existing "parentphone" field is only moved into the guardian group.
     *
     * @return void
     */
    public static function ensure(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $categoryids = [];
        $sort = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}');
        foreach (self::CATEGORIES as $key => [$ar, $en]) {
            $name = self::ml($ar, $en);
            $id = $DB->get_field('user_info_category', 'id', ['name' => $name])
                ?: $DB->get_field('user_info_category', 'id', ['name' => $ar]);
            if (!$id) {
                $id = $DB->insert_record('user_info_category', (object) ['name' => $name, 'sortorder' => ++$sort]);
            }
            $categoryids[$key] = (int) $id;
        }

        $structure = academic_structure::get();
        $menus = [
            academic_structure::USER_YEAR => array_column(academic_structure::years(), 'key'),
            academic_structure::USER_SYSTEM => array_column($structure['systems'], 'name'),
            academic_structure::USER_DIVISION => academic_structure::all_divisions($structure),
        ];
        $order = [];
        foreach (self::definitions() as $shortname => $def) {
            $categoryid = $categoryids[$def['category']];
            $order[$categoryid] = ($order[$categoryid] ?? 0) + 1;
            $existing = $DB->get_record('user_info_field', ['shortname' => $shortname]);
            if ($existing) {
                if ($shortname === 'parentphone' && (int) $existing->categoryid !== $categoryid) {
                    $DB->update_record('user_info_field', (object) ['id' => $existing->id,
                        'categoryid' => $categoryid, 'sortorder' => $order[$categoryid]]);
                }
                continue;
            }
            $options = $menus[$shortname] ?? array_map(static fn($o) => self::ml(...$o), $def['options'] ?? []);
            $DB->insert_record('user_info_field', (object) [
                'shortname' => $shortname,
                'name' => self::ml(...$def['name']),
                'datatype' => $def['type'],
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
                'categoryid' => $categoryid,
                'sortorder' => $order[$categoryid],
                'required' => 0,
                'locked' => 0,
                // The student and the staff see it; other users do not.
                'visible' => PROFILE_VISIBLE_PRIVATE,
                'forceunique' => 0,
                'signup' => 0,
                'defaultdata' => '',
                'defaultdataformat' => 0,
                'param1' => $def['type'] === 'menu' ? implode("\n", $options) : '30',
                'param2' => $def['type'] === 'menu' ? null : '2048',
                'param3' => $def['type'] === 'menu' ? null : '0',
            ]);
        }
    }

    /**
     * Replace a dropdown profile field's options (answers are stored as text,
     * so existing answers keep their value).
     *
     * @param string $shortname
     * @param string[] $options
     * @return void
     */
    public static function set_menu_options(string $shortname, array $options): void {
        global $DB;
        $DB->set_field('user_info_field', 'param1', implode("\n", $options),
            ['shortname' => $shortname, 'datatype' => 'menu']);
    }

    /**
     * A user's values for these fields (shortname => text, '' when unset).
     *
     * @param int $userid
     * @return array<string, string>
     */
    public static function values(int $userid): array {
        global $DB;
        $out = array_fill_keys(array_keys(self::definitions()), '');
        $rows = $DB->get_records_sql(
            "SELECT f.shortname, d.data
               FROM {user_info_field} f
               JOIN {user_info_data} d ON d.fieldid = f.id AND d.userid = :userid",
            ['userid' => $userid]
        );
        foreach ($rows as $shortname => $row) {
            if (array_key_exists($shortname, $out)) {
                $out[$shortname] = (string) $row->data;
            }
        }
        return $out;
    }

    /**
     * The options of a dropdown profile field.
     *
     * @param string $shortname
     * @return string[]
     */
    public static function menu_options(string $shortname): array {
        global $DB;
        $param = (string) $DB->get_field('user_info_field', 'param1', ['shortname' => $shortname, 'datatype' => 'menu']);
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $param)), 'strlen'));
    }
}
