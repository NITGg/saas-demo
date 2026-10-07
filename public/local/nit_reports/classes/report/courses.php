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
 * Course performance: category, teachers, current students (and how many were
 * active this week or left), how many finished the course, quiz and video
 * averages, what the course holds, learner rating, and (for managers) price and
 * the gateway sales of the period.
 *
 * Every figure about students covers the current students only; the ones who
 * left are counted in their own column. Sales still count every payment (money
 * that came in), so a course can show more sales than students.
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
            'category' => get_string('category'),
            'teachers' => self::str('col_teachers'),
            'students' => self::str('col_students'),
            'active' => self::str('col_activeweek'),
            'left' => self::str('col_leftstudents'),
            'completed' => self::str('col_completed'),
            'quiz' => self::str('col_quizavg'),
            'video' => self::str('col_videoavg'),
            'videos' => self::str('col_videocount'),
            'quizzes' => self::str('col_quizcount'),
            'rating' => self::str('col_rating'),
        ];
        if ($this->money_visible()) {
            $cols['price'] = self::str('col_price');
            $cols['sales'] = self::str('col_sales');
            $cols['revenue'] = self::str('col_revenue');
            $cols['refunds'] = self::str('col_refunds');
        }
        return $cols;
    }

    public function sortable(): array {
        return array_fill_keys(array_keys($this->columns()), true);
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
        // Every course in scope is built (there are few), then sorted and cut to the page.
        [$from, $params] = $this->from();
        $courses = $DB->get_records_sql("SELECT c.id, c.fullname, c.category, c.enablecompletion $from
                                          ORDER BY c.fullname, c.id", $params);
        if (!$courses) {
            return ['total' => 0, 'rows' => []];
        }
        $ids = array_keys($courses);
        [$csql, $cparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cc');

        // Current students and teachers per course, and the students who left.
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
        [$left, $lparams] = data::left_sql("c.id $csql", $cparams);
        $leftcount = $DB->get_records_sql_menu("SELECT l.courseid, COUNT(1) FROM ($left) l GROUP BY l.courseid", $lparams);

        // Active this week = current students who opened the course in the last 7 days.
        $active = [];
        foreach ($DB->get_recordset_sql("SELECT courseid, userid FROM {user_lastaccess}
                                          WHERE courseid $csql AND timeaccess >= :wk", $cparams + ['wk' => time() - WEEKSECS]) as $r) {
            if (isset($students[(int) $r->courseid][(int) $r->userid])) {
                $active[(int) $r->courseid] = ($active[(int) $r->courseid] ?? 0) + 1;
            }
        }

        // Finished = core course completion, counted among current students.
        $completed = [];
        foreach ($DB->get_recordset_sql("SELECT course, userid FROM {course_completions}
                                          WHERE course $csql AND timecompleted IS NOT NULL", $cparams) as $r) {
            if (isset($students[(int) $r->course][(int) $r->userid])) {
                $completed[(int) $r->course] = ($completed[(int) $r->course] ?? 0) + 1;
            }
        }
        $quiz = data::quiz_avg($members, $mparams, 'courseid');
        $video = data::video_avg($members, $mparams, 'courseid');
        $videocount = data::videos_per_course($ids);
        $quizcount = $DB->get_records_sql_menu("SELECT course, COUNT(1) FROM {quiz} WHERE course $csql GROUP BY course", $cparams);
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_aggregates($ids) : [];
        $categories = $DB->get_records_menu('course_categories', null, '', 'id, name');

        $sales = $refunds = $prices = [];
        if ($this->money_visible()) {
            [$period, $pparams] = $this->f->period_sql('t.timecreated');
            $rs = $DB->get_recordset_sql("SELECT t.courseid, t.currency, COUNT(1) AS n, SUM(t.amount) AS total
                FROM {local_payments_transactions} t
               WHERE t.courseid $csql AND t.status IN ('completed', 'partially_refunded') AND $period
            GROUP BY t.courseid, t.currency", $cparams + $pparams);
            foreach ($rs as $r) {
                $id = (int) $r->courseid;
                $sales[$id]['n'] = ($sales[$id]['n'] ?? 0) + (int) $r->n;
                $sales[$id]['sum'] = ($sales[$id]['sum'] ?? 0) + (float) $r->total;
                $sales[$id]['money'][] = self::money((float) $r->total, (string) $r->currency);
            }
            $rs->close();
            [$rperiod, $rpparams] = $this->f->period_sql('t.timemodified', 'r');
            $refunds = $DB->get_records_sql_menu("SELECT t.courseid, COUNT(1) FROM {local_payments_transactions} t
                WHERE t.courseid $csql AND t.status = 'refunded' AND $rperiod GROUP BY t.courseid", $cparams + $rpparams);
            if ($DB->get_manager()->table_exists('local_payments_course_prices')) {
                foreach ($DB->get_records_sql("SELECT id, courseid, price, currency FROM {local_payments_course_prices}
                        WHERE courseid $csql AND is_default = 1 AND is_active = 1 ORDER BY id", $cparams) as $p) {
                    $prices[(int) $p->courseid] = $prices[(int) $p->courseid] ?? $p;
                }
            }
        }

        $rows = [];
        foreach ($courses as $c) {
            $id = (int) $c->id;
            $n = count($students[$id] ?? []);
            $agg = $ratings[$id] ?? null;
            $done = $completed[$id] ?? 0;
            $row = [
                'course' => self::cname($c->fullname),
                'category' => self::cname($categories[$c->category] ?? null),
                'teachers' => implode('، ', array_map(fn($t) => $teachernames[$t] ?? '', $teachers[$id] ?? [])) ?: '—',
                'students' => self::num($n),
                'active' => self::num($active[$id] ?? 0),
                'left' => self::num($leftcount[$id] ?? 0),
                'completed' => empty($c->enablecompletion) ? '—'
                    : self::num($done) . ($n ? ' (' . self::pct(100 * $done / $n) . ')' : ''),
                'quiz' => isset($quiz[$id]) ? self::pct($quiz[$id]) : '—',
                'video' => isset($video[$id]) ? self::pct($video[$id]) : '—',
                'videos' => self::num($videocount[$id] ?? 0),
                'quizzes' => self::num($quizcount[$id] ?? 0),
                'rating' => $agg && $agg->count ? format_float($agg->avg, 1) . ' (' . $agg->count . ')' : '—',
                '_sort' => [
                    'students' => $n, 'active' => (int) ($active[$id] ?? 0), 'left' => (int) ($leftcount[$id] ?? 0),
                    'completed' => empty($c->enablecompletion) ? -1 : $done, 'quiz' => $quiz[$id] ?? -1.0,
                    'video' => $video[$id] ?? -1.0, 'videos' => (int) ($videocount[$id] ?? 0),
                    'quizzes' => (int) ($quizcount[$id] ?? 0), 'rating' => $agg && $agg->count ? (float) $agg->avg : -1.0,
                ],
            ];
            if ($this->money_visible()) {
                $price = $prices[$id] ?? null;
                $row['price'] = $price ? self::money((float) $price->price, (string) $price->currency) : self::str('free');
                $row['sales'] = self::num($sales[$id]['n'] ?? 0);
                $row['revenue'] = isset($sales[$id]) ? implode(' + ', $sales[$id]['money']) : '0';
                $row['refunds'] = self::num($refunds[$id] ?? 0);
                $row['_sort'] += ['price' => $price ? (float) $price->price : 0.0, 'sales' => (int) ($sales[$id]['n'] ?? 0),
                    'revenue' => (float) ($sales[$id]['sum'] ?? 0), 'refunds' => (int) ($refunds[$id] ?? 0)];
            }
            $rows[] = $row;
        }
        return $this->finish($rows, $page, $perpage);
    }
}
