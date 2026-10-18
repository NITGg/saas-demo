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

namespace local_nit_tutors\local;

/**
 * The Super Tutor badge.
 *
 * Earned automatically by an approved teacher who meets every rule the admin set (Site
 * administration → Users → Teacher applications → Settings): an average rating, a number of
 * ratings, completed private lessons, and a low no-show rate. A daily task refreshes it, so it is
 * also lost automatically. The reviewer can pin it on or off for one teacher (badgemode on/off),
 * which beats the rules.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class badge {

    /** Modes. */
    const AUTO = 'auto';
    const ON = 'on';
    const OFF = 'off';

    /**
     * The admin's rules.
     *
     * @return array{minrating: float, minreviews: int, minlessons: int, maxabsence: float}
     */
    public static function rules(): array {
        $get = function(string $name, $default) {
            $v = get_config('local_nit_tutors', $name);
            return ($v === false || $v === null || $v === '') ? $default : $v;
        };
        return [
            'minrating' => (float) $get('badge_minrating', 4.8),
            'minreviews' => (int) $get('badge_minreviews', 10),
            'minlessons' => (int) $get('badge_minlessons', 30),
            'maxabsence' => (float) $get('badge_maxabsence', 2),
        ];
    }

    /**
     * The teacher's numbers the rules are checked against.
     *
     * @param int $userid
     * @return array{rating: float, reviews: int, lessons: int, absences: int, absencepct: float}
     */
    public static function stats(int $userid): array {
        global $DB;
        $rating = 0.0;
        $reviews = 0;
        if (class_exists('\local_nit_reviews\api')) {
            $agg = \local_nit_reviews\api::get_teacher_aggregate($userid);
            $rating = (float) $agg->avg;
            $reviews = (int) $agg->count;
        }
        $lessons = $DB->count_records('nit_lesson', ['teacherid' => $userid, 'status' => 'completed']);
        $absences = $DB->count_records('nit_lesson', ['teacherid' => $userid, 'status' => 'teacher_absent']);
        $held = $lessons + $absences;
        return ['rating' => $rating, 'reviews' => $reviews, 'lessons' => $lessons, 'absences' => $absences,
            'absencepct' => $held ? round($absences * 100 / $held, 1) : 0.0];
    }

    /**
     * Does the teacher meet the rules?
     *
     * @param int $userid
     * @return bool
     */
    public static function earned(int $userid): bool {
        $r = self::rules();
        $s = self::stats($userid);
        return $s['reviews'] >= $r['minreviews'] && $s['reviews'] > 0 && $s['rating'] >= $r['minrating']
            && $s['lessons'] >= $r['minlessons'] && $s['absencepct'] <= $r['maxabsence'];
    }

    /**
     * Recompute and store the badge of one teacher.
     *
     * @param int $userid
     * @return bool whether they wear it now
     */
    public static function refresh(int $userid): bool {
        global $DB;
        $row = application::get($userid);
        if (!$row) {
            return false;
        }
        $on = $row->status === application::APPROVED && ($row->badgemode === self::ON
            || ($row->badgemode !== self::OFF && self::earned($userid)));
        if ((bool) $row->badge !== $on) {
            $DB->update_record(application::TABLE, (object) ['id' => $row->id, 'badge' => $on ? 1 : 0,
                'badgesince' => $on ? time() : 0, 'timemodified' => time()]);
        }
        return $on;
    }

    /**
     * Pin the badge on/off for one teacher, or back to the rules.
     *
     * @param int $userid
     * @param string $mode auto|on|off
     */
    public static function set_mode(int $userid, string $mode): void {
        global $DB;
        if (!in_array($mode, [self::AUTO, self::ON, self::OFF], true)) {
            throw new \coding_exception('Bad badge mode ' . $mode);
        }
        $row = application::ensure($userid);
        $DB->set_field(application::TABLE, 'badgemode', $mode, ['id' => $row->id]);
        self::refresh($userid);
    }

    /**
     * Does the teacher wear the badge (as stored)?
     *
     * @param int $userid
     * @return bool
     */
    public static function has(int $userid): bool {
        global $DB;
        return (bool) $DB->get_field(application::TABLE, 'badge', ['userid' => $userid]);
    }

    /**
     * Refresh every teacher who has an application (the daily task).
     *
     * @return int how many wear the badge
     */
    public static function refresh_all(): int {
        global $DB;
        $n = 0;
        foreach ($DB->get_fieldset_select(application::TABLE, 'userid', '1 = 1') as $userid) {
            $n += self::refresh((int) $userid) ? 1 : 0;
        }
        return $n;
    }
}
