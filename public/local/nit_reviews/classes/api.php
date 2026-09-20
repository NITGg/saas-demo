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

namespace local_nit_reviews;

/**
 * Course-reviews API — aggregates for the catalogue, and validated writes.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** @var array<int,object> Per-request aggregate cache: courseid => {avg,count}. */
    private static $aggcache = [];

    /**
     * Aggregate rating for one course: {avg (float, 1 dp), count (int)}.
     * count is 0 and avg 0.0 when there are no reviews.
     *
     * @param int $courseid
     * @return object
     */
    public static function get_aggregate(int $courseid): object {
        $all = self::get_aggregates([$courseid]);
        return $all[$courseid] ?? (object) ['avg' => 0.0, 'count' => 0];
    }

    /**
     * Aggregate ratings for many courses in one query (for the catalogue).
     *
     * @param int[] $courseids
     * @return array<int,object> courseid => {avg, count}
     */
    public static function get_aggregates(array $courseids): array {
        global $DB;
        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $result = [];
        $need = [];
        foreach ($courseids as $cid) {
            if (isset(self::$aggcache[$cid])) {
                $result[$cid] = self::$aggcache[$cid];
            } else {
                $need[] = $cid;
            }
        }
        if ($need) {
            // Default every needed course to "no reviews" first.
            foreach ($need as $cid) {
                $result[$cid] = (object) ['avg' => 0.0, 'count' => 0];
            }
            try {
                [$insql, $params] = $DB->get_in_or_equal($need);
                $rows = $DB->get_records_sql(
                    "SELECT courseid, AVG(rating) AS avgrating, COUNT(1) AS cnt
                       FROM {local_nit_reviews}
                      WHERE courseid $insql
                   GROUP BY courseid", $params);
                foreach ($rows as $r) {
                    $result[(int) $r->courseid] = (object) [
                        'avg'   => round((float) $r->avgrating, 1),
                        'count' => (int) $r->cnt,
                    ];
                }
            } catch (\Throwable $e) {
                // Table missing / DB error → treat as no reviews.
            }
            foreach ($need as $cid) {
                self::$aggcache[$cid] = $result[$cid];
            }
        }
        return $result;
    }

    /**
     * The current user's (or a given user's) review record for a course, or null.
     *
     * @param int $courseid
     * @param int $userid 0 = current user
     * @return \stdClass|null
     */
    public static function get_user_review(int $courseid, int $userid = 0): ?\stdClass {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        $rec = $DB->get_record('local_nit_reviews', ['courseid' => $courseid, 'userid' => $userid]);
        return $rec ?: null;
    }

    /**
     * Whether the user may rate this course: enrolled + has the capability, and
     * not the site/front page.
     *
     * @param int $courseid
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function can_rate(int $courseid, int $userid = 0): bool {
        global $USER;
        if ($courseid <= 1) {
            return false;
        }
        $userid = $userid ?: (int) $USER->id;
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        try {
            $context = \context_course::instance($courseid);
        } catch (\Throwable $e) {
            return false;
        }
        return is_enrolled($context, $userid, '', true)
            && has_capability('local/nit_reviews:rate', $context, $userid);
    }

    /**
     * Create or update the user's rating for a course. Enforces enrolment +
     * capability and clamps the rating to 1..5.
     *
     * @param int $courseid
     * @param int $rating 1..5
     * @param string $review
     * @param int $userid 0 = current user
     * @return bool true on save
     */
    public static function save(int $courseid, int $rating, string $review = '', int $userid = 0): bool {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        if (!self::can_rate($courseid, $userid)) {
            return false;
        }
        $rating = max(1, min(5, $rating));
        $now = time();
        $existing = self::get_user_review($courseid, $userid);
        if ($existing) {
            $existing->rating = $rating;
            $existing->review = $review;
            $existing->timemodified = $now;
            $DB->update_record('local_nit_reviews', $existing);
        } else {
            $DB->insert_record('local_nit_reviews', (object) [
                'courseid' => $courseid,
                'userid' => $userid,
                'rating' => $rating,
                'review' => $review,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        unset(self::$aggcache[$courseid]);
        return true;
    }

    /**
     * The courses (of a given set) whose average rating is >= a threshold.
     * Courses with no reviews are excluded.
     *
     * @param int[] $courseids
     * @param float $min minimum average (e.g. 4.0)
     * @return int[] matching course ids
     */
    public static function filter_by_min_rating(array $courseids, float $min): array {
        $aggs = self::get_aggregates($courseids);
        $out = [];
        foreach ($aggs as $cid => $agg) {
            if ($agg->count > 0 && $agg->avg >= $min) {
                $out[] = $cid;
            }
        }
        return $out;
    }
}
