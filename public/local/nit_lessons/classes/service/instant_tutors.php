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

namespace local_nit_lessons\service;

use local_nit_lessons\entity\lesson;

/**
 * Instant lessons v2 — what the student browses before asking: the school years, the tutors of a
 * subject (and year) with rating, reliability, price, online now / usually online and favourite,
 * and the card shown after an instant lesson (rate, book again, favourite).
 *
 * Year-aware: a tutor teaches a year when they teach a course in that year's category (or below);
 * a tutor with no courses (private lessons only) counts for every year.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instant_tutors {
    /** @var int Weeks of presence history read for "usually online". */
    const HISTORY_WEEKS = 4;

    /** @var int An hour counts as "usual" when the tutor was online in it in at least this many weeks. */
    const USUAL_WEEKS = 3;

    /** @var instant_service */
    private $instant;

    /** @var teacher_service */
    private $teachers;

    public function __construct() {
        $this->instant = new instant_service();
        $this->teachers = new teacher_service();
    }

    /**
     * The academy's school years, and the one the student registered with (pre-selected).
     *
     * @param int $studentid
     * @return array {years: [{key, name}], current: key|''}
     */
    public static function years(int $studentid): array {
        global $CFG;
        if (!class_exists('\local_academy\local\academic_structure')) {
            return ['years' => [], 'current' => ''];
        }
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $years = array_map(fn($y) => ['key' => $y['key'], 'name' => $y['name']],
            \local_academy\local\academic_structure::years());
        $profile = profile_user_record($studentid, false);
        $field = \local_academy\local\academic_structure::USER_YEAR;
        $current = (string) ($profile->$field ?? '');
        return ['years' => $years, 'current' => in_array($current, array_column($years, 'key'), true) ? $current : ''];
    }

    /**
     * Course categories a tutor teaches in (with their parents), or null when they teach no course.
     *
     * @param int $teacherid
     * @return int[]|null
     */
    private static function teacher_categories(int $teacherid): ?array {
        global $DB;
        $paths = $DB->get_fieldset_sql(
            "SELECT DISTINCT cc.path
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid AND r.archetype IN ('teacher', 'editingteacher')
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
               JOIN {course} c ON c.id = ctx.instanceid
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE ra.userid = :uid", ['lvl' => CONTEXT_COURSE, 'uid' => $teacherid]);
        if (!$paths) {
            return null;
        }
        $ids = [];
        foreach ($paths as $path) {
            foreach (array_filter(explode('/', (string) $path), 'strlen') as $id) {
                $ids[(int) $id] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Does the tutor teach this year (a year key from years())? No year, or no courses, = yes.
     *
     * @param int $teacherid
     * @param string $yearkey
     * @return bool
     */
    public static function teaches_year(int $teacherid, string $yearkey): bool {
        if ($yearkey === '' || !class_exists('\local_academy\local\academic_structure')) {
            return true;
        }
        $catid = \local_academy\local\academic_structure::year_category($yearkey);
        $cats = self::teacher_categories($teacherid);
        return $catid === 0 || $cats === null || in_array($catid, $cats, true);
    }

    /**
     * The tutors of a subject for the student to choose from: online first, then favourites, rating
     * and reliability. Only tutors at or under $maxflex for this mode (0 = any) and, when a year is
     * given, tutors who teach it.
     *
     * @param int $studentid
     * @param string $subject raw subject (as stored)
     * @param string $yearkey '' = any year
     * @param string $mode normal | quick
     * @param int $maxflex 0 = any price
     * @return array[]
     */
    public function list(int $studentid, string $subject, string $yearkey = '', string $mode = instant_service::MODE_NORMAL,
            int $maxflex = 0): array {
        global $DB, $PAGE;
        $now = time();
        $trial = $this->instant->trial_eligible($studentid);
        $online = array_flip($this->instant->available_teachers($subject, $studentid, $now));
        $rows = [];
        foreach ($this->teachers->browse('') as $t) {
            $id = (int) $t['id'];
            if ($id === $studentid || !$this->teachers->teaches($id, $subject) || !self::teaches_year($id, $yearkey)) {
                continue;
            }
            $price = $this->instant->price($id, $mode, $trial, $now);
            if ($maxflex > 0 && $price > $maxflex) {
                continue;
            }
            $rows[$id] = ['t' => $t, 'price' => $price];
        }
        if (!$rows) {
            return [];
        }
        $ids = array_keys($rows);
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_teacher_aggregates($ids) : [];
        $favourites = array_flip(array_map('intval', $DB->get_fieldset_select('nit_favourite_tutor', 'teacherid',
            'studentid = :s', ['s' => $studentid])));
        $out = [];
        foreach ($rows as $id => $row) {
            $isonline = isset($online[$id]);
            $presence = $this->instant->presence($id, $now);
            $rel = $this->instant->reliability($id);
            $picture = new \user_picture($row['t']['user']);
            $picture->size = 100;
            $out[] = [
                'id' => $id,
                'name' => $row['t']['fullname'],
                'headline' => format_string($row['t']['headline']),
                'pictureurl' => $picture->get_url($PAGE)->out(false),
                'profileurl' => (new \moodle_url('/local/academy/teacher.php', ['id' => $id]))->out(false),
                'online' => $isonline,
                'busy' => !$isonline && $presence['online'] && $presence['busy'],
                'usually' => $isonline ? '' : self::usually_online_text($id),
                'rating' => round((float) ($ratings[$id]->avg ?? 0), 1),
                'ratingcount' => (int) ($ratings[$id]->count ?? 0),
                'reliability' => $rel['show'] ? $rel['acceptance'] : null,
                'flex' => $row['price'],
                'favourite' => isset($favourites[$id]),
                'bookurl' => (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book', 'teacher' => $id]))->out(false),
            ];
        }
        usort($out, fn($a, $b) => [(int) $b['online'], (int) $b['favourite'], $b['rating'], (int) $b['reliability']]
            <=> [(int) $a['online'], (int) $a['favourite'], $a['rating'], (int) $a['reliability']]);
        return $out;
    }

    /**
     * Hours a tutor is usually online (server time): weekday × hour slots they were online in at least
     * USUAL_WEEKS of the last HISTORY_WEEKS weeks, merged into ranges.
     *
     * @param int $teacherid
     * @param int|null $now
     * @return array[] each {day 0-6, from, to} (to is exclusive)
     */
    public static function usually_online(int $teacherid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $since = $now - self::HISTORY_WEEKS * WEEKSECS;
        $tz = \core_date::get_server_timezone_object();
        $weeks = [];
        foreach ($DB->get_records_select('nit_teacher_presence_log', 'teacherid = :t AND timeend >= :since',
                ['t' => $teacherid, 'since' => $since], '', 'id, timestart, timeend') as $log) {
            $start = max((int) $log->timestart, $since);
            $end = min((int) $log->timeend, $start + DAYSECS); // A day at most per session.
            for ($t = $start - $start % HOURSECS; $t < $end; $t += HOURSECS) {
                $dt = (new \DateTimeImmutable('@' . $t))->setTimezone($tz);
                $slot = (int) $dt->format('w') * 24 + (int) $dt->format('G');
                $weeks[$slot][intdiv($now - $t, WEEKSECS)] = true;
            }
        }
        $usual = [];
        foreach ($weeks as $slot => $seen) {
            if (count($seen) >= self::USUAL_WEEKS) {
                $usual[$slot] = true;
            }
        }
        ksort($usual);
        $ranges = [];
        foreach (array_keys($usual) as $slot) {
            $day = intdiv($slot, 24);
            $hour = $slot % 24;
            $last = end($ranges);
            if ($last && $last['day'] === $day && $last['to'] === $hour) {
                $ranges[key($ranges)]['to'] = $hour + 1;
            } else {
                $ranges[] = ['day' => $day, 'from' => $hour, 'to' => $hour + 1];
            }
        }
        return array_values($ranges);
    }

    /**
     * "Usually online: Sunday 18–22, Monday 18–21" (or '' without enough history).
     *
     * @param int $teacherid
     * @return string
     */
    public static function usually_online_text(int $teacherid): string {
        $parts = array_map(fn($r) => get_string('day' . $r['day'], 'local_nit_lessons') . ' '
            . sprintf('%02d:00–%02d:00', $r['from'], $r['to'] % 24), self::usually_online($teacherid));
        return $parts ? get_string('instant_usually', 'local_nit_lessons', implode('، ', array_slice($parts, 0, 4))) : '';
    }

    /**
     * After an instant lesson (started in the last day): rate the tutor, book again, favourite.
     *
     * @param int $studentid
     * @return array|null {lessonid, teacherid, teachername, rated, rateurl, bookagainurl, favourite}
     */
    public static function recent(int $studentid): ?array {
        global $DB, $CFG;
        $rows = $DB->get_records_select('nit_lesson', 'studentid = :s AND type = :type AND status IN (:a, :b)
                AND actual_start >= :since', ['s' => $studentid, 'type' => lesson::TYPE_INSTANT,
                'a' => lesson::STATUS_IN_PROGRESS, 'b' => lesson::STATUS_COMPLETED, 'since' => time() - DAYSECS],
            'actual_start DESC', 'id, teacherid', 0, 1);
        $l = reset($rows);
        if (!$l) {
            return null;
        }
        $tid = (int) $l->teacherid;
        $teacher = \core_user::get_user($tid);
        $dbman = $DB->get_manager();
        $rated = $dbman->table_exists('local_nit_reviews')
            && $DB->record_exists('local_nit_reviews', ['userid' => $studentid, 'teacherid' => $tid]);
        return [
            'lessonid' => (int) $l->id,
            'teacherid' => $tid,
            'teachername' => $teacher ? fullname($teacher) : '',
            'rated' => $rated,
            'rateurl' => file_exists($CFG->dirroot . '/local/nit_reviews/rate.php')
                ? (new \moodle_url('/local/nit_reviews/rate.php', ['teacherid' => $tid]))->out(false) : '',
            'bookagainurl' => (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book', 'teacher' => $tid]))->out(false),
            'favourite' => $DB->record_exists('nit_favourite_tutor', ['studentid' => $studentid, 'teacherid' => $tid]),
        ];
    }
}
