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

namespace local_nit_finance\local;

use local_nit_finance\config;

/**
 * The teacher's share of a sale.
 *
 * Each teacher can have their own percent, set by an admin on the teacher's
 * profile (field "teacherpercent", locked so teachers cannot change it). When
 * it is empty the global default applies (setting teacher_percent, 40%).
 * The platform gets the rest, including any rounding remainder.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teacher_share {

    /** Short name of the per-teacher percent profile field. */
    public const FIELD = 'teacherpercent';

    /** Profile-field group the field lives in (shared with local_academy's teacher fields). */
    public const CATEGORY = 'بيانات المدرس';

    /**
     * The teacher's percent: their own when set and valid, else the global default.
     *
     * @param int $teacherid 0 when the item has no teacher
     * @return int 0-100
     */
    public static function percent_for(int $teacherid): int {
        global $DB;
        if ($teacherid <= 0) {
            return 0;
        }
        $value = $DB->get_field_sql(
            "SELECT d.data
               FROM {user_info_data} d
               JOIN {user_info_field} f ON f.id = d.fieldid
              WHERE f.shortname = :shortname AND d.userid = :userid",
            ['shortname' => self::FIELD, 'userid' => $teacherid]);
        $value = trim((string) $value);
        if ($value !== '' && preg_match('/^\d{1,3}$/', $value) && (int) $value <= 100) {
            return (int) $value;
        }
        return self::default_percent();
    }

    /**
     * The global default teacher percent.
     *
     * @return int 0-100
     */
    public static function default_percent(): int {
        return max(0, min(100, config::teacher_percent()));
    }

    /**
     * Split an amount: the teacher's share is rounded, the platform keeps the rest,
     * so the two always add up to the amount.
     *
     * @param int $amountminor
     * @param int $teacherpercent
     * @return int[] [teacher minor, platform minor]
     */
    public static function split(int $amountminor, int $teacherpercent): array {
        $teacher = (int) round($amountminor * max(0, min(100, $teacherpercent)) / 100);
        return [$teacher, $amountminor - $teacher];
    }

    /**
     * Create the profile field (and its group) where missing. An admin's later
     * edits to the field are kept.
     *
     * @return void
     */
    public static function ensure_profile_field(): void {
        global $DB;
        if ($DB->record_exists('user_info_field', ['shortname' => self::FIELD])) {
            return;
        }
        $categoryid = $DB->get_field('user_info_category', 'id', ['name' => self::CATEGORY]);
        if (!$categoryid) {
            $sort = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}');
            $categoryid = $DB->insert_record('user_info_category', (object) ['name' => self::CATEGORY, 'sortorder' => $sort + 1]);
        }
        $sort = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = ?', [$categoryid]);
        $DB->insert_record('user_info_field', (object) [
            'shortname' => self::FIELD,
            'name' => 'نسبة المدرس من الأرباح %',
            'datatype' => 'text',
            'description' => '<p>نسبة المدرس من كل عملية شراء (0–100). اتركها فارغة لاستخدام النسبة الافتراضية للمنصة.</p>',
            'descriptionformat' => FORMAT_HTML,
            'categoryid' => $categoryid,
            'sortorder' => $sort + 1,
            'required' => 0,
            // Only admins set it; the teacher can see it but not change it.
            'locked' => 1,
            'visible' => 1, // PROFILE_VISIBLE_PRIVATE.
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => 0,
            'param1' => '3',
            'param2' => '3',
            'param3' => '0',
        ]);
    }
}
