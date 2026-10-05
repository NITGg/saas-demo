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

namespace local_parent;

defined('MOODLE_INTERNAL') || die();

/**
 * The parent dashboard: find a student by the two phone numbers a parent types,
 * and build that student's per-lecture report.
 *
 * There is no parent account. A parent proves who they follow by knowing both
 * the student's own phone (user phone1 / phone2) and one of the guardian phones
 * the student registered (the parent / father / mother phone profile fields).
 * Wrong pairs are counted per client IP so the pair cannot be brute-forced.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {

    /** @var int wrong phone pairs allowed per IP inside the cache TTL (db/caches.php). */
    const MAX_FAILURES = 10;

    /** @var string[] video activities, as tracked by local_nit_videoprogress. */
    const VIDEO_MODULES = ['vimeo', 'vdocipher'];

    /** @var int how many trailing digits pick the SQL candidates before the exact compare. */
    const TAIL_DIGITS = 8;

    /**
     * Normalize a phone number so 010…, 0020…, +2010… and 2010… all match.
     *
     * Keeps digits only, drops an international 00 prefix, strips a local trunk 0
     * and makes sure the default country code is present.
     *
     * @param string $raw the number as typed
     * @return string digits only with country code, or '' if there was nothing usable
     */
    public static function normalize_phone(string $raw): string {
        $cc = (string) (get_config('local_parent', 'countrycode') ?: '20');
        $d = (string) preg_replace('/\D+/', '', $raw);
        if ($d === '') {
            return '';
        }
        // International 00 prefix → drop it (00 20 10… → 2010…).
        if (strpos($d, '00') === 0) {
            $d = substr($d, 2);
        }
        // Already starts with the country code and is long enough to be a full number.
        if ($cc !== '' && strpos($d, $cc) === 0 && strlen($d) >= strlen($cc) + 7) {
            return $d;
        }
        // Otherwise treat it as a local number: strip the trunk 0 and prepend the code.
        $d = ltrim($d, '0');
        return $d === '' ? '' : $cc . $d;
    }

    /**
     * Whether a typed value looks like a phone number (digits, spaces, + - ( ), 7+ digits).
     *
     * @param string $raw
     * @return bool
     */
    public static function valid_phone(string $raw): bool {
        $raw = trim($raw);
        return (bool) preg_match('/^[+]?[0-9\s\-()]{7,25}$/', $raw)
            && strlen((string) preg_replace('/\D+/', '', $raw)) >= 7;
    }

    /**
     * The student profile fields that hold a guardian phone.
     *
     * @return string[] profile field shortnames
     */
    public static function guardian_fields(): array {
        $main = (string) (get_config('local_parent', 'parentphonefield') ?: 'parentphone');
        return array_values(array_unique([$main, 'fatherphone', 'motherphone']));
    }

    /**
     * The student whose own phone is $childphone and who registered $parentphone
     * as a guardian phone.
     *
     * @param string $childphone the student's phone as the parent typed it
     * @param string $parentphone the parent's phone as typed
     * @return int|null the student's user id, or null when no student matches both
     */
    public static function find_student(string $childphone, string $parentphone): ?int {
        global $DB;

        $child = self::normalize_phone($childphone);
        $parent = self::normalize_phone($parentphone);
        if (!self::valid_phone($childphone) || !self::valid_phone($parentphone) || $child === '' || $parent === '') {
            return null;
        }

        // Candidates: active users whose phone ends with the same digits, whatever
        // spaces or dashes they were saved with; the exact match is checked below.
        $strip = static function(string $field): string {
            foreach ([' ', '-', '(', ')', '+'] as $char) {
                $field = "REPLACE($field, '$char', '')";
            }
            return $field;
        };
        $tail = '%' . substr($child, -self::TAIL_DIGITS);
        $sql = "SELECT id, phone1, phone2
                  FROM {user}
                 WHERE deleted = 0 AND suspended = 0
                   AND (" . $DB->sql_like($strip('phone1'), ':p1') . "
                        OR " . $DB->sql_like($strip('phone2'), ':p2') . ")
              ORDER BY id";
        $candidates = $DB->get_records_sql($sql, ['p1' => $tail, 'p2' => $tail], 0, 50);

        [$insql, $params] = $DB->get_in_or_equal(self::guardian_fields(), SQL_PARAMS_NAMED);
        foreach ($candidates as $user) {
            if (self::normalize_phone((string) $user->phone1) !== $child
                    && self::normalize_phone((string) $user->phone2) !== $child) {
                continue;
            }
            $phones = $DB->get_fieldset_sql("SELECT d.data
                                               FROM {user_info_data} d
                                               JOIN {user_info_field} f ON f.id = d.fieldid
                                              WHERE d.userid = :userid AND f.shortname $insql",
                $params + ['userid' => $user->id]);
            foreach ($phones as $phone) {
                if (self::normalize_phone((string) $phone) === $parent) {
                    return (int) $user->id;
                }
            }
        }
        return null;
    }

    /**
     * The cache key for one client.
     *
     * @param string $ip
     * @return string
     */
    private static function client_key(string $ip): string {
        return sha1($ip);
    }

    /**
     * Whether this client has used up its wrong attempts.
     *
     * @param string $ip the client address (getremoteaddr())
     * @return bool
     */
    public static function is_blocked(string $ip): bool {
        $count = \cache::make('local_parent', 'failures')->get(self::client_key($ip));
        return (int) $count >= self::MAX_FAILURES;
    }

    /**
     * Count one wrong phone pair for this client.
     *
     * @param string $ip the client address (getremoteaddr())
     */
    public static function record_failure(string $ip): void {
        $cache = \cache::make('local_parent', 'failures');
        $key = self::client_key($ip);
        $cache->set($key, (int) $cache->get($key) + 1);
    }

    /**
     * The student's courses with every lecture's videos, homework and exams.
     *
     * Courses are the ones the student is actively enrolled in. A lecture is a
     * course section; the general section is listed only when it holds a video,
     * homework (assignment) or exam (quiz). Only items the student can see are
     * counted, so content that is not released yet is not reported as missed.
     *
     * @param int $studentid
     * @return array<int, array{id:int, name:string, sections:array}>
     */
    public static function report(int $studentid): array {
        $out = [];
        foreach (enrol_get_all_users_courses($studentid, true, 'id, fullname') as $course) {
            $out[] = [
                'id' => (int) $course->id,
                'name' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'sections' => self::course_sections($course, $studentid),
            ];
        }
        return $out;
    }

    /**
     * One course's lectures with the student's result on each item.
     *
     * @param \stdClass $course
     * @param int $studentid
     * @return array<int, array{name:string, started:int, videos:array, homework:array, exams:array}>
     */
    public static function course_sections(\stdClass $course, int $studentid): array {
        $modinfo = get_fast_modinfo($course, $studentid);
        $results = self::results($course, $studentid);

        $out = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible || $section->is_delegated()) {
                continue; // A subsection is reported inside the lecture that holds it.
            }
            $items = ['videos' => [], 'homework' => [], 'exams' => []];
            $started = 0;
            foreach (self::section_cms($modinfo, $section) as $cm) {
                $kind = self::kind($cm->modname);
                if ($kind === null || !$cm->uservisible) {
                    continue;
                }
                $items[$kind][] = ['name' => format_string($cm->name, true, ['context' => $cm->context])]
                    + ($results[$cm->id] ?? ['state' => 'absent', 'percent' => null]);
                $started = $started ? min($started, (int) $cm->added) : (int) $cm->added;
            }
            $empty = !$items['videos'] && !$items['homework'] && !$items['exams'];
            if ($empty && (int) $section->section === 0) {
                continue;
            }
            $out[] = ['name' => get_section_name($course, $section), 'started' => $started] + $items;
        }
        return $out;
    }

    /**
     * The activities of a section in page order, including those of its subsections.
     *
     * @param \course_modinfo $modinfo
     * @param \section_info $section
     * @return \cm_info[]
     */
    private static function section_cms(\course_modinfo $modinfo, \section_info $section): array {
        $out = [];
        foreach ($modinfo->get_sections()[$section->section] ?? [] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname === 'subsection') {
                $delegated = $cm->get_delegated_section_info();
                if ($delegated && $delegated->uservisible) {
                    array_push($out, ...self::section_cms($modinfo, $delegated));
                }
                continue;
            }
            $out[] = $cm;
        }
        return $out;
    }

    /**
     * Which report column an activity belongs to.
     *
     * @param string $modname
     * @return string|null videos | homework | exams, or null when it is not reported
     */
    private static function kind(string $modname): ?string {
        if (in_array($modname, self::VIDEO_MODULES, true)) {
            return 'videos';
        }
        return ['assign' => 'homework', 'quiz' => 'exams'][$modname] ?? null;
    }

    /**
     * The student's result on every reported activity of a course.
     *
     * - video: the watched percent (local_nit_videoprogress); 0% is "absent".
     * - homework / exam: the gradebook grade as a percent once released; a
     *   submitted assignment or finished quiz without a released grade is
     *   "pending"; anything else is "absent".
     *
     * @param \stdClass $course
     * @param int $studentid
     * @return array<int, array{state:string, percent:?float}> keyed by cmid
     */
    private static function results(\stdClass $course, int $studentid): array {
        global $DB;
        $out = [];

        $watched = class_exists('\local_nit_videoprogress\progress')
            ? \local_nit_videoprogress\progress::course_percents($studentid, (int) $course->id) : [];
        foreach ($watched as $cmid => $percent) {
            if ($percent > 0) {
                $out[$cmid] = ['state' => 'done', 'percent' => (float) $percent];
            }
        }

        // Released gradebook grades of the course's assignments and quizzes.
        $grades = $DB->get_records_sql(
            "SELECT cm.id AS cmid, gi.grademin, gi.grademax, gg.finalgrade
               FROM {grade_items} gi
               JOIN {modules} m ON m.name = gi.itemmodule
               JOIN {course_modules} cm ON cm.instance = gi.iteminstance AND cm.module = m.id AND cm.course = gi.courseid
               JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
              WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gi.itemnumber = 0
                AND gi.itemmodule IN ('assign', 'quiz')
                AND gi.hidden = 0 AND gg.hidden = 0 AND gg.finalgrade IS NOT NULL",
            ['userid' => $studentid, 'courseid' => $course->id]);
        foreach ($grades as $g) {
            $range = (float) $g->grademax - (float) $g->grademin;
            $out[$g->cmid] = [
                'state' => 'done',
                'percent' => $range > 0 ? round(((float) $g->finalgrade - (float) $g->grademin) / $range * 100, 1) : null,
            ];
        }

        // Handed in, but no grade released yet.
        $pending = $DB->get_fieldset_sql(
            "SELECT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
               JOIN {assign_submission} s ON s.assignment = cm.instance
              WHERE cm.course = :courseid AND s.userid = :userid AND s.latest = 1 AND s.status = 'submitted'
             UNION
             SELECT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
               JOIN {quiz_attempts} qa ON qa.quiz = cm.instance
              WHERE cm.course = :courseid2 AND qa.userid = :userid2 AND qa.preview = 0 AND qa.state = 'finished'",
            ['courseid' => $course->id, 'userid' => $studentid, 'courseid2' => $course->id, 'userid2' => $studentid]);
        foreach ($pending as $cmid) {
            $out[$cmid] = $out[$cmid] ?? ['state' => 'pending', 'percent' => null];
        }
        return $out;
    }
}
