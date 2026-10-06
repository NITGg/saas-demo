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

namespace local_nit_notifications;

/**
 * Who a notification goes to, and what each sender may choose.
 *
 * Audiences:
 *  - course_students / course_teachers: one course (courseid > 1), or every course
 *    the sender manages (courseid 0, a scoped manager only);
 *  - all_students, all_teachers, managers, admins: site-wide, for whoever holds
 *    local/nit_notifications:send on the whole site (admins, site managers).
 *
 * Definitions (the same as the teacher pages): a teacher holds a teacher or
 * editing-teacher role somewhere; a manager holds a manager role somewhere; site
 * admins are neither. A student is an active account that is none of those.
 * Course students are the course's active enrolments with a student role.
 * Recipients are active (not suspended, not deleted, confirmed) and never the sender.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience {

    /** Students of a course (or of every course the sender manages). */
    public const COURSE_STUDENTS = 'course_students';
    /** Teachers of a course (or of every course the sender manages). */
    public const COURSE_TEACHERS = 'course_teachers';
    /** Every student on the site. */
    public const ALL_STUDENTS = 'all_students';
    /** Every teacher on the site. */
    public const ALL_TEACHERS = 'all_teachers';
    /** Every manager (any level). */
    public const MANAGERS = 'managers';
    /** The site administrators. */
    public const ADMINS = 'admins';
    /** A given list of users (automatic notifications). */
    public const USERS = 'users';

    /** The capability behind sending and the log. */
    public const CAP = 'local/nit_notifications:send';

    /**
     * Whether the user may send to (and read the log of) the whole site.
     *
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function is_sitewide(int $userid = 0): bool {
        global $USER;
        return has_capability(self::CAP, \context_system::instance(), $userid ?: (int) $USER->id);
    }

    /**
     * The courses the user may notify: null = every course (site-wide sender),
     * else the course ids (maybe empty).
     *
     * @param int $userid 0 = current user
     * @return int[]|null
     */
    public static function course_ids(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        if (self::is_sitewide($userid)) {
            return null;
        }
        $courses = get_user_capability_course(self::CAP, $userid, true, '', 'id', 0) ?: [];
        return array_values(array_filter(array_map(fn($c) => (int) $c->id, $courses), fn($id) => $id > 1));
    }

    /**
     * Whether the user may send anything at all.
     *
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function can_send(int $userid = 0): bool {
        $ids = self::course_ids($userid);
        return $ids === null || !empty($ids);
    }

    /**
     * The audiences the user may pick, in display order.
     *
     * @param int $userid 0 = current user
     * @return string[]
     */
    public static function allowed(int $userid = 0): array {
        if (!self::can_send($userid)) {
            return [];
        }
        $out = [self::COURSE_STUDENTS, self::COURSE_TEACHERS];
        if (self::is_sitewide($userid)) {
            $out = array_merge($out, [self::ALL_STUDENTS, self::ALL_TEACHERS, self::MANAGERS, self::ADMINS]);
        }
        return $out;
    }

    /**
     * Whether an audience is about one course or the sender's courses.
     *
     * @param string $audience
     * @return bool
     */
    public static function is_course_audience(string $audience): bool {
        return in_array($audience, [self::COURSE_STUDENTS, self::COURSE_TEACHERS], true);
    }

    /**
     * Check a choice against the user's rights.
     *
     * @param string $audience
     * @param int $courseid the course for a course audience; 0 = every course the sender
     *     manages (a scoped manager only)
     * @param int $userid 0 = current user
     * @return string|null an error string key, or null when allowed
     */
    public static function validate(string $audience, int $courseid, int $userid = 0): ?string {
        if (!in_array($audience, self::allowed($userid), true)) {
            return 'err_audience';
        }
        if (!self::is_course_audience($audience)) {
            return null;
        }
        $scope = self::course_ids($userid);
        if ($courseid > 1) {
            return ($scope === null || in_array($courseid, $scope, true)) ? null : 'err_course';
        }
        // "Every course I manage" is for a scoped manager; site-wide senders pick a course.
        return $scope === null ? 'err_choosecourse' : null;
    }

    /**
     * The recipients of an audience, as user ids.
     *
     * @param string $audience
     * @param int $courseid see validate()
     * @param int $senderid the sender (left out); also defines "every course I manage"
     * @return int[]
     */
    public static function resolve(string $audience, int $courseid, int $senderid): array {
        global $DB;

        $courses = [];
        if (self::is_course_audience($audience)) {
            $courses = $courseid > 1 ? [$courseid] : (self::course_ids($senderid) ?? []);
        }

        $ids = [];
        switch ($audience) {
            case self::COURSE_STUDENTS:
                $roles = array_keys(get_archetype_roles('student'));
                foreach ($courses as $cid) {
                    $context = \context_course::instance($cid, IGNORE_MISSING);
                    if (!$context || !$roles) {
                        continue;
                    }
                    [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
                    [$rsql, $rparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'sr');
                    $ids = array_merge($ids, $DB->get_fieldset_sql(
                        "SELECT DISTINCT e.id
                           FROM ($esql) e
                           JOIN {role_assignments} ra ON ra.userid = e.id AND ra.contextid = :ctxid
                          WHERE ra.roleid $rsql",
                        $eparams + $rparams + ['ctxid' => $context->id]));
                }
                break;

            case self::COURSE_TEACHERS:
                $roles = self::teacher_roles();
                foreach ($courses as $cid) {
                    $context = \context_course::instance($cid, IGNORE_MISSING);
                    if (!$context || !$roles) {
                        continue;
                    }
                    [$rsql, $rparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'tr');
                    $ids = array_merge($ids, $DB->get_fieldset_sql(
                        "SELECT DISTINCT ra.userid FROM {role_assignments} ra WHERE ra.contextid = :ctxid AND ra.roleid $rsql",
                        $rparams + ['ctxid' => $context->id]));
                }
                $ids = self::without_admins($ids);
                break;

            case self::ALL_TEACHERS:
                $ids = self::without_admins(self::holders(self::teacher_roles()));
                break;

            case self::MANAGERS:
                $ids = self::without_admins(self::holders(array_keys(get_archetype_roles('manager'))));
                break;

            case self::ADMINS:
                $ids = array_keys(get_admins());
                break;

            case self::ALL_STUDENTS:
                $staff = array_merge(self::holders(self::teacher_roles()),
                    self::holders(array_keys(get_archetype_roles('manager'))), array_keys(get_admins()));
                $ids = $DB->get_fieldset_select('user', 'id',
                    'deleted = 0 AND suspended = 0 AND confirmed = 1 AND id <> :guest',
                    ['guest' => (int) $GLOBALS['CFG']->siteguest]);
                $ids = array_diff($ids, $staff);
                break;
        }

        return self::active(array_values(array_diff(array_unique(array_map('intval', $ids)), [$senderid])));
    }

    /**
     * How many users an audience reaches (the confirmation step).
     *
     * @param string $audience
     * @param int $courseid
     * @param int $senderid
     * @return int
     */
    public static function count(string $audience, int $courseid, int $senderid): int {
        return count(self::resolve($audience, $courseid, $senderid));
    }

    /**
     * Teacher and editing-teacher role ids.
     *
     * @return int[]
     */
    private static function teacher_roles(): array {
        return array_keys(get_archetype_roles('editingteacher') + get_archetype_roles('teacher'));
    }

    /**
     * Users holding any of these roles anywhere.
     *
     * @param int[] $roleids
     * @return int[]
     */
    private static function holders(array $roleids): array {
        global $DB;
        if (!$roleids) {
            return [];
        }
        [$rsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {role_assignments} WHERE roleid $rsql", $params));
    }

    /**
     * Drop site admins (they are enrolled as teacher in courses they create).
     *
     * @param int[] $ids
     * @return int[]
     */
    private static function without_admins(array $ids): array {
        return array_diff(array_map('intval', $ids), array_keys(get_admins()));
    }

    /**
     * Keep only active accounts (not deleted, not suspended, confirmed, not guest).
     *
     * @param int[] $ids
     * @return int[]
     */
    public static function active(array $ids): array {
        global $DB, $CFG;
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params['guest'] = (int) $CFG->siteguest;
            $out = array_merge($out, $DB->get_fieldset_select('user', 'id',
                "id $insql AND deleted = 0 AND suspended = 0 AND confirmed = 1 AND id <> :guest", $params));
        }
        $out = array_map('intval', $out);
        sort($out);
        return $out;
    }
}
