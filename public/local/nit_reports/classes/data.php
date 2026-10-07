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
 *  - a course student holds a student-archetype role in the course;
 *  - a teacher holds a teacher or editing-teacher role (site admins excluded);
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
     * SQL for "the (user, course) pairs of a role group in courses matching a condition":
     * columns userid, courseid. Active, non-deleted users only.
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
        [$rsql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'mr');
        $sql = "SELECT DISTINCT ra.userid, c.id AS courseid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                  JOIN {course} c ON c.id = ctx.instanceid AND c.id <> " . SITEID . "
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE ra.roleid $rsql AND $coursewhere";
        return [$sql, $rparams + $params];
    }

    /**
     * Average quiz result (percent) per user, over quizzes of the given courses.
     *
     * @param int[] $userids
     * @param int[]|null $courseids null = every course
     * @return array<int,float> userid => percent
     */
    public static function quiz_avg_by_user(array $userids, ?array $courseids): array {
        global $DB;
        if (!$userids || $courseids === []) {
            return [];
        }
        [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'qu');
        $csql = '';
        if ($courseids !== null) {
            [$in, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'qc');
            $csql = "AND gi.courseid $in";
            $params += $cparams;
        }
        return array_map('floatval', $DB->get_records_sql_menu(
            "SELECT gg.userid, AVG((gg.finalgrade - gi.grademin) / (gi.grademax - gi.grademin) * 100)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                    AND gi.grademax > gi.grademin
              WHERE gg.userid $usql $csql AND gg.finalgrade IS NOT NULL
           GROUP BY gg.userid", $params));
    }

    /**
     * Average quiz result (percent) per course.
     *
     * @param int[] $courseids
     * @return array<int,float> courseid => percent
     */
    public static function quiz_avg_by_course(array $courseids): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'qc');
        return array_map('floatval', $DB->get_records_sql_menu(
            "SELECT gi.courseid, AVG((gg.finalgrade - gi.grademin) / (gi.grademax - gi.grademin) * 100)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                    AND gi.grademax > gi.grademin
              WHERE gi.courseid $in AND gg.finalgrade IS NOT NULL
           GROUP BY gi.courseid", $params));
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
     * Average video watching (percent of the video played) per course, over the
     * videos students started.
     *
     * @param int[] $courseids
     * @return array<int,float> courseid => percent
     */
    public static function video_avg_by_course(array $courseids): array {
        global $DB;
        if (!$courseids || !self::has_videos()) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'vc');
        return array_map('floatval', $DB->get_records_sql_menu(
            "SELECT courseid, AVG(percent) FROM {nit_video_progress} WHERE courseid $in AND percent > 0 GROUP BY courseid",
            $params));
    }

    /**
     * Average video watching per user (over the videos they started) in the given courses.
     *
     * @param int[] $userids
     * @param int[]|null $courseids
     * @return array<int,float>
     */
    public static function video_avg_by_user(array $userids, ?array $courseids): array {
        global $DB;
        if (!$userids || $courseids === [] || !self::has_videos()) {
            return [];
        }
        [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'vu');
        $csql = '';
        if ($courseids !== null) {
            [$in, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'vc');
            $csql = "AND courseid $in";
            $params += $cparams;
        }
        return array_map('floatval', $DB->get_records_sql_menu(
            "SELECT userid, AVG(percent) FROM {nit_video_progress} WHERE userid $usql $csql AND percent > 0 GROUP BY userid",
            $params));
    }

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
