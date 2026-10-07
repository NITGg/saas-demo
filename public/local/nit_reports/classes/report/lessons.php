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

namespace local_nit_reports\report;

use local_nit_reports\data;
use local_nit_reports\scope;

/**
 * Live lessons and attendance, two views:
 *  - private lessons (Flex, local_nit_lessons): each lesson with its student,
 *    teacher, status and Flex; attendance rate = completed / (completed + student
 *    absent);
 *  - live sessions (local_academysessions): each session with how many invited
 *    students joined.
 * The period is the lesson's (confirmed or requested) time / the session's start.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessons extends base {

    /** Private lesson statuses. */
    private const LESSON_STATUSES = ['pending', 'waiting_student', 'waiting_teacher', 'confirmed', 'in_progress', 'completed',
        'student_absent', 'teacher_absent', 'cancelled', 'cancelled_teacher', 'rejected'];

    public static function key(): string {
        return 'lessons';
    }

    public static function level(): string {
        return scope::SITE;
    }

    /**
     * Which data exists.
     *
     * @return array{private:bool, live:bool}
     */
    private static function tables(): array {
        global $DB;
        $dbman = $DB->get_manager();
        return ['private' => $dbman->table_exists('nit_lesson'), 'live' => $dbman->table_exists('academy_live_sessions')];
    }

    public static function available(): bool {
        $t = self::tables();
        return $t['private'] || $t['live'];
    }

    public function filters(): array {
        return ['period', 'user', 'status', 'q'];
    }

    public function view_options(): array {
        $t = self::tables();
        return array_filter(['private' => $t['private'] ? self::str('view_privatelessons') : null,
            'live' => $t['live'] ? self::str('view_livesessions') : null]);
    }

    public function status_options(): array {
        if ($this->view() === 'live') {
            return ['scheduled' => self::str('live_scheduled'), 'live' => self::str('live_live'),
                'ended' => self::str('live_ended'), 'cancelled' => self::str('live_cancelled')];
        }
        $out = [];
        foreach (self::LESSON_STATUSES as $st) {
            $out[$st] = self::str('lesson_' . $st);
        }
        return $out;
    }

    public function user_options(): array {
        global $DB;
        $ids = $this->view() === 'live'
            ? $DB->get_fieldset_sql('SELECT DISTINCT teacherid FROM {academy_live_sessions}')
            : $DB->get_fieldset_sql('SELECT DISTINCT teacherid FROM {nit_lesson}');
        $names = data::user_names($ids);
        asort($names);
        return $names;
    }

    public function columns(): array {
        if ($this->view() === 'live') {
            return ['session' => self::str('col_session'), 'course' => get_string('course'), 'teacher' => self::str('col_teacher'),
                'start' => self::str('col_start'), 'duration' => self::str('col_duration'), 'status' => get_string('status'),
                'invited' => self::str('col_invited'), 'attended' => self::str('col_attended'),
                'teacherjoined' => self::str('col_teacherjoined')];
        }
        return ['time' => self::str('col_start'), 'student' => self::str('col_student'), 'teacher' => self::str('col_teacher'),
            'subject' => self::str('col_subject'), 'duration' => self::str('col_duration'), 'status' => get_string('status'),
            'flex' => self::str('col_flexstate'), 'actual' => self::str('col_actualminutes')];
    }

    /**
     * Private lessons matching the filters: "FROM … WHERE …" over l, s (student), t (teacher).
     *
     * @return array{0:string, 1:array}
     */
    private function from_private(): array {
        [$period, $params] = $this->f->period_sql('CASE WHEN l.confirmed_time > 0 THEN l.confirmed_time ELSE l.requested_time END');
        $where = [$period];
        if ($this->f->status !== '') {
            $where[] = 'l.status = :fst';
            $params['fst'] = $this->f->status;
        }
        if ($this->f->userid) {
            $where[] = 'l.teacherid = :fteacher';
            $params['fteacher'] = $this->f->userid;
        }
        if ($this->f->q !== '') {
            [$search, $sp] = data::user_search_sql('s', $this->f->q);
            $where[] = $search;
            $params += $sp;
        }
        return ['FROM {nit_lesson} l JOIN {user} s ON s.id = l.studentid JOIN {user} t ON t.id = l.teacherid
                WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Live sessions matching the filters: "FROM … WHERE …" over ls, c.
     *
     * @return array{0:string, 1:array}
     */
    private function from_live(): array {
        global $DB;
        [$period, $params] = $this->f->period_sql('ls.start_time');
        $where = [$period];
        if ($this->f->status !== '') {
            $where[] = 'ls.status = :fst';
            $params['fst'] = $this->f->status;
        }
        if ($this->f->userid) {
            $where[] = 'ls.teacherid = :fteacher';
            $params['fteacher'] = $this->f->userid;
        }
        if ($this->f->q !== '') {
            $where[] = $DB->sql_like('ls.title', ':lq', false);
            $params['lq'] = '%' . $DB->sql_like_escape($this->f->q) . '%';
        }
        return ['FROM {academy_live_sessions} ls LEFT JOIN {course} c ON c.id = ls.courseid
                WHERE ' . implode(' AND ', $where), $params];
    }

    public function summary(): array {
        global $DB;
        if ($this->view() === 'live') {
            [$from, $params] = $this->from_live();
            $by = $DB->get_records_sql_menu("SELECT ls.status, COUNT(1) $from GROUP BY ls.status", $params);
            $att = $DB->get_record_sql("SELECT COUNT(DISTINCT ss.id) AS invited, COUNT(DISTINCT sa.id) AS attended
                FROM {academy_session_students} ss
                LEFT JOIN {academy_session_attendance} sa ON sa.sessionid = ss.sessionid AND sa.userid = ss.userid
               WHERE ss.sessionid IN (SELECT ls.id $from)", $params);
            return [
                ['label' => self::str('card_sessions'), 'value' => self::num(array_sum($by))],
                ['label' => self::str('live_ended'), 'value' => self::num($by['ended'] ?? 0)],
                ['label' => self::str('live_cancelled'), 'value' => self::num($by['cancelled'] ?? 0)],
                ['label' => self::str('card_attendance'), 'value' => $att && $att->invited
                    ? self::pct(100 * $att->attended / $att->invited) : '—'],
            ];
        }
        [$from, $params] = $this->from_private();
        $by = $DB->get_records_sql_menu("SELECT l.status, COUNT(1) $from GROUP BY l.status", $params);
        $completed = (int) ($by['completed'] ?? 0);
        $absent = (int) ($by['student_absent'] ?? 0);
        return [
            ['label' => self::str('card_lessons'), 'value' => self::num(array_sum($by))],
            ['label' => self::str('lesson_completed'), 'value' => self::num($completed)],
            ['label' => self::str('lesson_student_absent'), 'value' => self::num($absent)],
            ['label' => self::str('lesson_teacher_absent'), 'value' => self::num($by['teacher_absent'] ?? 0)],
            ['label' => self::str('card_cancelled'), 'value' => self::num(($by['cancelled'] ?? 0) + ($by['cancelled_teacher'] ?? 0)
                + ($by['rejected'] ?? 0))],
            ['label' => self::str('card_attendance'), 'value' => ($completed + $absent)
                ? self::pct(100 * $completed / ($completed + $absent)) : '—'],
        ];
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        $offset = $perpage ? $page * $perpage : 0;
        if ($this->view() === 'live') {
            [$from, $params] = $this->from_live();
            $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
            $list = $DB->get_records_sql("SELECT ls.id, ls.title, ls.teacherid, ls.start_time, ls.duration, ls.status,
                        ls.teacher_joined_at, c.fullname AS coursename,
                        (SELECT COUNT(1) FROM {academy_session_students} ss WHERE ss.sessionid = ls.id) AS invited,
                        (SELECT COUNT(1) FROM {academy_session_attendance} sa WHERE sa.sessionid = ls.id) AS attended
                   $from ORDER BY ls.start_time DESC, ls.id DESC", $params, $offset, $perpage);
            $teachers = data::user_names(array_map(fn($l) => $l->teacherid, $list));
            $rows = [];
            foreach ($list as $l) {
                $rows[] = [
                    'session' => self::cname($l->title),
                    'course' => self::cname($l->coursename),
                    'teacher' => $teachers[(int) $l->teacherid] ?? '—',
                    'start' => self::date((int) $l->start_time, true),
                    'duration' => self::str('minutes', (int) $l->duration),
                    'status' => self::str('live_' . $l->status),
                    'invited' => self::num($l->invited),
                    'attended' => self::num($l->attended) . ($l->invited ? ' (' . self::pct(100 * $l->attended / $l->invited) . ')' : ''),
                    'teacherjoined' => $l->teacher_joined_at ? self::date((int) $l->teacher_joined_at, true) : get_string('no'),
                ];
            }
            return ['total' => $total, 'rows' => $rows];
        }
        [$from, $params] = $this->from_private();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $sn = \core_user\fields::for_name()->get_sql('s', false, 's_', '', false)->selects;
        $tn = \core_user\fields::for_name()->get_sql('t', false, 't_', '', false)->selects;
        $list = $DB->get_records_sql("SELECT l.id, l.subject, l.status, l.flex_state, l.duration, l.requested_time,
                    l.confirmed_time, l.actual_start, l.actual_end, $sn, $tn
               $from ORDER BY CASE WHEN l.confirmed_time > 0 THEN l.confirmed_time ELSE l.requested_time END DESC, l.id DESC",
            $params, $offset, $perpage);
        $person = function(\stdClass $r, string $prefix): string {
            $u = new \stdClass();
            foreach (\core_user\fields::get_name_fields() as $f) {
                $u->$f = $r->{$prefix . $f} ?? '';
            }
            return fullname($u);
        };
        $rows = [];
        foreach ($list as $l) {
            $actual = $l->actual_start && $l->actual_end ? (int) round(($l->actual_end - $l->actual_start) / MINSECS) : null;
            $rows[] = [
                'time' => self::date((int) ($l->confirmed_time ?: $l->requested_time), true),
                'student' => $person($l, 's_'),
                'teacher' => $person($l, 't_'),
                'subject' => self::cname($l->subject),
                'duration' => self::str('minutes', (int) $l->duration),
                'status' => self::str('lesson_' . $l->status),
                'flex' => self::str('flex_' . $l->flex_state),
                'actual' => $actual === null ? '—' : self::str('minutes', $actual),
            ];
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
