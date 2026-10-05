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
 * The academy's course custom fields (course settings → group "custom fields").
 *
 * Created on install/upgrade and only when missing, so an admin's later edits
 * (renaming, options, sort order) are never overwritten. More fields (Year,
 * Study system, Division…) join the same group later.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_fields {

    /** Name of the custom-field group on the course settings page. */
    public const CATEGORY = 'custom fields';

    /** "Show in the home page's suggested lessons" checkbox. */
    public const SPECIAL = 'is_special';

    /**
     * Create the group and its fields where missing.
     *
     * @return int the group (custom-field category) id
     */
    public static function ensure(): int {
        // A field that already exists stays where it is, even if an admin has
        // renamed or moved its group since.
        $existing = self::field_category(self::SPECIAL);
        if ($existing) {
            return $existing;
        }

        $handler = handler::get_handler('core_course', 'course', 0);
        $categoryid = self::category_id($handler);
        $category = category_controller::create($categoryid);
        $field = field_controller::create(0, (object) ['type' => 'checkbox'], $category);
        $handler->save_field_configuration($field, (object) [
            'name' => 'is-special',
            'shortname' => self::SPECIAL,
            'type' => 'checkbox',
            'description' => 'ظاهر في قسم "المحاضرات المقترحة" بالصفحة الرئيسية.',
            'descriptionformat' => FORMAT_HTML,
            'configdata' => json_encode([
                'required' => 0,
                'uniquevalues' => 0,
                // Only managers decide what the home page features.
                'locked' => 1,
                // Not printed on course listings — it is a switch, not information.
                'visibility' => 0,
                'checkbydefault' => 0,
            ]),
        ]);
        return $categoryid;
    }

    /**
     * The id of the "custom fields" group, created if missing.
     *
     * @param handler $handler course custom-field handler
     * @return int
     */
    private static function category_id(handler $handler): int {
        global $DB;
        $id = $DB->get_field('customfield_category', 'id',
            ['component' => 'core_course', 'area' => 'course', 'itemid' => 0, 'name' => self::CATEGORY]);
        return $id ? (int) $id : (int) $handler->create_category(self::CATEGORY);
    }

    /**
     * The group of the course custom field with this short name, if it exists.
     *
     * @param string $shortname
     * @return int category id, 0 when there is no such field
     */
    private static function field_category(string $shortname): int {
        global $DB;
        return (int) $DB->get_field_sql(
            "SELECT f.categoryid
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE f.shortname = :shortname AND c.component = 'core_course' AND c.area = 'course'",
            ['shortname' => $shortname]
        );
    }
}
