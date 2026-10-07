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
 * Student results: one row per student per course — quizzes taken out of the
 * course's quizzes, average quiz result, content completion and video watching.
 * The current students by default; the status filter lists the ones who left
 * instead, with what they did before leaving.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_results extends base {

    public static function key(): string {
        return 'student_results';
    }

    public static function level(): string {
        return scope::TEACHING;
    }

    public function filters(): array {
        return ['course', 'status', 'q'];
    }

    public function status_options(): array {
        return ['left' => self::str('member_left')];
    }

    public function status_all_label(): string {
        return self::str('member_current');
    }

    public function sortable(): array {
        global $DB;
        return ['name' => $DB->sql_fullname('u.firstname', 'u.lastname'), 'email' => 'u.email', 'course' => 'co.fullname'];
    }

    /**
     * The (student, course) pairs listed: current students, or the ones who left.
     *
     * @return array{0:string, 1:array}
     */
    private function pairs(): array {
        [$cwhere, $cparams] = $this->course_where('c.id');
        return $this->f->status === 'left' ? data::left_sql($cwhere, $cparams)
            : data::members_sql(data::student_roles(), $cwhere, $cparams);
    }

    public function columns(): array {
        return [
            'name' => get_string('fullname'),
            'email' => get_string('email'),
            'course' => get_string('course'),
            'quizzes' => self::str('col_quizzestaken'),
            'quiz' => self::str('col_quizavg'),
            'lastquiz' => self::str('col_lastquiz'),
            'completion' => self::str('col_completion'),
            'video' => self::str('col_videoavg'),
        ];
    }

    /**
     * The (student, course) pairs: "FROM … WHERE …" with m.userid, m.courseid, u.*.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        [$members, $params] = $this->pairs();
        [$search, $sparams] = data::user_search_sql('u', $this->f->q);
        return ["FROM ($members) m JOIN {user} u ON u.id = m.userid JOIN {course} co ON co.id = m.courseid WHERE $search",
            $params + $sparams];
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $pairs = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $cards = [['id' => 'card_enrolments', 'label' => self::str('card_enrolments'), 'value' => self::num($pairs)]];
        [$members, $mparams] = $this->pairs();
        $avg = data::quiz_avg($members, $mparams, 'courseid');
        if ($avg) {
            $cards[] = ['id' => 'card_quizavg', 'label' => self::str('card_quizavg'), 'value' => self::pct(array_sum($avg) / count($avg))];
        }
        return $cards;
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $pairs = $DB->get_recordset_sql("SELECT m.userid, m.courseid, co.fullname AS coursename, u.email, $names
                                           $from ORDER BY " . $this->order_sql('co.fullname, u.firstname, u.lastname, m.userid'),
            $params, $perpage ? $page * $perpage : 0, $perpage);
        $list = [];
        foreach ($pairs as $p) {
            $list[] = $p;
        }
        $pairs->close();
        if (!$list) {
            return ['total' => $total, 'rows' => []];
        }
        $userids = array_values(array_unique(array_map(fn($p) => (int) $p->userid, $list)));
        $courseids = array_values(array_unique(array_map(fn($p) => (int) $p->courseid, $list)));
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ru');
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'rc');

        // Quiz results per (user, course): taken, average %, last attempt.
        $stats = [];
        $rs = $DB->get_recordset_sql("SELECT gg.userid, gi.courseid, COUNT(1) AS taken,
                    AVG((gg.finalgrade - gi.grademin) / (gi.grademax - gi.grademin) * 100) AS avgpct
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                    AND gi.grademax > gi.grademin
              WHERE gg.userid $usql AND gi.courseid $csql AND gg.finalgrade IS NOT NULL
           GROUP BY gg.userid, gi.courseid", $uparams + $cparams);
        foreach ($rs as $r) {
            $stats[$r->userid . '-' . $r->courseid] = $r;
        }
        $rs->close();
        $quizcount = $DB->get_records_sql_menu("SELECT course, COUNT(1) FROM {quiz} WHERE course $csql GROUP BY course",
            $cparams);
        $last = [];
        $rs = $DB->get_recordset_sql("SELECT qa.userid, q.course, MAX(qa.timefinish) AS lastat
               FROM {quiz_attempts} qa JOIN {quiz} q ON q.id = qa.quiz
              WHERE qa.userid $usql AND q.course $csql AND qa.state = 'finished' AND qa.preview = 0
           GROUP BY qa.userid, q.course", $uparams + $cparams);
        foreach ($rs as $r) {
            $last[$r->userid . '-' . $r->course] = (int) $r->lastat;
        }
        $rs->close();
        $video = [];
        if (data::has_videos()) {
            $rs = $DB->get_recordset_sql("SELECT userid, courseid, AVG(percent) AS pct FROM {nit_video_progress}
                WHERE userid $usql AND courseid $csql AND percent > 0 GROUP BY userid, courseid", $uparams + $cparams);
            foreach ($rs as $r) {
                $video[$r->userid . '-' . $r->courseid] = (float) $r->pct;
            }
            $rs->close();
        }

        $rows = [];
        foreach ($list as $p) {
            $k = $p->userid . '-' . $p->courseid;
            $stat = $stats[$k] ?? null;
            $completion = data::completion((int) $p->courseid, (int) $p->userid);
            $rows[] = [
                'name' => fullname($p),
                'email' => $p->email,
                'course' => self::cname($p->coursename),
                'quizzes' => self::num($stat ? $stat->taken : 0) . ' / ' . self::num($quizcount[$p->courseid] ?? 0),
                'quiz' => $stat ? self::pct((float) $stat->avgpct) : '—',
                'lastquiz' => self::date($last[$k] ?? 0),
                'completion' => $completion === null ? '—' : self::pct($completion),
                'video' => isset($video[$k]) ? self::pct($video[$k]) : '—',
            ];
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
