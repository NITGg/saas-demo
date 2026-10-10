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
                'absent' => self::str('col_absent'), 'avgminutes' => self::str('col_avgminutes'),
                'teacherjoined' => self::str('col_teacherjoined'), 'late' => self::str('col_teacherlate')];
        }
        $cols = ['requested' => self::str('col_requested_at'), 'time' => self::str('col_start'),
            'student' => self::str('col_student'), 'teacher' => self::str('col_teacher'),
            'subject' => self::str('col_subject'), 'package' => self::str('col_package'),
            'duration' => self::str('col_duration'), 'status' => get_string('status'), 'reason' => self::str('col_reason'),
            'flex' => self::str('col_flexstate'), 'actualstart' => self::str('col_actualstart'),
            'actualend' => self::str('col_actualend'), 'actual' => self::str('col_actualminutes')];
        if (self::has_earnings()) {
            $cols['share'] = self::str('col_teachershare');
        }
        return $cols;
    }

    public function sortable(): array {
        global $DB;
        if ($this->view() === 'live') {
            return ['session' => 'ls.title', 'course' => 'c.fullname', 'start' => 'ls.start_time', 'duration' => 'ls.duration',
                'status' => 'ls.status', 'teacherjoined' => 'ls.teacher_first_join',
                'late' => 'CASE WHEN ls.teacher_first_join > ls.start_time THEN ls.teacher_first_join - ls.start_time ELSE 0 END'];
        }
        return ['requested' => 'l.requested_time', 'time' => 'l.confirmed_time',
            'student' => $DB->sql_fullname('s.firstname', 's.lastname'), 'teacher' => $DB->sql_fullname('t.firstname', 't.lastname'),
            'subject' => 'l.subject', 'duration' => 'l.duration', 'status' => 'l.status',
            'actualstart' => 'l.actual_start', 'actualend' => 'l.actual_end',
            'actual' => 'CASE WHEN l.actual_end > l.actual_start THEN l.actual_end - l.actual_start ELSE 0 END'];
    }

    /**
     * Whether teacher earnings are recorded (local_nit_finance).
     *
     * @return bool
     */
    private static function has_earnings(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_earning');
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
                ['id' => 'card_sessions', 'label' => self::str('card_sessions'), 'value' => self::num(array_sum($by))],
                ['id' => 'live_ended', 'label' => self::str('live_ended'), 'value' => self::num($by['ended'] ?? 0)],
                ['id' => 'live_cancelled', 'label' => self::str('live_cancelled'), 'value' => self::num($by['cancelled'] ?? 0)],
                ['id' => 'card_attendance', 'label' => self::str('card_attendance'), 'value' => $att && $att->invited
                    ? self::pct(100 * $att->attended / $att->invited) : '—'],
            ];
        }
        [$from, $params] = $this->from_private();
        $by = $DB->get_records_sql_menu("SELECT l.status, COUNT(1) $from GROUP BY l.status", $params);
        $completed = (int) ($by['completed'] ?? 0);
        $absent = (int) ($by['student_absent'] ?? 0);
        return [
            ['id' => 'card_lessons', 'label' => self::str('card_lessons'), 'value' => self::num(array_sum($by))],
            ['id' => 'lesson_completed', 'label' => self::str('lesson_completed'), 'value' => self::num($completed)],
            ['id' => 'lesson_student_absent', 'label' => self::str('lesson_student_absent'), 'value' => self::num($absent)],
            ['id' => 'lesson_teacher_absent', 'label' => self::str('lesson_teacher_absent'), 'value' => self::num($by['teacher_absent'] ?? 0)],
            ['id' => 'card_cancelled', 'label' => self::str('card_cancelled'), 'value' => self::num(($by['cancelled'] ?? 0) + ($by['cancelled_teacher'] ?? 0)
                + ($by['rejected'] ?? 0))],
            ['id' => 'card_attendance', 'label' => self::str('card_attendance'), 'value' => ($completed + $absent)
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
                        ls.teacher_first_join, c.fullname AS coursename,
                        (SELECT COUNT(1) FROM {academy_session_students} ss WHERE ss.sessionid = ls.id) AS invited,
                        (SELECT COUNT(DISTINCT sa.userid) FROM {academy_session_attendance} sa
                           JOIN {academy_session_students} ssa ON ssa.sessionid = sa.sessionid AND ssa.userid = sa.userid
                          WHERE sa.sessionid = ls.id) AS attended,
                        (SELECT COUNT(1) FROM {academy_session_students} ss2 WHERE ss2.sessionid = ls.id AND NOT EXISTS (
                            SELECT 1 FROM {academy_session_attendance} sa2
                             WHERE sa2.sessionid = ss2.sessionid AND sa2.userid = ss2.userid)) AS absent,
                        (SELECT AVG(sa3.duration_seconds) FROM {academy_session_attendance} sa3
                           JOIN {academy_session_students} ss3 ON ss3.sessionid = sa3.sessionid AND ss3.userid = sa3.userid
                          WHERE sa3.sessionid = ls.id AND sa3.duration_seconds > 0) AS avgseconds
                   $from ORDER BY " . $this->order_sql('ls.start_time DESC, ls.id DESC'), $params, $offset, $perpage);
            $teachers = data::user_names(array_map(fn($l) => $l->teacherid, $list));
            $rows = [];
            foreach ($list as $l) {
                $late = $l->teacher_first_join && $l->teacher_first_join > $l->start_time
                    ? (int) floor(($l->teacher_first_join - $l->start_time) / MINSECS) : 0;
                $rows[] = [
                    'session' => self::cname($l->title),
                    'course' => self::cname($l->coursename),
                    'teacher' => $teachers[(int) $l->teacherid] ?? '—',
                    'start' => self::date((int) $l->start_time, true),
                    'duration' => self::str('minutes', (int) $l->duration),
                    'status' => self::str('live_' . $l->status),
                    'invited' => self::num($l->invited),
                    'attended' => self::num($l->attended) . ($l->invited ? ' (' . self::pct(100 * $l->attended / $l->invited) . ')' : ''),
                    'absent' => $l->status === 'ended' ? self::num($l->absent) : '—',
                    'avgminutes' => $l->avgseconds ? self::str('minutes', (int) round($l->avgseconds / MINSECS)) : '—',
                    'teacherjoined' => $l->teacher_first_join ? self::date((int) $l->teacher_first_join, true) : get_string('no'),
                    'late' => $l->teacher_first_join ? ($late ? self::str('minutes', $late) : self::str('ontime')) : '—',
                ];
            }
            return ['total' => $total, 'rows' => $rows];
        }
        [$from, $params] = $this->from_private();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $sn = \core_user\fields::for_name()->get_sql('s', false, 's_', '', false)->selects;
        $tn = \core_user\fields::for_name()->get_sql('t', false, 't_', '', false)->selects;
        $list = $DB->get_records_sql("SELECT l.id, l.subject, l.status, l.flex_state, l.duration, l.requested_time,
                    l.confirmed_time, l.actual_start, l.actual_end, l.purchaseid, l.cancel_reason, l.reject_reason, $sn, $tn
               $from ORDER BY " . $this->order_sql('CASE WHEN l.confirmed_time > 0 THEN l.confirmed_time ELSE l.requested_time END DESC,
                    l.id DESC'),
            $params, $offset, $perpage);
        $packages = $shares = [];
        if ($list) {
            $purchases = array_values(array_unique(array_filter(array_map(fn($l) => (int) $l->purchaseid, $list))));
            if ($purchases && $DB->get_manager()->table_exists('nit_package_purchase')) {
                [$in, $pp] = $DB->get_in_or_equal($purchases, SQL_PARAMS_NAMED, 'lp');
                $packages = $DB->get_records_sql_menu("SELECT pp.id, pk.name FROM {nit_package_purchase} pp
                    JOIN {nit_package} pk ON pk.id = pp.packageid WHERE pp.id $in", $pp);
            }
            if (self::has_earnings()) {
                [$in, $lp] = $DB->get_in_or_equal(array_keys($list), SQL_PARAMS_NAMED, 'le');
                $shares = $DB->get_records_sql_menu("SELECT lessonid, SUM(teacher_amount_minor) FROM {nit_earning}
                    WHERE lessonid $in AND status = 'active' GROUP BY lessonid", $lp);
            }
        }
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
            $row = [
                'requested' => self::date((int) $l->requested_time, true),
                'time' => self::date((int) ($l->confirmed_time ?: $l->requested_time), true),
                'student' => $person($l, 's_'),
                'teacher' => $person($l, 't_'),
                'subject' => self::cname($l->subject),
                'package' => isset($packages[$l->purchaseid]) ? self::cname($packages[$l->purchaseid]) : '—',
                'duration' => self::str('minutes', (int) $l->duration),
                'status' => self::str('lesson_' . $l->status),
                'reason' => trim((string) ($l->cancel_reason ?: $l->reject_reason)) ?: '—',
                'flex' => self::str('flex_' . $l->flex_state),
                'actualstart' => self::date((int) $l->actual_start, true),
                'actualend' => self::date((int) $l->actual_end, true),
                'actual' => $actual === null ? '—' : self::str('minutes', $actual),
            ];
            if (self::has_earnings()) {
                $row['share'] = isset($shares[$l->id]) ? data::minor((int) $shares[$l->id]) : '—';
            }
            $rows[] = $row;
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
