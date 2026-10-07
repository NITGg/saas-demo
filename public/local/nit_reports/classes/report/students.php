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
 * Students: who they are, when they registered, how many courses, their active
 * subscription, last access, completion, quiz and video averages.
 *
 * Who is listed: site-wide without a course filter, every student account (an
 * active account that is not a teacher, a manager or an admin — even with no
 * course); otherwise the students of the courses in scope. The period filters the
 * registration date.
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

    public function columns(): array {
        return [
            'name' => get_string('fullname'),
            'email' => get_string('email'),
            'phone' => get_string('phone1'),
            'registered' => self::str('col_registered'),
            'courses' => self::str('col_courses'),
            'subscription' => self::str('col_subscription'),
            'lastaccess' => self::str('col_lastaccess'),
            'completion' => self::str('col_completion'),
            'quiz' => self::str('col_quizavg'),
            'video' => self::str('col_videoavg'),
        ];
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
        if ($this->s->sitewide && !$this->f->courseid) {
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
        [$cwhere, $cparams] = $this->course_where('c.id');
        [$members, $mparams] = data::members_sql(data::student_roles(), $cwhere, $cparams);
        return ["FROM {user} u
                WHERE u.id IN (SELECT m.userid FROM ($members) m) AND $period AND $search", $params + $mparams];
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $week = (int) $DB->count_records_sql("SELECT COUNT(1) $from AND u.lastaccess >= :wk", $params + ['wk' => time() - WEEKSECS]);
        $cards = [
            ['label' => self::str('card_students'), 'value' => self::num($total)],
            ['label' => self::str('card_activeweek'), 'value' => self::num($week)],
        ];
        if ($DB->get_manager()->table_exists('nit_sub_purchase')) {
            $subs = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT p.userid) FROM {nit_sub_purchase} p
                WHERE p.status = 'active' AND (p.expires_at = 0 OR p.expires_at > :now)
                  AND p.userid IN (SELECT u.id $from)", $params + ['now' => time()]);
            $cards[] = ['label' => self::str('card_withsubscription'), 'value' => self::num($subs)];
        }
        return $cards;
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, u.email, u.phone1, u.timecreated, u.lastaccess, $names
                                       $from ORDER BY u.timecreated DESC, u.id DESC",
            $params, $perpage ? $page * $perpage : 0, $perpage);
        if (!$users) {
            return ['total' => $total, 'rows' => []];
        }
        $ids = array_keys($users);
        $scopeids = $this->s->course_ids(static::level());

        // Their courses in scope.
        [$cwhere, $cparams] = $this->course_where('c.id');
        [$members, $mparams] = data::members_sql(data::student_roles(), $cwhere, $cparams);
        [$usql, $uparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'su');
        $courses = [];
        foreach ($DB->get_recordset_sql("SELECT m.userid, m.courseid FROM ($members) m WHERE m.userid $usql",
                $mparams + $uparams) as $r) {
            $courses[(int) $r->userid][] = (int) $r->courseid;
        }

        // Active subscription plan names.
        $plans = [];
        if ($DB->get_manager()->table_exists('nit_sub_purchase')) {
            $rs = $DB->get_recordset_sql("SELECT p.id, p.userid, s.name FROM {nit_sub_purchase} p
                JOIN {nit_subscription} s ON s.id = p.subscriptionid
               WHERE p.userid $usql AND p.status = 'active' AND (p.expires_at = 0 OR p.expires_at > :now)
            ORDER BY p.expires_at DESC", $uparams + ['now' => time()]);
            foreach ($rs as $r) {
                $plans[(int) $r->userid] = $plans[(int) $r->userid] ?? format_string($r->name, true, ['escape' => false]);
            }
            $rs->close();
        }

        // The app signs in with a token: its last use counts as an access too.
        $apps = $DB->get_records_sql_menu("SELECT userid, MAX(lastaccess) FROM {external_tokens}
                                            WHERE userid $usql GROUP BY userid", $uparams);
        $quiz = data::quiz_avg_by_user($ids, $scopeids);
        $video = data::video_avg_by_user($ids, $scopeids);

        $rows = [];
        foreach ($users as $u) {
            $mine = $courses[(int) $u->id] ?? [];
            $done = array_filter(array_map(fn($cid) => data::completion($cid, (int) $u->id), $mine), fn($v) => $v !== null);
            $rows[] = [
                'name' => fullname($u),
                'email' => $u->email,
                'phone' => $u->phone1 ?: '—',
                'registered' => self::date((int) $u->timecreated),
                'courses' => self::num(count($mine)),
                'subscription' => $plans[(int) $u->id] ?? '—',
                'lastaccess' => self::date(max((int) $u->lastaccess, (int) ($apps[$u->id] ?? 0)), true),
                'completion' => $done ? self::pct(array_sum($done) / count($done)) : '—',
                'quiz' => isset($quiz[$u->id]) ? self::pct($quiz[$u->id]) : '—',
                'video' => isset($video[$u->id]) ? self::pct($video[$u->id]) : '—',
            ];
        }
        return ['total' => $total, 'rows' => $rows];
    }
}
