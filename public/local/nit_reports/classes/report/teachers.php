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
 * Teachers: their courses and current students (in scope), learner rating, how
 * much of their videos is watched, the private lessons and live sessions they
 * held in the period and the ones they missed, last access, and their money
 * (local_nit_finance): earned and paid out in the period, wallet now, and
 * withdrawals waiting.
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
        $t = self::tables();
        if ($t['lessons']) {
            $cols['lessons'] = self::str('col_lessonsdone');
        }
        if ($t['live']) {
            $cols['sessions'] = self::str('col_sessionsheld');
        }
        if ($t['lessons'] || $t['live']) {
            $cols['absences'] = self::str('col_teacherabsences');
        }
        $cols['lastaccess'] = self::str('col_lastaccess');
        if ($t['finance']) {
            $cols['earned'] = self::str('col_earned');
            $cols['paid'] = self::str('col_paidout');
            $cols['wallet'] = self::str('col_balance');
            $cols['pending'] = self::str('col_requested');
        }
        return $cols;
    }

    public function sortable(): array {
        return array_fill_keys(array_keys($this->columns()), true);
    }

    /**
     * Which data exists.
     *
     * @return array{finance:bool, lessons:bool, live:bool}
     */
    private static function tables(): array {
        global $DB;
        $dbman = $DB->get_manager();
        return ['finance' => $dbman->table_exists('nit_earning'), 'lessons' => $dbman->table_exists('nit_lesson'),
            'live' => $dbman->table_exists('academy_live_sessions')];
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
        if (self::tables()['finance']) {
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
        // Every teacher in scope is built (there are few), then sorted and cut to the page.
        [$from, $params] = $this->from();
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, u.email, u.lastaccess, $names $from ORDER BY u.firstname, u.lastname, u.id",
            $params);
        if (!$users) {
            return ['total' => 0, 'rows' => []];
        }
        $ids = array_keys($users);
        $t = self::tables();
        [$pairs, $pparams] = $this->pairs();
        [$usql, $uparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tu');
        $courses = [];
        foreach ($DB->get_recordset_sql("SELECT p.userid, p.courseid FROM ($pairs) p WHERE p.userid $usql",
                $pparams + $uparams) as $r) {
            $courses[(int) $r->userid][] = (int) $r->courseid;
        }
        $allcourses = array_values(array_unique(array_merge([], ...array_values($courses ?: [[]]))));

        // Current students per course, then per teacher (distinct); video watching of current students.
        $students = [];
        $video = [];
        if ($allcourses && ($roles = data::student_roles())) {
            [$csql, $cparams] = $DB->get_in_or_equal($allcourses, SQL_PARAMS_NAMED, 'sc');
            [$members, $mparams] = data::members_sql($roles, "c.id $csql", $cparams);
            foreach ($DB->get_recordset_sql($members, $mparams) as $r) {
                $students[(int) $r->courseid][(int) $r->userid] = true;
            }
            $video = data::video_avg($members, $mparams, 'courseid');
        }
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_teacher_aggregates($ids) : [];
        $apps = $DB->get_records_sql_menu("SELECT userid, MAX(lastaccess) FROM {external_tokens}
                                            WHERE userid $usql GROUP BY userid", $uparams);

        // Lessons and live sessions in the period, and the ones the teacher missed.
        $lessons = $sessions = $absent = [];
        if ($t['lessons']) {
            [$lperiod, $lparams] = $this->f->period_sql('CASE WHEN confirmed_time > 0 THEN confirmed_time ELSE requested_time END', 'l');
            foreach ($DB->get_recordset_sql("SELECT teacherid, status, COUNT(1) AS n FROM {nit_lesson}
                    WHERE teacherid $usql AND status IN ('completed', 'teacher_absent') AND $lperiod
                 GROUP BY teacherid, status", $uparams + $lparams) as $r) {
                if ($r->status === 'completed') {
                    $lessons[(int) $r->teacherid] = (int) $r->n;
                } else {
                    $absent[(int) $r->teacherid] = ($absent[(int) $r->teacherid] ?? 0) + (int) $r->n;
                }
            }
        }
        if ($t['live']) {
            [$speriod, $sparams] = $this->f->period_sql('start_time', 's');
            foreach ($DB->get_recordset_sql("SELECT teacherid, CASE WHEN teacher_joined_at > 0 THEN 1 ELSE 0 END AS joined,
                        COUNT(1) AS n FROM {academy_live_sessions}
                    WHERE teacherid $usql AND status = 'ended' AND $speriod
                 GROUP BY teacherid, CASE WHEN teacher_joined_at > 0 THEN 1 ELSE 0 END", $uparams + $sparams) as $r) {
                if ((int) $r->joined) {
                    $sessions[(int) $r->teacherid] = (int) $r->n;
                } else {
                    $absent[(int) $r->teacherid] = ($absent[(int) $r->teacherid] ?? 0) + (int) $r->n;
                }
            }
        }

        $earned = $paid = $wallets = $pending = [];
        if ($t['finance']) {
            [$period, $eparams] = $this->f->period_sql('timecreated');
            $earned = $DB->get_records_sql_menu("SELECT teacherid, SUM(teacher_amount_minor) FROM {nit_earning}
                WHERE status = 'active' AND $period AND teacherid $usql GROUP BY teacherid", $eparams + $uparams);
            [$wperiod, $wparams] = $this->f->period_sql('timeprocessed', 'w');
            $paid = $DB->get_records_sql_menu("SELECT teacherid, SUM(amount_minor) FROM {nit_withdrawal}
                WHERE status = 'paid' AND $wperiod AND teacherid $usql GROUP BY teacherid", $wparams + $uparams);
            $wallets = $DB->get_records_sql_menu("SELECT userid, SUM(balance_minor) FROM {nit_wallet}
                WHERE userid $usql AND ownertype = 'teacher' GROUP BY userid", $uparams);
            $pending = $DB->get_records_sql_menu("SELECT teacherid, SUM(amount_minor) FROM {nit_withdrawal}
                WHERE teacherid $usql AND status IN ('pending', 'approved') GROUP BY teacherid", $uparams);
        }

        $rows = [];
        foreach ($users as $u) {
            $id = (int) $u->id;
            $mine = $courses[$id] ?? [];
            $learners = [];
            $watch = [];
            foreach ($mine as $cid) {
                $learners += $students[$cid] ?? [];
                if (isset($video[$cid])) {
                    $watch[] = $video[$cid];
                }
            }
            $agg = $ratings[$id] ?? null;
            $last = max((int) $u->lastaccess, (int) ($apps[$id] ?? 0));
            $watched = $watch ? array_sum($watch) / count($watch) : null;
            $row = [
                'name' => fullname($u),
                'email' => $u->email,
                'courses' => self::num(count($mine)),
                'students' => self::num(count($learners)),
                'rating' => $agg && $agg->count ? format_float($agg->avg, 1) . ' (' . $agg->count . ')' : '—',
                'video' => self::pct($watched),
                'lessons' => self::num($lessons[$id] ?? 0),
                'sessions' => self::num($sessions[$id] ?? 0),
                'absences' => self::num($absent[$id] ?? 0),
                'lastaccess' => self::date($last, true),
                '_sort' => ['courses' => count($mine), 'students' => count($learners),
                    'rating' => $agg && $agg->count ? (float) $agg->avg : -1.0, 'video' => $watched ?? -1.0,
                    'lessons' => $lessons[$id] ?? 0, 'sessions' => $sessions[$id] ?? 0, 'absences' => $absent[$id] ?? 0,
                    'lastaccess' => $last],
            ];
            if ($t['finance']) {
                $money = ['earned' => (int) ($earned[$id] ?? 0), 'paid' => (int) ($paid[$id] ?? 0),
                    'wallet' => (int) ($wallets[$id] ?? 0), 'pending' => (int) ($pending[$id] ?? 0)];
                $row += array_map(fn($v) => data::minor($v), $money);
                $row['_sort'] += $money;
            }
            $rows[] = $row;
        }
        return $this->finish($rows, $page, $perpage);
    }
}
