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
 * Students: who they are, when they registered, their courses (current and left),
 * subscription, last access, completion, quiz and video results, and — for
 * admins — what they paid and what they hold.
 *
 * Who is listed: site-wide without a course or status filter, every student
 * account (an active account that is not a teacher, a manager or an admin — even
 * with no course); otherwise the current students of the courses in scope, or the
 * ones who left them (status "left"). The period filters the registration date.
 * Completion, quiz and video figures cover the current courses, or the left ones
 * when listing the students who left.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class students extends base {

    public static function key(): string {
        return 'students';
    }

    public static function level(): string {
        return scope::TEACHING;
    }

    public function filters(): array {
        return ['period', 'course', 'status', 'q'];
    }

    public function status_options(): array {
        return ['current' => self::str('member_current'), 'left' => self::str('member_left')];
    }

    /**
     * Whether money columns show (site-wide viewers only: they cover the whole site).
     *
     * @return bool
     */
    private function money_visible(): bool {
        return $this->s->sitewide;
    }

    public function columns(): array {
        $cols = [
            'name' => get_string('fullname'),
            'email' => get_string('email'),
            'phone' => get_string('phone1'),
            'registered' => self::str('col_registered'),
            'courses' => self::str('col_courses'),
            'left' => self::str('col_leftcourses'),
            'subscription' => self::str('col_subscription'),
            'subends' => self::str('col_subends'),
            'lastaccess' => self::str('col_lastaccess'),
            'completion' => self::str('col_completion'),
            'quiz' => self::str('col_quizavg'),
            'video' => self::str('col_videosfinished'),
        ];
        if ($this->money_visible()) {
            $cols['paid'] = self::str('col_totalpaid');
            $cols['wallet'] = self::str('col_wallet');
            $cols['flex'] = self::str('col_flexleft');
        }
        $cols['account'] = self::str('col_account');
        return $cols;
    }

    public function sortable(): array {
        global $DB;
        $cols = [
            'name' => $DB->sql_fullname('u.firstname', 'u.lastname'),
            'email' => 'u.email',
            'phone' => 'u.phone1',
            'registered' => 'u.timecreated',
            'lastaccess' => 'CASE WHEN (SELECT MAX(et.lastaccess) FROM {external_tokens} et WHERE et.userid = u.id) > u.lastaccess
                THEN (SELECT MAX(et2.lastaccess) FROM {external_tokens} et2 WHERE et2.userid = u.id) ELSE u.lastaccess END',
            'account' => 'u.suspended',
        ];
        if ($this->money_visible()) {
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_payments_transactions')) {
                $cols['paid'] = "(SELECT COALESCE(SUM(st.amount), 0) FROM {local_payments_transactions} st
                                   WHERE st.userid = u.id AND st.status IN ('completed', 'partially_refunded'))";
            }
            if ($dbman->table_exists('nit_wallet')) {
                $cols['wallet'] = "(SELECT COALESCE(SUM(sw.balance_minor), 0) FROM {nit_wallet} sw
                                     WHERE sw.userid = u.id AND sw.ownertype = 'student')";
            }
        }
        return $cols;
    }

    /**
     * The (student, course) pairs the figures cover: current students, or the
     * ones who left when listing those.
     *
     * @return array{0:string, 1:array}
     */
    private function pairs(): array {
        [$cwhere, $cparams] = $this->course_where('c.id');
        return $this->f->status === 'left' ? data::left_sql($cwhere, $cparams)
            : data::members_sql(data::student_roles(), $cwhere, $cparams);
    }

    /**
     * The students matching the filters: SQL "FROM … WHERE …" over {user} u.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        global $DB, $CFG;
        [$period, $params] = $this->f->period_sql('u.timecreated');
        [$search, $sparams] = data::user_search_sql('u', $this->f->q);
        $params += $sparams;
        if ($this->s->sitewide && !$this->f->courseid && $this->f->status === '') {
            // Every student account: active, not staff.
            $staff = array_merge(data::teacher_roles(), array_map('intval', array_keys(get_archetype_roles('manager'))));
            $admins = array_map('intval', explode(',', $CFG->siteadmins)) ?: [0];
            [$asql, $aparams] = $DB->get_in_or_equal($admins, SQL_PARAMS_NAMED, 'adm', false);
            $staffsql = '';
            if ($staff) {
                [$ssql, $stparams] = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, 'stf');
                $staffsql = "AND NOT EXISTS (SELECT 1 FROM {role_assignments} sra WHERE sra.userid = u.id AND sra.roleid $ssql)";
                $params += $stparams;
            }
            return ["FROM {user} u
                    WHERE u.deleted = 0 AND u.confirmed = 1 AND u.id <> :guest AND u.id $asql $staffsql
                      AND $period AND $search", $params + $aparams + ['guest' => (int) $CFG->siteguest]];
        }
        [$pairs, $pparams] = $this->pairs();
        return ["FROM {user} u
                WHERE u.id IN (SELECT m.userid FROM ($pairs) m) AND $period AND $search", $params + $pparams];
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        // Same "last access" as the table: the website, or the app's token.
        $week = (int) $DB->count_records_sql("SELECT COUNT(1) $from AND (u.lastaccess >= :wk OR EXISTS (
                SELECT 1 FROM {external_tokens} et WHERE et.userid = u.id AND et.lastaccess >= :wk2))",
            $params + ['wk' => time() - WEEKSECS, 'wk2' => time() - WEEKSECS]);
        $cards = [
            ['id' => 'card_students', 'label' => self::str('card_students'), 'value' => self::num($total)],
            ['id' => 'card_activeweek', 'label' => self::str('card_activeweek'), 'value' => self::num($week)],
        ];
        if ($DB->get_manager()->table_exists('nit_sub_purchase')) {
            $subs = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT p.userid) FROM {nit_sub_purchase} p
                WHERE p.status = 'active' AND (p.expires_at = 0 OR p.expires_at > :now)
                  AND p.userid IN (SELECT u.id $from)", $params + ['now' => time()]);
            $cards[] = ['id' => 'card_withsubscription', 'label' => self::str('card_withsubscription'), 'value' => self::num($subs)];
        }
        return $cards;
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, u.email, u.phone1, u.timecreated, u.lastaccess, u.suspended, $names
                                       $from ORDER BY " . $this->order_sql('u.timecreated DESC, u.id DESC'),
            $params, $perpage ? $page * $perpage : 0, $perpage);
        if (!$users) {
            return ['total' => $total, 'rows' => []];
        }
        $ids = array_keys($users);
        $dbman = $DB->get_manager();

        // Their current and left courses in scope; the figures cover $mine.
        [$usql, $uparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'su');
        [$cwhere, $cparams] = $this->course_where('c.id');
        [$current, $curparams] = data::members_sql(data::student_roles(), $cwhere, $cparams);
        [$left, $leftparams] = data::left_sql($cwhere, $cparams);
        $courses = $leftcourses = [];
        foreach ($DB->get_recordset_sql("SELECT m.userid, m.courseid FROM ($current) m WHERE m.userid $usql",
                $curparams + $uparams) as $r) {
            $courses[(int) $r->userid][] = (int) $r->courseid;
        }
        foreach ($DB->get_recordset_sql("SELECT m.userid, m.courseid FROM ($left) m WHERE m.userid $usql",
                $leftparams + $uparams) as $r) {
            $leftcourses[(int) $r->userid][] = (int) $r->courseid;
        }
        $showleft = $this->f->status === 'left';
        [$pairs, $pparams] = $showleft ? [$left, $leftparams] : [$current, $curparams];
        $mypairs = "SELECT pm.userid, pm.courseid FROM ($pairs) pm WHERE pm.userid $usql";
        $mypparams = $pparams + $uparams;

        // Active subscription: plan name and end.
        $plans = [];
        if ($dbman->table_exists('nit_sub_purchase')) {
            $rs = $DB->get_recordset_sql("SELECT p.id, p.userid, p.expires_at, s.name FROM {nit_sub_purchase} p
                JOIN {nit_subscription} s ON s.id = p.subscriptionid
               WHERE p.userid $usql AND p.status = 'active' AND (p.expires_at = 0 OR p.expires_at > :now)
            ORDER BY p.expires_at DESC", $uparams + ['now' => time()]);
            foreach ($rs as $r) {
                $plans[(int) $r->userid] = $plans[(int) $r->userid]
                    ?? ['name' => format_string($r->name, true, ['escape' => false]), 'ends' => (int) $r->expires_at];
            }
            $rs->close();
        }

        // The app signs in with a token: its last use counts as an access too.
        $apps = $DB->get_records_sql_menu("SELECT userid, MAX(lastaccess) FROM {external_tokens}
                                            WHERE userid $usql GROUP BY userid", $uparams);
        $quiz = data::quiz_avg($mypairs, $mypparams, 'userid');
        $finished = data::videos_finished($mypairs, $mypparams);
        $allcourses = array_values(array_unique(array_merge([], ...array_values(($showleft ? $leftcourses : $courses) ?: [[]]))));
        $videocount = data::videos_per_course($allcourses);

        // Money (site-wide viewers).
        $paid = $wallets = $flex = [];
        if ($this->money_visible()) {
            if ($dbman->table_exists('local_payments_transactions')) {
                $rs = $DB->get_recordset_sql("SELECT t.userid, t.currency, SUM(t.amount) AS total
                    FROM {local_payments_transactions} t WHERE t.userid $usql AND t.status IN ('completed', 'partially_refunded')
                GROUP BY t.userid, t.currency", $uparams);
                foreach ($rs as $r) {
                    $paid[(int) $r->userid][] = self::money((float) $r->total, (string) $r->currency);
                }
                $rs->close();
            }
            if ($dbman->table_exists('nit_wallet')) {
                $wallets = $DB->get_records_sql_menu("SELECT userid, SUM(balance_minor) FROM {nit_wallet}
                    WHERE userid $usql AND ownertype = 'student' GROUP BY userid", $uparams);
            }
            if ($dbman->table_exists('nit_package_purchase')) {
                $flex = $DB->get_records_sql_menu("SELECT userid, SUM(remaining_flex) FROM {nit_package_purchase}
                    WHERE userid $usql AND status = 'active' AND (expires_at = 0 OR expires_at > :fnow)
                 GROUP BY userid", $uparams + ['fnow' => time()]);
            }
        }

        $rows = [];
        foreach ($users as $u) {
            $id = (int) $u->id;
            $mine = ($showleft ? $leftcourses : $courses)[$id] ?? [];
            $done = array_filter(array_map(fn($cid) => data::completion($cid, $id), $mine), fn($v) => $v !== null);
            $videos = array_sum(array_map(fn($cid) => $videocount[$cid] ?? 0, $mine));
            $plan = $plans[$id] ?? null;
            $row = [
                'name' => fullname($u),
                'email' => $u->email,
                'phone' => $u->phone1 ?: '—',
                'registered' => self::date((int) $u->timecreated),
                'courses' => self::num(count($courses[$id] ?? [])),
                'left' => self::num(count($leftcourses[$id] ?? [])),
                'subscription' => $plan['name'] ?? '—',
                'subends' => $plan ? ($plan['ends'] ? self::date($plan['ends']) : self::str('noend')) : '—',
                'lastaccess' => self::date(max((int) $u->lastaccess, (int) ($apps[$id] ?? 0)), true),
                'completion' => $done ? self::pct(array_sum($done) / count($done)) : '—',
                'quiz' => isset($quiz[$id]) ? self::pct($quiz[$id]) : '—',
                'video' => $videos ? self::num($finished[$id] ?? 0) . ' / ' . self::num($videos) : '—',
            ];
            if ($this->money_visible()) {
                $row['paid'] = isset($paid[$id]) ? implode(' + ', $paid[$id]) : '0';
                $row['wallet'] = data::minor((int) ($wallets[$id] ?? 0));
                $row['flex'] = self::num($flex[$id] ?? 0);
            }
            $row['account'] = self::str($u->suspended ? 'account_suspended' : 'account_active');
            $rows[] = $row;
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
