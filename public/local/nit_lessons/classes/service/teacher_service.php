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

use local_nit_core\base\service;
use local_nit_lessons\entity\lesson;
use local_nit_lessons\exception\lesson_exception;

/**
 * Teachers for live lessons: their booking profile (available, subjects, weekly hours), who
 * students can book, and the free one-hour slots of a teacher.
 *
 * Weekly hours are wall-clock times in the teacher's own timezone. A teacher without hours can be
 * booked from 08:00 to 20:00 every day.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_service extends service {

    /** Bookable window when a teacher set no hours. */
    const DEFAULT_WINDOW = ['08:00', '20:00'];

    /** How many days ahead students can book. */
    const BOOKING_DAYS = 14;

    /** Lesson statuses that hold a teacher's time. */
    const BUSY_STATUSES = ['pending', 'waiting_student', 'waiting_teacher', 'confirmed', 'in_progress'];

    /**
     * Is this user a teacher (teacher / editing teacher role in any course)?
     *
     * @param int $userid
     * @return bool
     */
    public function is_teacher(int $userid): bool {
        global $DB;
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :uid AND r.archetype IN ('teacher', 'editingteacher')",
            ['uid' => $userid]);
    }

    /**
     * A teacher's booking profile.
     *
     * @param int $userid
     * @return array{available:bool, headline:string, subjects:string[], hours:array}
     */
    /**
     * The site's subject list (Live lesson settings → Subjects, one per line,
     * {mlang} allowed), as stored values.
     *
     * @return string[]
     */
    public static function site_subjects(): array {
        $raw = get_config('local_nit_lessons', 'subjects');
        if ($raw === false || $raw === null) {
            $raw = self::default_subjects();
        }
        $list = [];
        foreach (preg_split('/\R/u', (string) $raw) as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $list, true)) {
                $list[] = \core_text::substr($line, 0, 255);
            }
        }
        return $list;
    }

    /**
     * The subjects a teacher can pick: the site's list — or, while it is empty,
     * the names of their courses — plus any subject they already teach.
     *
     * @param int $userid
     * @return string[]
     */
    public static function subject_options(int $userid): array {
        global $DB;
        $options = self::site_subjects();
        if (!$options && class_exists('\local_academy\teacher_manager')) {
            foreach (\local_academy\teacher_manager::get_teacher_courses($userid) as $course) {
                $options[] = (string) $course['fullname'];
            }
        }
        foreach ($DB->get_fieldset_select('nit_teacher_subject', 'subject', 'teacherid = ? ORDER BY id', [$userid]) as $s) {
            if (!in_array($s, $options, true)) {
                $options[] = $s;
            }
        }
        return array_values(array_unique($options));
    }

    /**
     * The subject list a new site starts with (Arabic + English).
     *
     * @return string
     */
    public static function default_subjects(): string {
        $subjects = [
            ['اللغة العربية', 'Arabic'], ['اللغة الإنجليزية', 'English'], ['اللغة الفرنسية', 'French'],
            ['الرياضيات', 'Mathematics'], ['العلوم', 'Science'], ['الفيزياء', 'Physics'], ['الكيمياء', 'Chemistry'],
            ['الأحياء', 'Biology'], ['الدراسات الاجتماعية', 'Social studies'], ['التاريخ', 'History'],
            ['الجغرافيا', 'Geography'], ['الفلسفة والمنطق', 'Philosophy and logic'], ['علم النفس والاجتماع', 'Psychology and sociology'],
            ['الجيولوجيا', 'Geology'], ['التربية الدينية', 'Religious education'], ['الحاسب الآلي', 'Computer science'],
        ];
        return implode("\n", array_map(fn($s) => '{mlang ar}' . $s[0] . '{mlang}{mlang en}' . $s[1] . '{mlang}', $subjects));
    }

    public function profile(int $userid): array {
        global $DB;
        $row = $DB->get_record('nit_teacher_profile', ['userid' => $userid]);
        $subjects = $DB->get_fieldset_select('nit_teacher_subject', 'subject', 'teacherid = ? ORDER BY id', [$userid]);
        $hours = [];
        foreach ($DB->get_records('nit_teacher_hour', ['teacherid' => $userid], 'dayofweek, starttime') as $h) {
            $hours[] = ['dayofweek' => (int) $h->dayofweek, 'starttime' => $h->starttime, 'endtime' => $h->endtime];
        }
        return [
            'available' => $row ? (bool) $row->available : false,
            'headline' => $row ? (string) $row->headline : '',
            'subjects' => array_values($subjects),
            'hours' => $hours,
        ];
    }

    /**
     * Save a teacher's booking profile (replaces subjects and hours).
     *
     * @param int $userid
     * @param bool $available
     * @param string $headline
     * @param string[] $subjects
     * @param array $hours [{dayofweek, starttime, endtime}]
     * @return void
     */
    public function save(int $userid, bool $available, string $headline, array $subjects, array $hours): void {
        global $DB;
        $options = self::subject_options($userid);
        $clean = [];
        foreach ($subjects as $subject) {
            $subject = trim(clean_param((string) $subject, PARAM_TEXT));
            if ($subject === '') {
                continue;
            }
            if (!in_array($subject, $options, true)) {
                throw new lesson_exception('err_badsubject');
            }
            if (!in_array(\core_text::strtolower($subject), array_map('core_text::strtolower', $clean), true)) {
                $clean[] = \core_text::substr($subject, 0, 255);
            }
        }
        if ($available && !$clean) {
            throw new lesson_exception('err_subjectsrequired');
        }
        $slots = [];
        foreach ($hours as $h) {
            $day = (int) ($h['dayofweek'] ?? -1);
            $start = (string) ($h['starttime'] ?? '');
            $end = (string) ($h['endtime'] ?? '');
            if ($day < 0 || $day > 6 || !self::valid_time($start) || !self::valid_time($end)
                    || self::minutes($end) - self::minutes($start) < lesson::DEFAULT_DURATION) {
                throw new lesson_exception('err_badhours');
            }
            $slots[] = (object) ['teacherid' => $userid, 'dayofweek' => $day, 'starttime' => $start, 'endtime' => $end];
        }

        $transaction = $DB->start_delegated_transaction();
        $row = $DB->get_record('nit_teacher_profile', ['userid' => $userid]);
        $record = (object) [
            'userid' => $userid,
            'available' => $available ? 1 : 0,
            'headline' => \core_text::substr(trim($headline), 0, 255),
            'timemodified' => time(),
        ];
        if ($row) {
            $record->id = $row->id;
            $DB->update_record('nit_teacher_profile', $record);
        } else {
            $DB->insert_record('nit_teacher_profile', $record);
        }
        $DB->delete_records('nit_teacher_subject', ['teacherid' => $userid]);
        foreach ($clean as $subject) {
            $DB->insert_record('nit_teacher_subject', (object) ['teacherid' => $userid, 'subject' => $subject]);
        }
        $DB->delete_records('nit_teacher_hour', ['teacherid' => $userid]);
        if ($slots) {
            $DB->insert_records('nit_teacher_hour', $slots);
        }
        $transaction->allow_commit();
    }

    /**
     * Teachers a student can book, optionally only those teaching a subject (contains, any case).
     *
     * @param string $subject
     * @return array
     */
    public function browse(string $subject = ''): array {
        global $DB;
        $params = [];
        $where = '';
        $subject = trim($subject);
        if ($subject !== '') {
            $where = ' AND EXISTS (SELECT 1 FROM {nit_teacher_subject} s2 WHERE s2.teacherid = u.id AND '
                . $DB->sql_like('s2.subject', ':subject', false) . ')';
            $params['subject'] = '%' . $DB->sql_like_escape($subject) . '%';
        }
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT $fields, tp.headline
               FROM {nit_teacher_profile} tp
               JOIN {user} u ON u.id = tp.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE tp.available = 1
                AND EXISTS (SELECT 1 FROM {nit_teacher_subject} s WHERE s.teacherid = u.id)
                    $where
           ORDER BY u.firstname, u.lastname", $params);
        $out = [];
        foreach ($rows as $u) {
            if (!$this->is_teacher((int) $u->id)) {
                continue;
            }
            $out[] = [
                'id' => (int) $u->id,
                'fullname' => fullname($u),
                'headline' => (string) $u->headline,
                'subjects' => $this->profile((int) $u->id)['subjects'],
                'user' => $u,
            ];
        }
        return $out;
    }

    /**
     * Can students book this teacher at all?
     *
     * @param int $teacherid
     * @return bool
     */
    public function bookable(int $teacherid): bool {
        global $DB;
        return $DB->record_exists('nit_teacher_profile', ['userid' => $teacherid, 'available' => 1])
            && $DB->record_exists('nit_teacher_subject', ['teacherid' => $teacherid])
            && $this->is_teacher($teacherid);
    }

    /**
     * Does the teacher give lessons in this subject (exact match, any case)?
     *
     * @param int $teacherid
     * @param string $subject
     * @return bool
     */
    public function teaches(int $teacherid, string $subject): bool {
        $subject = \core_text::strtolower(trim($subject));
        foreach ($this->profile($teacherid)['subjects'] as $s) {
            if (\core_text::strtolower($s) === $subject) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is a lesson starting at $time inside the teacher's weekly hours?
     *
     * @param int $teacherid
     * @param int $time
     * @param int $duration minutes
     * @return bool
     */
    public function within_hours(int $teacherid, int $time, int $duration = lesson::DEFAULT_DURATION): bool {
        $dt = (new \DateTimeImmutable('@' . $time))->setTimezone(self::timezone($teacherid));
        $start = (int) $dt->format('G') * 60 + (int) $dt->format('i');
        foreach ($this->intervals($teacherid, (int) $dt->format('w')) as [$from, $to]) {
            if ($start >= $from && $start + $duration <= $to) {
                return true;
            }
        }
        return false;
    }

    /**
     * Free one-hour slots for the next BOOKING_DAYS days, grouped by day.
     *
     * @param int $teacherid
     * @param int $exceptlessonid a lesson whose own time does not count as busy (rescheduling)
     * @return array [{date:int (midnight), slots:[{time:int, free:bool}]}]
     */
    public function slots(int $teacherid, int $exceptlessonid = 0): array {
        $tz = self::timezone($teacherid);
        $earliest = time() + (new settings_service())->get('min_booking_minutes') * MINSECS;
        $busy = $this->busy_times($teacherid, $exceptlessonid);
        $day = (new \DateTimeImmutable('today', $tz));
        $out = [];
        for ($i = 0; $i < self::BOOKING_DAYS; $i++, $day = $day->modify('+1 day')) {
            $slots = [];
            foreach ($this->intervals($teacherid, (int) $day->format('w')) as [$from, $to]) {
                for ($m = $from; $m + lesson::DEFAULT_DURATION <= $to; $m += lesson::DEFAULT_DURATION) {
                    $start = $day->setTime(intdiv($m, 60), $m % 60)->getTimestamp();
                    $end = $start + lesson::DEFAULT_DURATION * MINSECS;
                    $free = $start >= $earliest;
                    foreach ($busy as [$bstart, $bend]) {
                        if ($start < $bend && $end > $bstart) {
                            $free = false;
                            break;
                        }
                    }
                    $slots[] = ['time' => $start, 'free' => $free];
                }
            }
            usort($slots, fn($a, $b) => $a['time'] <=> $b['time']);
            if (array_filter($slots, fn($s) => $s['free'])) {
                $out[] = ['date' => $day->getTimestamp(), 'slots' => $slots];
            }
        }
        return $out;
    }

    /**
     * Times the teacher is already taken: [[start, end], ...].
     *
     * @param int $teacherid
     * @param int $exceptlessonid
     * @return array
     */
    public function busy_times(int $teacherid, int $exceptlessonid = 0): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(self::BUSY_STATUSES, SQL_PARAMS_NAMED);
        $params['tid'] = $teacherid;
        $params['except'] = $exceptlessonid;
        $out = [];
        foreach ($DB->get_records_select('nit_lesson', "teacherid = :tid AND id <> :except AND status $insql",
                $params, '', 'id, requested_time, confirmed_time, duration') as $l) {
            $start = (int) $l->confirmed_time > 0 ? (int) $l->confirmed_time : (int) $l->requested_time;
            $out[] = [$start, $start + (int) $l->duration * MINSECS];
        }
        return $out;
    }

    /**
     * Bookable intervals of one weekday, in minutes from midnight.
     *
     * @param int $teacherid
     * @param int $dayofweek 0 = Sunday
     * @return array [[from, to], ...]
     */
    private function intervals(int $teacherid, int $dayofweek): array {
        $hours = $this->profile($teacherid)['hours'];
        if (!$hours) {
            return [[self::minutes(self::DEFAULT_WINDOW[0]), self::minutes(self::DEFAULT_WINDOW[1])]];
        }
        $out = [];
        foreach ($hours as $h) {
            if ($h['dayofweek'] === $dayofweek) {
                $out[] = [self::minutes($h['starttime']), self::minutes($h['endtime'])];
            }
        }
        return $out;
    }

    /**
     * The teacher's timezone (their profile setting, else the site's).
     *
     * @param int $teacherid
     * @return \DateTimeZone
     */
    public static function timezone(int $teacherid): \DateTimeZone {
        $teacher = \core_user::get_user($teacherid);
        return $teacher ? \core_date::get_user_timezone_object($teacher) : \core_date::get_server_timezone_object();
    }

    /**
     * "HH:MM" is a valid time of day.
     *
     * @param string $t
     * @return bool
     */
    private static function valid_time(string $t): bool {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
    }

    /**
     * "HH:MM" to minutes from midnight.
     *
     * @param string $t
     * @return int
     */
    private static function minutes(string $t): int {
        [$h, $m] = array_map('intval', explode(':', $t) + [0, 0]);
        return $h * 60 + $m;
    }
}
