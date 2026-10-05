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

use local_nit_finance\exception\finance_exception;
use moodle_url;

/**
 * What can be bought or unlocked, and for how much.
 *
 * Item types:
 *  - cm:     one activity (lesson) of a course, sold on its own at the price
 *            set on the activity's settings page;
 *  - course: a whole course (unlocked with a code; its online price lives in
 *            local_payments);
 *  - wallet: not an item — a code of this type tops up the student wallet.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {

    /** One activity. */
    public const CM = 'cm';
    /** A whole course. */
    public const COURSE = 'course';
    /** Wallet top-up (codes only). */
    public const WALLET = 'wallet';

    /**
     * An activity's own price.
     *
     * @param int $cmid
     * @return int minor units, 0 when it is not sold on its own
     */
    public static function price(int $cmid): int {
        global $DB;
        return (int) $DB->get_field('nit_item_price', 'price_minor', ['itemtype' => self::CM, 'itemid' => $cmid]);
    }

    /**
     * Prices of all the activities sold on their own in a course.
     *
     * @param int $courseid
     * @return array<int, int> cmid => minor units
     */
    public static function course_prices(int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu('nit_item_price',
            ['itemtype' => self::CM, 'courseid' => $courseid], '', 'itemid, price_minor'));
    }

    /**
     * Set or clear (0) an activity's price.
     *
     * @param int $cmid
     * @param int $courseid
     * @param int $priceminor
     * @return void
     */
    public static function set_price(int $cmid, int $courseid, int $priceminor): void {
        global $DB, $USER;
        $existing = $DB->get_record('nit_item_price', ['itemtype' => self::CM, 'itemid' => $cmid]);
        if ($priceminor <= 0) {
            if ($existing) {
                $DB->delete_records('nit_item_price', ['id' => $existing->id]);
            }
            return;
        }
        $now = time();
        if ($existing) {
            $existing->price_minor = $priceminor;
            $existing->courseid = $courseid;
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('nit_item_price', $existing);
            return;
        }
        $DB->insert_record('nit_item_price', (object) [
            'itemtype' => self::CM, 'itemid' => $cmid, 'courseid' => $courseid, 'price_minor' => $priceminor,
            'usermodified' => (int) $USER->id, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * The teacher who earns from a course's sales: the first editing teacher
     * (or, failing that, non-editing teacher) assigned to the course.
     *
     * @param int $courseid
     * @return int user id, 0 when the course has no teacher
     */
    public static function course_teacher(int $courseid): int {
        global $DB;
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return 0;
        }
        foreach (['editingteacher', 'teacher'] as $archetype) {
            $userid = $DB->get_field_sql(
                "SELECT ra.userid
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                   JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                  WHERE ra.contextid = :ctx AND r.archetype = :archetype
               ORDER BY ra.timemodified ASC, ra.id ASC",
                ['ctx' => $context->id, 'archetype' => $archetype], IGNORE_MULTIPLE);
            if ($userid) {
                return (int) $userid;
            }
        }
        return 0;
    }

    /**
     * Describe an item.
     *
     * @param string $type cm | course
     * @param int $id
     * @return \stdClass type, id, courseid, name, coursename, priceminor, teacherid, url
     */
    public static function item(string $type, int $id): \stdClass {
        global $DB;
        if ($type === self::CM) {
            $cmrow = $DB->get_record('course_modules', ['id' => $id]);
            if (!$cmrow || $cmrow->deletioninprogress) {
                throw new finance_exception('err_itemnotfound');
            }
            [$course, $cm] = get_course_and_cm_from_cmid($id);
            $url = class_exists('\local_academy\player') ? \local_academy\player::url_for($cm)
                : ($cm->url ? $cm->url->out(false) : (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false));
            return (object) [
                'type' => self::CM,
                'id' => (int) $cm->id,
                'courseid' => (int) $course->id,
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'coursename' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'priceminor' => self::price($cm->id),
                'teacherid' => self::course_teacher($course->id),
                'url' => $url,
            ];
        }
        if ($type === self::COURSE) {
            $course = $DB->get_record('course', ['id' => $id]);
            if (!$course || $course->id == SITEID) {
                throw new finance_exception('err_itemnotfound');
            }
            $name = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
            return (object) [
                'type' => self::COURSE,
                'id' => (int) $course->id,
                'courseid' => (int) $course->id,
                'name' => $name,
                'coursename' => $name,
                'priceminor' => 0,
                'teacherid' => self::course_teacher($course->id),
                'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }
        throw new finance_exception('err_itemnotfound');
    }
}
