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

use core_customfield\category_controller;
use core_customfield\field_controller;
use core_customfield\handler;

/**
 * The academy's study structure: Years, Study systems and their Divisions.
 *
 * - **Year (الصف) = the course categories, all levels** (REQUIREMENTS: "Categories
 *   = Year only, no course duplication"). A course's year is the category it sits
 *   in (and that category's parents); a student's year is a profile dropdown whose
 *   options are the categories, kept in step by sync_years() whenever a category
 *   is created, renamed, moved or deleted (see \local_academy\observer).
 * - **Study systems and their Divisions** are edited on Site administration →
 *   Plugins → Local plugins → "Study systems & divisions" (config
 *   local_academy/academic_structure, JSON). Saving calls sync(), which copies
 *   the lists into the dropdowns that use them: course custom fields
 *   study_system / division (group "custom fields") and the student profile
 *   fields studysystem / division. The link between a system and its divisions
 *   is enforced by the forms (see linked_selects).
 *
 * Moodle stores a course dropdown answer as the option's position, so when an
 * option is moved or removed sync() re-points the courses' answers to the same
 * text (an answer whose option was removed is cleared).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class academic_structure {

    /** Config name (local_academy) of the stored structure. */
    public const CONFIG = 'academic_structure';

    /** Course custom field short names. */
    public const COURSE_SYSTEM = 'study_system';
    public const COURSE_DIVISION = 'division';

    /** The course "Year" dropdown of an earlier version (years are categories now). */
    public const OLD_COURSE_YEAR = 'year';

    /** User profile field short names. */
    public const USER_YEAR = 'year';
    public const USER_SYSTEM = 'studysystem';
    public const USER_DIVISION = 'division';

    /** Separator between a category and its parents in a year's name. */
    public const PATH_SEPARATOR = ' / ';

    /**
     * The study systems a new academy starts with.
     *
     * @return array{systems:array<int, array{name:string, divisions:string[]}>}
     */
    public static function defaults(): array {
        $ml = static fn(string $ar): string => user_fields::ml($ar, self::ENGLISH[$ar]);
        return [
            'systems' => [
                ['name' => $ml('عام'), 'divisions' => [$ml('ادبى'), $ml('علمى علوم'), $ml('علمى رياضة')]],
                ['name' => $ml('أزهر'), 'divisions' => [$ml('علمى'), $ml('ادبى')]],
                ['name' => $ml('باكالوريا'), 'divisions' => [$ml('مسار طب وعلوم الحياة'), $ml('مسار الهندسة وعلوم الحاسب'),
                    $ml('مسار ادارة الاعمال'), $ml('مسار الاداب والفنون')]],
            ],
        ];
    }

    /** English for the default system / division names (earlier versions saved the Arabic alone). */
    private const ENGLISH = [
        'عام' => 'General',
        'أزهر' => 'Azhar',
        'باكالوريا' => 'Baccalaureate',
        'ادبى' => 'Literary',
        'علمى علوم' => 'Science',
        'علمى رياضة' => 'Mathematics',
        'علمى' => 'Scientific',
        'مسار طب وعلوم الحياة' => 'Medicine & Life Sciences track',
        'مسار الهندسة وعلوم الحاسب' => 'Engineering & Computer Science track',
        'مسار ادارة الاعمال' => 'Business Administration track',
        'مسار الاداب والفنون' => 'Arts & Humanities track',
    ];

    /**
     * Give the saved systems / divisions that are still a default Arabic-only name
     * their English too (other names are left as the admin typed them), keeping
     * every course's and student's answer. Also the course dropdowns' labels.
     *
     * @return void
     */
    public static function translate(): void {
        global $DB;
        // Only names the saved structure still uses (an admin may have renamed one).
        $structure = self::get();
        $used = array_merge(array_column($structure['systems'], 'name'), self::all_divisions($structure));
        $map = [];
        foreach (self::ENGLISH as $ar => $en) {
            if (in_array($ar, $used, true)) {
                $map[$ar] = user_fields::ml($ar, $en);
            }
        }
        $rename = static fn(string $name): string => $map[$name] ?? $name;

        // Course dropdowns store the option position: rename the texts in place.
        $labels = [self::COURSE_SYSTEM => ['النظام الدراسي', 'Study system'], self::COURSE_DIVISION => ['الشعبة', 'Division']];
        foreach ($labels as $shortname => [$ar, $en]) {
            $record = $DB->get_record_sql(
                "SELECT f.*
                   FROM {customfield_field} f
                   JOIN {customfield_category} c ON c.id = f.categoryid
                  WHERE f.shortname = :shortname AND c.component = 'core_course' AND c.area = 'course'",
                ['shortname' => $shortname]
            );
            if (!$record) {
                continue;
            }
            $config = json_decode((string) $record->configdata, true) ?: [];
            $options = array_map('trim', preg_split('/\R/u', (string) ($config['options'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));
            $config['options'] = implode("\n", array_map($rename, $options));
            $DB->update_record('customfield_field', (object) ['id' => $record->id, 'configdata' => json_encode($config),
                'name' => $record->name === $ar ? user_fields::ml($ar, $en) : $record->name, 'timemodified' => time()]);
        }

        // Students' answers are the texts themselves.
        foreach ([self::USER_SYSTEM, self::USER_DIVISION] as $shortname) {
            $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
            foreach ($fieldid ? $map : [] as $ar => $both) {
                $DB->set_field('user_info_data', 'data', $both, ['fieldid' => $fieldid, 'data' => $ar]);
            }
        }

        foreach ($structure['systems'] as &$system) {
            $system['name'] = $rename($system['name']);
            $system['divisions'] = array_map($rename, $system['divisions']);
        }
        unset($system);
        self::save($structure);
    }

    /**
     * The current study systems (the saved ones, else the defaults).
     *
     * @return array{systems:array<int, array{name:string, divisions:string[]}>}
     */
    public static function get(): array {
        $raw = get_config('local_academy', self::CONFIG);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) && !empty($decoded['systems']) ? self::normalise($decoded) : self::defaults();
    }

    /**
     * Clean a structure: trimmed, no empty or repeated names.
     *
     * @param array $data
     * @return array{systems:array<int, array{name:string, divisions:string[]}>}
     */
    public static function normalise(array $data): array {
        $systems = [];
        $seen = [];
        foreach ((array) ($data['systems'] ?? []) as $system) {
            $name = trim((string) ($system['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $systems[] = ['name' => $name, 'divisions' => self::unique_list((array) ($system['divisions'] ?? []))];
        }
        return ['systems' => $systems];
    }

    /**
     * The years: every visible course category, in the category tree's order.
     *
     * `key` (the category and its parents joined with " / ", as typed — {mlang}
     * markup kept) is what a student's year field stores; `name` is the same
     * path for display, filtered for the current language.
     *
     * @return array<int, array{id:int, key:string, name:string, ids:int[]}>
     */
    public static function years(): array {
        global $DB;
        $records = $DB->get_records('course_categories', ['visible' => 1], 'sortorder', 'id, name, path');
        $all = $DB->get_records_menu('course_categories', null, '', 'id, name');
        $out = [];
        foreach ($records as $cat) {
            $ids = array_map('intval', array_values(array_filter(explode('/', $cat->path), 'strlen')));
            $raw = [];
            $shown = [];
            foreach ($ids as $id) {
                $raw[] = (string) ($all[$id] ?? '');
                // Not HTML-escaped: callers escape it for where it goes (s() / the JS esc()).
                $shown[] = format_string((string) ($all[$id] ?? ''), true,
                    ['context' => \context_coursecat::instance($id), 'escape' => false]);
            }
            $out[] = [
                'id' => (int) $cat->id,
                'key' => implode(self::PATH_SEPARATOR, $raw),
                'name' => implode(self::PATH_SEPARATOR, $shown),
                'ids' => $ids,
            ];
        }
        return $out;
    }

    /**
     * The category a student's stored year points at, or 0.
     *
     * @param string $value the student's year field
     * @param array|null $years defaults to years()
     * @return int category id
     */
    public static function year_category(string $value, ?array $years = null): int {
        if ($value === '') {
            return 0;
        }
        foreach ($years ?? self::years() as $year) {
            if ($year['key'] === $value) {
                return $year['id'];
            }
        }
        return 0;
    }

    /**
     * Every division, each name once, in the order they first appear.
     *
     * @param array|null $structure defaults to get()
     * @return string[]
     */
    public static function all_divisions(?array $structure = null): array {
        $structure = $structure ?? self::get();
        $all = [];
        foreach ($structure['systems'] as $system) {
            $all = array_merge($all, $system['divisions']);
        }
        return array_values(array_unique($all));
    }

    /**
     * System name => its division names (what the linked dropdowns read).
     *
     * @param array|null $structure defaults to get()
     * @return array<string, string[]>
     */
    public static function map(?array $structure = null): array {
        $structure = $structure ?? self::get();
        $map = [];
        foreach ($structure['systems'] as $system) {
            $map[$system['name']] = $system['divisions'];
        }
        return $map;
    }

    /**
     * Save the study systems and push them into the dropdown fields.
     *
     * @param array $data
     * @return void
     */
    public static function save(array $data): void {
        set_config(self::CONFIG, json_encode(self::normalise($data), JSON_UNESCAPED_UNICODE), 'local_academy');
        self::sync();
    }

    /**
     * Create the dropdown fields where missing and give them the current lists.
     *
     * @return void
     */
    public static function sync(): void {
        $structure = self::get();
        $systems = array_column($structure['systems'], 'name');
        $divisions = self::all_divisions($structure);

        $categoryid = course_fields::ensure();
        self::course_select($categoryid, self::COURSE_SYSTEM, user_fields::ml('النظام الدراسي', 'Study system'), $systems);
        self::course_select($categoryid, self::COURSE_DIVISION, user_fields::ml('الشعبة', 'Division'), $divisions);

        user_fields::ensure();
        user_fields::set_menu_options(self::USER_SYSTEM, $systems);
        user_fields::set_menu_options(self::USER_DIVISION, $divisions);
        self::sync_years();
    }

    /**
     * Give the student "Year" dropdown the current categories.
     *
     * @return void
     */
    public static function sync_years(): void {
        user_fields::set_menu_options(self::USER_YEAR, array_column(self::years(), 'key'));
    }

    /**
     * Remove the course "Year" dropdown of an earlier version (and its answers):
     * a course's year is its category.
     *
     * @return void
     */
    public static function remove_old_course_year(): void {
        global $DB;
        $fieldid = $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE f.shortname = :shortname AND c.component = 'core_course' AND c.area = 'course'",
            ['shortname' => self::OLD_COURSE_YEAR]
        );
        if ($fieldid) {
            field_controller::create((int) $fieldid)->delete();
        }
    }

    /**
     * Create or update one course dropdown field, keeping the courses' answers.
     *
     * @param int $categoryid custom-field group
     * @param string $shortname
     * @param string $name label on the course form (only used when creating)
     * @param string[] $options
     * @return void
     */
    private static function course_select(int $categoryid, string $shortname, string $name, array $options): void {
        global $DB;
        $handler = handler::get_handler('core_course', 'course', 0);
        $record = $DB->get_record_sql(
            "SELECT f.*
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE f.shortname = :shortname AND c.component = 'core_course' AND c.area = 'course'",
            ['shortname' => $shortname]
        );
        $newoptions = implode("\n", $options);

        if (!$record) {
            $category = category_controller::create($categoryid);
            $field = field_controller::create(0, (object) ['type' => 'select'], $category);
            $handler->save_field_configuration($field, (object) [
                'name' => $name,
                'shortname' => $shortname,
                'type' => 'select',
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
                'configdata' => json_encode(['required' => 0, 'uniquevalues' => 0, 'locked' => 0,
                    'visibility' => 2, 'options' => $newoptions, 'defaultvalue' => '']),
            ]);
            return;
        }

        $config = json_decode((string) $record->configdata, true) ?: [];
        $oldoptions = preg_split('/\R/u', (string) ($config['options'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $oldoptions = array_map('trim', $oldoptions);
        if ($oldoptions === $options) {
            return;
        }

        // Re-point every answer (stored as the 1-based option position) at the
        // same text in the new list; an answer whose text is gone is cleared.
        $rows = $DB->get_records('customfield_data', ['fieldid' => $record->id], '', 'id, intvalue, value');
        foreach ($rows as $row) {
            $text = $oldoptions[(int) $row->intvalue - 1] ?? null;
            $newpos = ($text === null) ? false : array_search($text, $options, true);
            if ($newpos === false) {
                $DB->delete_records('customfield_data', ['id' => $row->id]);
            } else if ($newpos + 1 !== (int) $row->intvalue) {
                $DB->update_record('customfield_data', (object) ['id' => $row->id, 'intvalue' => $newpos + 1,
                    'value' => (string) ($newpos + 1), 'timemodified' => time()]);
            }
        }

        $config['options'] = $newoptions;
        if (!empty($config['defaultvalue']) && !in_array($config['defaultvalue'], $options, true)) {
            $config['defaultvalue'] = '';
        }
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $record->id]);
        $DB->set_field('customfield_field', 'timemodified', time(), ['id' => $record->id]);
    }

    /**
     * Trimmed, non-empty, each value once.
     *
     * @param array $values
     * @return string[]
     */
    private static function unique_list(array $values): array {
        $out = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }
        return $out;
    }
}
