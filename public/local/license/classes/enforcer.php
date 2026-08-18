<?php
namespace local_license;

defined('MOODLE_INTERNAL') || die();

/**
 * Counts current usage and decides whether one more of something is allowed
 * under the academy's tier. All counts are academy-wide (the whole site), which
 * matches how the tiers are described ("1 course, 4 videos, 2 quizzes").
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
     * Count course modules whose module maps to a given bucket, academy-wide.
     *
     * @param string $bucket
     * @return int
     */
    public static function count_bucket(string $bucket): int {
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
            return self::count_default_bucket();
        }
        if (!$modnames) {
            return 0;
        }

        $moduleids = self::module_ids($modnames);
        if (!$moduleids) {
            return 0;
        }
        list($insql, $params) = $DB->get_in_or_equal($moduleids, SQL_PARAMS_NAMED);
        return $DB->count_records_select('course_modules',
            "module $insql AND deletioninprogress = 0", $params);
    }

    /**
     * Count modules that are NOT in any named bucket (the 'default' bucket).
     *
     * @return int
     */
    protected static function count_default_bucket(): int {
        global $DB;
        $named = array_keys(license::BUCKETS);
        $namedids = self::module_ids($named);
        if (!$namedids) {
            return $DB->count_records_select('course_modules', 'deletioninprogress = 0');
        }
        list($insql, $params) = $DB->get_in_or_equal($namedids, SQL_PARAMS_NAMED, 'm', false); // NOT IN
        return $DB->count_records_select('course_modules',
            "module $insql AND deletioninprogress = 0", $params);
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
     * Can another activity of this module type be added?
     *
     * @param string $modname
     * @return bool
     */
    public static function can_add_activity(string $modname): bool {
        $bucket = license::bucket_for($modname);
        $limit  = license::bucket_limit($bucket);
        if ($limit < 0) {
            return true;
        }
        return self::count_bucket($bucket) < $limit;
    }

    /**
     * Count distinct users who hold the editing-teacher role anywhere.
     *
     * @return int
     */
    public static function count_teachers(): int {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$roleid) {
            return 0;
        }
        return $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {role_assignments} WHERE roleid = ?", [$roleid]);
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
