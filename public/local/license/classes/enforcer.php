<?php
namespace local_license;

defined('MOODLE_INTERNAL') || die();

/**
 * Counts current usage and decides whether one more of something is allowed
 * under the academy's tier. Courses and teachers are counted academy-wide (the
 * whole site); ACTIVITY caps are counted per-course (a "3 quizzes" cap means 3
 * quizzes in each course, not 3 in the whole academy) — matching the dashboard's
 * "Per-course activity caps" label.
 */
class enforcer {

    /** @return int number of real courses (excludes the front page). */
    public static function count_courses(): int {
        global $DB;
        return $DB->count_records_select('course', 'id <> :site', ['site' => SITEID]);
    }

    /** @return bool can another course be created? */
    public static function can_add_course(): bool {
        $max = license::max_courses();
        if ($max < 0) {
            return true;
        }
        return self::count_courses() < $max;
    }

    /**
     * Count course modules whose module maps to a given bucket.
     *
     * Scoped to one course when $courseid is given (the normal enforcement path,
     * so caps are per-course); academy-wide when null (used by the status page's
     * totals).
     *
     * @param string $bucket
     * @param int|null $courseid limit the count to this course (null = whole site)
     * @return int
     */
    public static function count_bucket(string $bucket, ?int $courseid = null): int {
        global $DB;

        // Module names that fall in this bucket.
        $modnames = [];
        foreach (license::BUCKETS as $modname => $b) {
            if ($b === $bucket) {
                $modnames[] = $modname;
            }
        }
        // 'default' bucket = every module NOT explicitly bucketed.
        if ($bucket === 'default') {
            return self::count_default_bucket($courseid);
        }
        if (!$modnames) {
            return 0;
        }

        $moduleids = self::module_ids($modnames);
        if (!$moduleids) {
            return 0;
        }
        list($insql, $params) = $DB->get_in_or_equal($moduleids, SQL_PARAMS_NAMED);
        $where = "module $insql AND deletioninprogress = 0";
        if ($courseid !== null) {
            $where .= ' AND course = :courseid';
            $params['courseid'] = $courseid;
        }
        return $DB->count_records_select('course_modules', $where, $params);
    }

    /**
     * Count modules that are NOT in any named bucket (the 'default' bucket).
     *
     * @param int|null $courseid limit the count to this course (null = whole site)
     * @return int
     */
    protected static function count_default_bucket(?int $courseid = null): int {
        global $DB;
        $named = array_keys(license::BUCKETS);
        $namedids = self::module_ids($named);
        $params = [];
        if (!$namedids) {
            $where = 'deletioninprogress = 0';
        } else {
            list($insql, $params) = $DB->get_in_or_equal($namedids, SQL_PARAMS_NAMED, 'm', false); // NOT IN
            $where = "module $insql AND deletioninprogress = 0";
        }
        if ($courseid !== null) {
            $where .= ' AND course = :courseid';
            $params['courseid'] = $courseid;
        }
        // Moodle auto-creates an Announcements (news) forum in every course — it
        // must not eat into the tenant's 'default' activity quota, or a cap of 1
        // would block the very first real activity in an otherwise empty course.
        $forumid = (int) $DB->get_field('modules', 'id', ['name' => 'forum']);
        if ($forumid) {
            $where .= ' AND NOT (module = :forummod'
                    . ' AND instance IN (SELECT id FROM {forum} WHERE type = :newstype))';
            $params['forummod'] = $forumid;
            $params['newstype'] = 'news';
        }
        return $DB->count_records_select('course_modules', $where, $params);
    }

    /**
     * Resolve module ids for a list of module names.
     *
     * @param string[] $modnames
     * @return int[]
     */
    protected static function module_ids(array $modnames): array {
        global $DB;
        if (!$modnames) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($modnames, SQL_PARAMS_NAMED);
        return array_map('intval',
            array_keys($DB->get_records_select_menu('modules', "name $insql", $params, '', 'id, name')));
    }

    /**
     * Can another activity of this module type be added to a course?
     *
     * @param string $modname
     * @param int|null $courseid the course it's being added to (per-course cap)
     * @return bool
     */
    public static function can_add_activity(string $modname, ?int $courseid = null): bool {
        $bucket = license::bucket_for($modname);
        $limit  = license::bucket_limit($bucket);
        if ($limit < 0) {
            return true;
        }
        return self::count_bucket($bucket, $courseid) < $limit;
    }

    /**
     * Count distinct users who hold the editing-teacher role anywhere.
     *
     * Site admins are excluded: the academy owner runs on the Moodle `admin`
     * account (send_welcome points it at the owner), and the owner must not
     * consume a paid teacher seat just for teaching their own courses.
     *
     * @return int
     */
    public static function count_teachers(): int {
        global $DB, $CFG;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$roleid) {
            return 0;
        }
        $adminids = array_filter(array_map('intval', explode(',', (string) $CFG->siteadmins)));
        $params = [$roleid];
        $exclude = '';
        if ($adminids) {
            list($insql, $inparams) = $DB->get_in_or_equal($adminids, SQL_PARAMS_QM, 'param', false);
            $exclude = " AND userid {$insql}";
            $params = array_merge($params, $inparams);
        }
        return $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {role_assignments} WHERE roleid = ?{$exclude}", $params);
    }

    /** @return bool can another teacher be added? */
    public static function can_add_teacher(): bool {
        $max = license::max_teachers();
        if ($max < 0) {
            return true;
        }
        return self::count_teachers() < $max;
    }

    /**
     * A short "3 / 10" style usage string for a bucket (for the status page).
     *
     * @param string $bucket
     * @return string
     */
    public static function usage(string $bucket): string {
        $limit = license::bucket_limit($bucket);
        $count = self::count_bucket($bucket);
        return $count . ' / ' . ($limit < 0 ? '∞' : $limit);
    }
}
