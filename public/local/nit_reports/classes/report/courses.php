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
 * Course performance: students, how many finished the course, quiz and video
 * averages, learner rating, and (for managers) the gateway revenue of the period.
 *
 * "Finished" is core course completion (course_completions.timecompleted), so it
 * needs completion tracking on the course; otherwise it shows "—".
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses extends base {

    public static function key(): string {
        return 'courses';
    }

    public static function level(): string {
        return scope::TEACHING;
    }

    /**
     * Whether the viewer sees money (managers, not teachers).
     *
     * @return bool
     */
    private function money_visible(): bool {
        global $DB;
        return !$this->s->teacher_only() && $DB->get_manager()->table_exists('local_payments_transactions');
    }

    public function columns(): array {
        $cols = [
            'course' => get_string('course'),
            'teachers' => self::str('col_teachers'),
            'students' => self::str('col_students'),
            'completed' => self::str('col_completed'),
            'quiz' => self::str('col_quizavg'),
            'video' => self::str('col_videoavg'),
            'rating' => self::str('col_rating'),
        ];
        if ($this->money_visible()) {
            $cols['sales'] = self::str('col_sales');
            $cols['revenue'] = self::str('col_revenue');
        }
        return $cols;
    }

    /**
     * The courses in scope matching the filters: "FROM … WHERE …" over {course} c.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        global $DB;
        [$cwhere, $params] = $this->course_where('c.id');
        $search = '1 = 1';
        if ($this->f->q !== '') {
            $search = $DB->sql_like('c.fullname', ':cq', false);
            $params['cq'] = '%' . $DB->sql_like_escape($this->f->q) . '%';
        }
        return ["FROM {course} c WHERE c.id <> " . SITEID . " AND $cwhere AND $search", $params];
    }

    public function filters(): array {
        return $this->money_visible() ? ['period', 'course', 'q'] : ['course', 'q'];
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $ids = $DB->get_fieldset_sql("SELECT c.id $from", $params);
        $students = 0;
        if ($ids && ($roles = data::student_roles())) {
            [$csql, $cparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'sc');
            [$members, $mparams] = data::members_sql($roles, "c.id $csql", $cparams);
            $students = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT m.userid) FROM ($members) m", $mparams);
        }
        $cards = [
            ['label' => self::str('card_courses'), 'value' => self::num(count($ids))],
            ['label' => self::str('card_students'), 'value' => self::num($students)],
        ];
        if ($ids && class_exists('\local_nit_reviews\api')) {
            $aggs = array_filter(\local_nit_reviews\api::get_aggregates($ids), fn($a) => $a->count > 0);
            $count = array_sum(array_map(fn($a) => $a->count, $aggs));
            if ($count) {
                $avg = array_sum(array_map(fn($a) => $a->avg * $a->count, $aggs)) / $count;
                $cards[] = ['label' => self::str('card_avgrating'), 'value' => format_float($avg, 1) . ' (' . $count . ')'];
            }
        }
        return $cards;
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $courses = $DB->get_records_sql("SELECT c.id, c.fullname $from ORDER BY c.fullname, c.id",
            $params, $perpage ? $page * $perpage : 0, $perpage);
        if (!$courses) {
            return ['total' => $total, 'rows' => []];
        }
        $ids = array_keys($courses);
        [$csql, $cparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cc');

        // Students and teachers per course.
        $students = $teachers = [];
        [$members, $mparams] = data::members_sql(data::student_roles(), "c.id $csql", $cparams);
        foreach ($DB->get_recordset_sql($members, $mparams) as $r) {
            $students[(int) $r->courseid][(int) $r->userid] = true;
        }
        [$tmembers, $tparams] = data::members_sql(data::teacher_roles(), "c.id $csql", $cparams);
        foreach ($DB->get_recordset_sql($tmembers, $tparams) as $r) {
            if (!is_siteadmin((int) $r->userid)) {
                $teachers[(int) $r->courseid][] = (int) $r->userid;
            }
        }
        $teachernames = data::user_names(array_merge([], ...array_values($teachers ?: [[]])));

        // Finished = core course completion, counted among current students.
        $completed = [];
        foreach ($DB->get_recordset_sql("SELECT course, userid FROM {course_completions}
                                          WHERE course $csql AND timecompleted IS NOT NULL", $cparams) as $r) {
            if (isset($students[(int) $r->course][(int) $r->userid])) {
                $completed[(int) $r->course] = ($completed[(int) $r->course] ?? 0) + 1;
            }
        }
        $tracking = $DB->get_records_sql_menu("SELECT id, enablecompletion FROM {course} WHERE id $csql", $cparams);
        $quiz = data::quiz_avg_by_course($ids);
        $video = data::video_avg_by_course($ids);
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_aggregates($ids) : [];

        $sales = [];
        if ($this->money_visible()) {
            [$period, $pparams] = $this->f->period_sql('t.timecreated');
            $rs = $DB->get_recordset_sql("SELECT t.courseid, t.currency, COUNT(1) AS n, SUM(t.amount) AS total
                FROM {local_payments_transactions} t
               WHERE t.courseid $csql AND t.status IN ('completed', 'partially_refunded') AND $period
            GROUP BY t.courseid, t.currency", $cparams + $pparams);
            foreach ($rs as $r) {
                $sales[(int) $r->courseid]['n'] = ($sales[(int) $r->courseid]['n'] ?? 0) + (int) $r->n;
                $sales[(int) $r->courseid]['money'][] = self::money((float) $r->total, (string) $r->currency);
            }
            $rs->close();
        }

        $rows = [];
        foreach ($courses as $c) {
            $id = (int) $c->id;
            $n = count($students[$id] ?? []);
            $agg = $ratings[$id] ?? null;
            $row = [
                'course' => self::cname($c->fullname),
                'teachers' => implode('، ', array_map(fn($t) => $teachernames[$t] ?? '', $teachers[$id] ?? [])) ?: '—',
                'students' => self::num($n),
                'completed' => empty($tracking[$id]) ? '—'
                    : self::num($completed[$id] ?? 0) . ($n ? ' (' . self::pct(100 * ($completed[$id] ?? 0) / $n) . ')' : ''),
                'quiz' => isset($quiz[$id]) ? self::pct($quiz[$id]) : '—',
                'video' => isset($video[$id]) ? self::pct($video[$id]) : '—',
                'rating' => $agg && $agg->count ? format_float($agg->avg, 1) . ' (' . $agg->count . ')' : '—',
            ];
            if ($this->money_visible()) {
                $row['sales'] = self::num($sales[$id]['n'] ?? 0);
                $row['revenue'] = isset($sales[$id]) ? implode(' + ', $sales[$id]['money']) : '0';
            }
            $rows[] = $row;
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
