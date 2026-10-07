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

namespace local_nit_reports;

/**
 * Shared queries of the reports: who is a student / teacher, quiz averages,
 * video watching, completion, money. Every method takes ids already limited to
 * the viewer's scope.
 *
 * Definitions (the same as the rest of the site):
 *  - a course member holds a role in the course AND an active enrolment there
 *    (not suspended, not ended): a student-archetype role makes a student, a
 *    teacher or editing-teacher role a teacher (site admins excluded);
 *  - a student who left a course is not a member any more but left traces there:
 *    a paid purchase, a quiz grade, video watching or an old enrolment. Their data
 *    is kept (and counts again if they come back) but course figures leave it out;
 *  - a quiz result is the grade-book grade of the quiz, as a percent of its range;
 *  - video watching is local_nit_videoprogress "percent" (share of the video played).
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data {

    /**
     * Student-archetype role ids.
     *
     * @return int[]
     */
    public static function student_roles(): array {
        return array_map('intval', array_keys(get_archetype_roles('student')));
    }

    /**
     * Teacher and editing-teacher role ids.
     *
     * @return int[]
     */
    public static function teacher_roles(): array {
        return array_map('intval', array_keys(get_archetype_roles('editingteacher') + get_archetype_roles('teacher')));
    }

    /**
     * A placeholder prefix not used before in this request (so two SQL pieces from
     * here can sit in one query).
     *
     * @param string $base
     * @return string
     */
    private static function prefix(string $base): string {
        static $n = 0;
        return $base . (++$n) . 'x';
    }

    /**
     * SQL: the user holds an active enrolment in the course (not suspended, not ended).
     *
     * @param string $user user id column
     * @param string $course course id column
     * @return array{0:string, 1:array}
     */
    private static function active_enrolment_sql(string $user, string $course): array {
        $p = self::prefix('ae');
        return ["EXISTS (SELECT 1 FROM {user_enrolments} {$p}ue JOIN {enrol} {$p}e ON {$p}e.id = {$p}ue.enrolid
                          WHERE {$p}ue.userid = $user AND {$p}e.courseid = $course
                            AND {$p}ue.status = " . ENROL_USER_ACTIVE . " AND {$p}e.status = " . ENROL_INSTANCE_ENABLED . "
                            AND {$p}ue.timestart <= :{$p}now1 AND ({$p}ue.timeend = 0 OR {$p}ue.timeend > :{$p}now2))",
            ["{$p}now1" => time(), "{$p}now2" => time()]];
    }

    /**
     * SQL for "the current members of a role group in courses matching a condition":
     * columns userid, courseid. A member holds the role in the course and an active
     * enrolment there; deleted users are left out.
     *
     * @param int[] $roleids
     * @param string $coursewhere condition on c (e.g. from scope::course_sql('c.id', …))
     * @param array $params its params
     * @return array{0:string, 1:array}
     */
    public static function members_sql(array $roleids, string $coursewhere, array $params): array {
        global $DB;
        if (!$roleids) {
            return ['SELECT 0 AS userid, 0 AS courseid FROM {user} WHERE 1 = 0', []];
        }
        [$rsql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, self::prefix('mr'));
        [$active, $aparams] = self::active_enrolment_sql('ra.userid', 'c.id');
        $sql = "SELECT DISTINCT ra.userid, c.id AS courseid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                  JOIN {course} c ON c.id = ctx.instanceid AND c.id <> " . SITEID . "
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE ra.roleid $rsql AND $active AND $coursewhere";
        return [$sql, $rparams + $aparams + $params];
    }

    /**
     * SQL for "the students who left courses matching a condition": columns userid,
     * courseid. They are not current students there, hold no teaching role there,
     * are not site admins, and left a trace: a paid purchase, a quiz grade, video
     * watching or an enrolment (suspended or ended ones included).
     *
     * @param string $coursewhere condition on c
     * @param array $params its params
     * @return array{0:string, 1:array}
     */
    public static function left_sql(string $coursewhere, array $params): array {
        global $DB, $CFG;
        $dbman = $DB->get_manager();
        $traces = ["SELECT ue.userid, e.courseid FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid",
            "SELECT gg.userid, gi.courseid FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
              WHERE gg.finalgrade IS NOT NULL"];
        if ($dbman->table_exists('local_payments_transactions')) {
            $traces[] = "SELECT t.userid, t.courseid FROM {local_payments_transactions} t
                          WHERE t.courseid > 0 AND t.status IN ('completed', 'partially_refunded')";
        }
        if (self::has_videos()) {
            $traces[] = "SELECT vp.userid, vp.courseid FROM {nit_video_progress} vp";
        }
        [$current, $cparams] = self::members_sql(self::student_roles(), '1 = 1', []);
        $staff = array_merge(self::teacher_roles(), array_map('intval', array_keys(get_archetype_roles('manager'))));
        [$ssql, $sparams] = $DB->get_in_or_equal($staff ?: [0], SQL_PARAMS_NAMED, self::prefix('lst'));
        $admins = array_map('intval', explode(',', $CFG->siteadmins)) ?: [0];
        [$asql, $aparams] = $DB->get_in_or_equal($admins, SQL_PARAMS_NAMED, self::prefix('lad'), false);
        $sql = "SELECT DISTINCT h.userid, h.courseid
                  FROM (" . implode(' UNION ', $traces) . ") h
                  JOIN {course} c ON c.id = h.courseid AND c.id <> " . SITEID . "
                  JOIN {user} u ON u.id = h.userid AND u.deleted = 0 AND u.id $asql
                 WHERE $coursewhere
                   AND NOT EXISTS (SELECT 1 FROM ($current) cur WHERE cur.userid = h.userid AND cur.courseid = h.courseid)
                   AND NOT EXISTS (SELECT 1 FROM {role_assignments} sra
                                     JOIN {context} sctx ON sctx.id = sra.contextid AND sctx.contextlevel = " . CONTEXT_COURSE . "
                                    WHERE sctx.instanceid = h.courseid AND sra.userid = h.userid AND sra.roleid $ssql)";
        return [$sql, $params + $cparams + $sparams + $aparams];
    }

    /**
     * Average quiz result (percent) over the given (user, course) pairs, per user or per course.
     *
     * @param string $pairs SQL with columns userid, courseid (members_sql() or left_sql())
     * @param array $params
     * @param string $by userid | courseid
     * @return array<int,float> id => percent
     */
    public static function quiz_avg(string $pairs, array $params, string $by): array {
        global $DB;
        $group = $by === 'courseid' ? 'gi.courseid' : 'gg.userid';
        return array_map('floatval', $DB->get_records_sql_menu(
            "SELECT $group, AVG((gg.finalgrade - gi.grademin) / (gi.grademax - gi.grademin) * 100)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                    AND gi.grademax > gi.grademin
               JOIN ($pairs) qm ON qm.userid = gg.userid AND qm.courseid = gi.courseid
              WHERE gg.finalgrade IS NOT NULL
           GROUP BY $group", $params));
    }

    /**
     * Whether video watching is recorded on this site.
     *
     * @return bool
     */
    public static function has_videos(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_video_progress');
    }

    /**
     * Installed video activity modules.
     *
     * @return string[]
     */
    public static function video_modules(): array {
        global $DB;
        return array_values(array_filter(['vimeo', 'vdocipher'], fn($m) => $DB->get_manager()->table_exists($m)));
    }

    /**
     * Number of video lessons per course.
     *
     * @param int[] $courseids
     * @return array<int,int>
     */
    public static function videos_per_course(array $courseids): array {
        global $DB;
        $mods = self::video_modules();
        if (!$courseids || !$mods) {
            return [];
        }
        [$msql, $params] = $DB->get_in_or_equal($mods, SQL_PARAMS_NAMED, self::prefix('vpm'));
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, self::prefix('vpc'));
        return array_map('intval', $DB->get_records_sql_menu("SELECT cm.course, COUNT(1) FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name $msql
           WHERE cm.course $csql AND cm.deletioninprogress = 0 GROUP BY cm.course", $params + $cparams));
    }

    /**
     * Average video watching over the given (user, course) pairs, per user or per
     * course: the share played of the videos they started.
     *
     * @param string $pairs SQL with columns userid, courseid
     * @param array $params
     * @param string $by userid | courseid
     * @return array<int,float> id => percent
     */
    public static function video_avg(string $pairs, array $params, string $by): array {
        global $DB;
        if (!self::has_videos()) {
            return [];
        }
        $group = $by === 'courseid' ? 'vp.courseid' : 'vp.userid';
        return array_map('floatval', $DB->get_records_sql_menu(
            "SELECT $group, AVG(vp.percent) FROM {nit_video_progress} vp
               JOIN ($pairs) vm ON vm.userid = vp.userid AND vm.courseid = vp.courseid
              WHERE vp.percent > 0 GROUP BY $group", $params));
    }

    /**
     * Videos finished (played 90%+) per user over the given (user, course) pairs,
     * counting only videos still in the course.
     *
     * @param string $pairs SQL with columns userid, courseid
     * @param array $params
     * @return array<int,int> userid => videos
     */
    public static function videos_finished(string $pairs, array $params): array {
        global $DB;
        if (!self::has_videos()) {
            return [];
        }
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT vp.userid, COUNT(DISTINCT vp.cmid) FROM {nit_video_progress} vp
               JOIN ($pairs) vm ON vm.userid = vp.userid AND vm.courseid = vp.courseid
               JOIN {course_modules} cm ON cm.id = vp.cmid AND cm.deletioninprogress = 0
              WHERE vp.percent >= " . self::FINISHED . " GROUP BY vp.userid", $params));
    }

    /** Played share (percent) from which a video counts as finished. */
    public const FINISHED = 90;

    /**
     * Course completion percent of a user (core completion), null when the course
     * does not track completion.
     *
     * @param int $courseid
     * @param int $userid
     * @return float|null
     */
    public static function completion(int $courseid, int $userid): ?float {
        static $courses = [];
        if (!isset($courses[$courseid])) {
            $courses[$courseid] = get_course($courseid);
        }
        $pct = \core_completion\progress::get_course_progress_percentage($courses[$courseid], $userid);
        return $pct === null ? null : (float) $pct;
    }

    /**
     * Minor units (piastres) to a display amount in EGP.
     *
     * @param int|float $minor
     * @return string
     */
    public static function minor(int|float $minor): string {
        return format_float($minor / 100, 2) . ' ' . get_string('currency_egp', 'local_nit_reports');
    }

    /**
     * Course names (multilang resolved).
     *
     * @param int[] $ids
     * @return array<int,string>
     */
    public static function course_names(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach ($DB->get_records_list('course', 'id', $ids, '', 'id, fullname') as $c) {
            $out[(int) $c->id] = format_string($c->fullname, true, ['escape' => false]);
        }
        return $out;
    }

    /**
     * User full names.
     *
     * @param int[] $ids
     * @return array<int,string>
     */
    public static function user_names(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $fields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
        $out = [];
        foreach ($DB->get_records_list('user', 'id', $ids, '', 'id, ' . $fields) as $u) {
            $out[(int) $u->id] = fullname($u);
        }
        return $out;
    }

    /**
     * A SQL condition matching a user's name or email.
     *
     * @param string $alias user table alias
     * @param string $q
     * @return array{0:string, 1:array}
     */
    public static function user_search_sql(string $alias, string $q): array {
        global $DB;
        if ($q === '') {
            return ['1 = 1', []];
        }
        $like = '%' . $DB->sql_like_escape($q) . '%';
        return ['(' . $DB->sql_like($DB->sql_fullname("$alias.firstname", "$alias.lastname"), ':usq1', false)
            . ' OR ' . $DB->sql_like("$alias.email", ':usq2', false)
            . ' OR ' . $DB->sql_like("$alias.phone1", ':usq3', false) . ')',
            ['usq1' => $like, 'usq2' => $like, 'usq3' => $like]];
    }
}
