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

/**
 * Who may open which activity, lesson by lesson.
 *
 * Rules, for a student enrolled in the course:
 *  1. Staff of the course open everything.
 *  2. A student who owns the whole course (bought it online, unlocked it with
 *     a code, or has a subscription covering it) opens everything.
 *  3. An activity with its own price opens only for a student who bought it.
 *  4. An activity without a price is free — except for a student who got into
 *     a paid course only by buying single lessons: they did not pay for the
 *     course, so the unpriced activities stay closed for them.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {

    /**
     * Everything needed to decide access to any activity of one course, for
     * one user — computed once per page.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{staff:bool, ownscourse:bool, lessononly:bool, prices:array<int,int>, owned:array<int,bool>}
     */
    public static function course_state(int $userid, int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $prices = catalog::course_prices($courseid);
        $state = [
            'staff' => has_capability('moodle/course:manageactivities', $context, $userid),
            'ownscourse' => false,
            'lessononly' => false,
            'coursepriced' => self::course_has_price($courseid),
            'prices' => $prices,
            'owned' => [],
        ];
        if ($state['staff'] || $userid <= 0) {
            return $state;
        }
        $state['ownscourse'] = self::owns_course($userid, $courseid);
        $rows = $DB->get_records('nit_purchase',
            ['userid' => $userid, 'courseid' => $courseid, 'itemtype' => catalog::CM, 'status' => 'active'],
            '', 'itemid, enrolled');
        foreach ($rows as $row) {
            $state['owned'][(int) $row->itemid] = true;
            if ((int) $row->enrolled === 1) {
                $state['lessononly'] = true;
            }
        }
        if ($state['ownscourse']) {
            $state['lessononly'] = false;
        }
        return $state;
    }

    /**
     * May the user open this activity?
     *
     * @param array $state from {@see self::course_state()}
     * @param int $cmid
     * @return bool
     */
    public static function cm_open(array $state, int $cmid): bool {
        if ($state['staff'] || $state['ownscourse'] || !empty($state['owned'][$cmid])) {
            return true;
        }
        if (($state['prices'][$cmid] ?? 0) > 0) {
            return false;
        }
        return !($state['lessononly'] && $state['coursepriced']);
    }

    /**
     * May the user open this activity? (single check, for page guards)
     *
     * @param int $userid
     * @param \cm_info|\stdClass $cm needs id and course
     * @return bool
     */
    public static function can_open(int $userid, $cm): bool {
        return self::cm_open(self::course_state($userid, (int) $cm->course), (int) $cm->id);
    }

    /**
     * Does the user own the whole course (code, online purchase, subscription)?
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function owns_course(int $userid, int $courseid): bool {
        global $DB;
        if ($DB->record_exists('nit_purchase',
                ['userid' => $userid, 'itemtype' => catalog::COURSE, 'itemid' => $courseid, 'status' => 'active'])) {
            return true;
        }
        if (class_exists('\local_payments\price_resolver')) {
            if (\local_payments\price_resolver::is_purchased($courseid, $userid)
                    || \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $userid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Was the student taken out of the course (an enrolment deleted) at or after $since?
     * An online payment made before that no longer counts. Read from the standard log,
     * so it is false when that log store is off.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $since
     * @return bool
     */
    public static function unenrolled_since(int $userid, int $courseid, int $since): bool {
        global $DB;
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return false;
        }
        return $DB->record_exists_select('logstore_standard_log',
            'courseid = :courseid AND relateduserid = :userid AND eventname = :eventname AND timecreated >= :since',
            ['courseid' => $courseid, 'userid' => $userid, 'eventname' => '\core\event\user_enrolment_deleted',
                'since' => $since]);
    }

    /**
     * Did the student pay for this course or a lesson in it, and still keep it?
     * A wallet/code purchase (revoked when they are unenrolled), or a completed online
     * course payment they were not unenrolled after.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function paid_for_course(int $userid, int $courseid): bool {
        global $DB;
        if ($DB->record_exists('nit_purchase', ['userid' => $userid, 'courseid' => $courseid, 'status' => 'active'])) {
            return true;
        }
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return false;
        }
        $paid = $DB->get_fieldset_select('local_payments_transactions', 'timecreated',
            "userid = :userid AND courseid = :courseid AND status = 'completed'",
            ['userid' => $userid, 'courseid' => $courseid]);
        foreach ($paid as $time) {
            if (!self::unenrolled_since($userid, $courseid, (int) $time)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is the course itself sold (has an active online price)?
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_has_price(int $courseid): bool {
        return class_exists('\local_payments\price_resolver') && \local_payments\price_resolver::has_pricing($courseid);
    }
}
