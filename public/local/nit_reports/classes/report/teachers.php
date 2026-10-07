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
 * Teachers: their courses and students (in scope), learner rating, how much of
 * their videos is watched, and their earnings in the period (activity sales and
 * private lessons, local_nit_finance) with what was paid out to them.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teachers extends base {

    public static function key(): string {
        return 'teachers';
    }

    public static function level(): string {
        return scope::MANAGE;
    }

    public function columns(): array {
        $cols = [
            'name' => get_string('fullname'),
            'email' => get_string('email'),
            'courses' => self::str('col_courses'),
            'students' => self::str('col_students'),
            'rating' => self::str('col_rating'),
            'video' => self::str('col_videoavg'),
        ];
        if (self::finance()) {
            $cols['earned'] = self::str('col_earned');
            $cols['paid'] = self::str('col_paidout');
        }
        return $cols;
    }

    /**
     * Whether teacher earnings exist (local_nit_finance).
     *
     * @return bool
     */
    private static function finance(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_earning');
    }

    /**
     * (teacher, course) pairs in scope, site admins left out.
     *
     * @return array{0:string, 1:array}
     */
    private function pairs(): array {
        global $DB, $CFG;
        [$cwhere, $cparams] = $this->course_where('c.id');
        [$members, $params] = data::members_sql(data::teacher_roles(), $cwhere, $cparams);
        [$asql, $aparams] = $DB->get_in_or_equal(array_map('intval', explode(',', $CFG->siteadmins)) ?: [0],
            SQL_PARAMS_NAMED, 'adm', false);
        return ["SELECT t.userid, t.courseid FROM ($members) t WHERE t.userid $asql", $params + $aparams];
    }

    /**
     * The teachers matching the filters: "FROM … WHERE …" over {user} u.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        [$pairs, $params] = $this->pairs();
        [$search, $sparams] = data::user_search_sql('u', $this->f->q);
        return ["FROM {user} u WHERE u.id IN (SELECT p.userid FROM ($pairs) p) AND $search", $params + $sparams];
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $cards = [['label' => self::str('card_teachers'),
            'value' => self::num($DB->count_records_sql("SELECT COUNT(1) $from", $params))]];
        if (self::finance()) {
            [$period, $pparams] = $this->f->period_sql('e.timecreated');
            $earned = (int) $DB->get_field_sql("SELECT COALESCE(SUM(e.teacher_amount_minor), 0) FROM {nit_earning} e
                WHERE e.status = 'active' AND $period AND e.teacherid IN (SELECT u.id $from)", $pparams + $params);
            [$wperiod, $wparams] = $this->f->period_sql('w.timeprocessed', 'w');
            $paid = (int) $DB->get_field_sql("SELECT COALESCE(SUM(w.amount_minor), 0) FROM {nit_withdrawal} w
                WHERE w.status = 'paid' AND $wperiod AND w.teacherid IN (SELECT u.id $from)", $wparams + $params);
            $cards[] = ['label' => self::str('card_earned'), 'value' => data::minor($earned)];
            $cards[] = ['label' => self::str('card_paidout'), 'value' => data::minor($paid)];
        }
        return $cards;
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, u.email, $names $from ORDER BY u.firstname, u.lastname, u.id",
            $params, $perpage ? $page * $perpage : 0, $perpage);
        if (!$users) {
            return ['total' => $total, 'rows' => []];
        }
        $ids = array_keys($users);
        [$pairs, $pparams] = $this->pairs();
        [$usql, $uparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tu');
        $courses = [];
        foreach ($DB->get_recordset_sql("SELECT p.userid, p.courseid FROM ($pairs) p WHERE p.userid $usql",
                $pparams + $uparams) as $r) {
            $courses[(int) $r->userid][] = (int) $r->courseid;
        }
        $allcourses = array_values(array_unique(array_merge([], ...array_values($courses ?: [[]]))));

        // Students per course, then per teacher (distinct).
        $students = [];
        if ($allcourses && ($roles = data::student_roles())) {
            [$csql, $cparams] = $DB->get_in_or_equal($allcourses, SQL_PARAMS_NAMED, 'sc');
            [$members, $mparams] = data::members_sql($roles, "c.id $csql", $cparams);
            foreach ($DB->get_recordset_sql($members, $mparams) as $r) {
                $students[(int) $r->courseid][(int) $r->userid] = true;
            }
        }
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_teacher_aggregates($ids) : [];
        $video = data::video_avg_by_course($allcourses);

        $earned = $paid = [];
        if (self::finance()) {
            [$period, $eparams] = $this->f->period_sql('timecreated');
            $earned = $DB->get_records_sql_menu("SELECT teacherid, SUM(teacher_amount_minor) FROM {nit_earning}
                WHERE status = 'active' AND $period AND teacherid $usql GROUP BY teacherid", $eparams + $uparams);
            [$wperiod, $wparams] = $this->f->period_sql('timeprocessed', 'w');
            $paid = $DB->get_records_sql_menu("SELECT teacherid, SUM(amount_minor) FROM {nit_withdrawal}
                WHERE status = 'paid' AND $wperiod AND teacherid $usql GROUP BY teacherid", $wparams + $uparams);
        }

        $rows = [];
        foreach ($users as $u) {
            $mine = $courses[(int) $u->id] ?? [];
            $learners = [];
            $watch = [];
            foreach ($mine as $cid) {
                $learners += $students[$cid] ?? [];
                if (isset($video[$cid])) {
                    $watch[] = $video[$cid];
                }
            }
            $agg = $ratings[(int) $u->id] ?? null;
            $row = [
                'name' => fullname($u),
                'email' => $u->email,
                'courses' => self::num(count($mine)),
                'students' => self::num(count($learners)),
                'rating' => $agg && $agg->count ? format_float($agg->avg, 1) . ' (' . $agg->count . ')' : '—',
                'video' => $watch ? self::pct(array_sum($watch) / count($watch)) : '—',
            ];
            if (self::finance()) {
                $row['earned'] = data::minor((int) ($earned[$u->id] ?? 0));
                $row['paid'] = data::minor((int) ($paid[$u->id] ?? 0));
            }
            $rows[] = $row;
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
