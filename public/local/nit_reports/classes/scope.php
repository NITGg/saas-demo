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
 * What one user may see in the reports.
 *
 * Three levels, each report declares one:
 *  - site: whole-site reports (sales, subscriptions, packages, coupons, codes) —
 *    admins and site managers only;
 *  - manage: course reports for managers — site-wide, or the courses of the
 *    categories / courses they manage;
 *  - teaching: the reports a teacher sees for their own courses (students, course
 *    performance, videos, results, own dues) — also open to managers.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scope {

    /** Whole-site reports. */
    public const SITE = 'site';
    /** Manager reports. */
    public const MANAGE = 'manage';
    /** Teacher reports. */
    public const TEACHING = 'teaching';

    /** @var int */
    public $userid;

    /** @var bool the user sees every course */
    public $sitewide;

    /** @var int[] courses the user manages (when not site-wide) */
    public $managed = [];

    /** @var int[] courses the user teaches or manages (when not site-wide) */
    public $teaching = [];

    /**
     * The scope of a user.
     *
     * @param int $userid 0 = current user
     * @return self
     */
    public static function for_user(int $userid = 0): self {
        global $USER;
        $s = new self();
        $s->userid = $userid ?: (int) $USER->id;
        $s->sitewide = has_capability('local/nit_reports:view', \context_system::instance(), $s->userid);
        if (!$s->sitewide) {
            $s->managed = self::courses('local/nit_reports:view', $s->userid);
            $s->teaching = array_values(array_unique(array_merge($s->managed,
                self::courses('local/nit_reports:viewteaching', $s->userid))));
        }
        return $s;
    }

    /**
     * Course ids where the user holds a capability.
     *
     * @param string $cap
     * @param int $userid
     * @return int[]
     */
    private static function courses(string $cap, int $userid): array {
        $courses = get_user_capability_course($cap, $userid, true, '', 'id', 0) ?: [];
        return array_values(array_filter(array_map(fn($c) => (int) $c->id, $courses), fn($id) => $id > 1));
    }

    /**
     * Whether the user may open reports of a level.
     *
     * @param string $level
     * @return bool
     */
    public function can(string $level): bool {
        if ($this->sitewide) {
            return true;
        }
        if ($level === self::SITE) {
            return false;
        }
        return $level === self::MANAGE ? !empty($this->managed) : !empty($this->teaching);
    }

    /**
     * Whether the user sees only what a teacher sees (no manager scope at all).
     *
     * @return bool
     */
    public function teacher_only(): bool {
        return !$this->sitewide && empty($this->managed);
    }

    /**
     * The courses of a level: null = every course.
     *
     * @param string $level
     * @return int[]|null
     */
    public function course_ids(string $level): ?array {
        if ($this->sitewide) {
            return null;
        }
        return $level === self::MANAGE ? $this->managed : $this->teaching;
    }

    /**
     * A SQL condition limiting a course id column to a level's courses.
     *
     * @param string $column e.g. "c.id"
     * @param string $level
     * @param string $prefix param name prefix
     * @return array{0:string, 1:array} ["1 = 1" when site-wide]
     */
    public function course_sql(string $column, string $level, string $prefix = 'sc'): array {
        global $DB;
        $ids = $this->course_ids($level);
        if ($ids === null) {
            return ['1 = 1', []];
        }
        if (!$ids) {
            return ['1 = 0', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, $prefix);
        return ["$column $insql", $params];
    }
}
