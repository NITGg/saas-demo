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
                    "SELECT r.courseid, AVG(r.rating) AS avgrating, COUNT(1) AS cnt
                       FROM {local_nit_reviews} r
                       JOIN {user} u ON u.id = r.userid AND u.deleted = 0
                      WHERE r.courseid $insql
                   GROUP BY r.courseid", $params);
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

    /** Longest review text accepted (same as the web form's maxlength). */
    public const MAX_REVIEW_LENGTH = 2000;

    /**
     * The course behind a courseid, or a business error when it does not exist
     * or is hidden from the current user.
     *
     * @param int $courseid
     * @return \stdClass course record
     * @throws \moodle_exception err_coursenotfound
     */
    public static function require_course(int $courseid): \stdClass {
        global $DB;
        $course = $courseid > 1 ? $DB->get_record('course', ['id' => $courseid]) : false;
        if (!$course || (!$course->visible
                && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($course->id)))) {
            throw new \moodle_exception('err_coursenotfound', 'local_nit_reviews');
        }
        return $course;
    }

    /**
     * How many reviews have each star value: [1 => n, …, 5 => n].
     *
     * @param int $courseid
     * @return array<int,int>
     */
    public static function get_distribution(int $courseid): array {
        global $DB;
        $out = array_fill(1, 5, 0);
        $rows = $DB->get_records_sql_menu(
            "SELECT rating, COUNT(1) FROM {local_nit_reviews} WHERE courseid = ? GROUP BY rating", [$courseid]);
        foreach ($rows as $rating => $count) {
            if ((int) $rating >= 1 && (int) $rating <= 5) {
                $out[(int) $rating] = (int) $count;
            }
        }
        return $out;
    }

    /**
     * One page of a course's reviews, newest first, with the author's name and
     * picture. Reviews of deleted users are left out.
     *
     * @param int $courseid
     * @param int $page 0-based
     * @param int $perpage
     * @return array{total:int, reviews:array} each review: id, userid, fullname,
     *     pictureurl (raw, untokenized), rating, review, timecreated, timemodified
     */
    public static function get_reviews(int $courseid, int $page = 0, int $perpage = 20): array {
        global $DB;
        $from = "FROM {local_nit_reviews} r
                 JOIN {user} u ON u.id = r.userid AND u.deleted = 0
                WHERE r.courseid = :courseid";
        $params = ['courseid' => $courseid];
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);

        $ufields = \core_user\fields::for_userpic()->with_name()->get_sql('u', false, '', 'authorid', false)->selects;
        $rows = $DB->get_records_sql("SELECT r.id, r.userid, r.rating, r.review, r.timecreated, r.timemodified, $ufields
                                      $from
                                   ORDER BY r.timemodified DESC, r.id DESC",
            $params, max(0, $page) * max(1, $perpage), max(1, $perpage));

        $pg = new \moodle_page();
        $pg->set_context(\context_system::instance());
        $reviews = [];
        foreach ($rows as $r) {
            $user = clone $r;
            $user->id = (int) $r->authorid;
            $picture = '';
            try {
                $up = new \user_picture($user);
                $up->size = 100;
                $picture = $up->get_url($pg)->out(false);
            } catch (\Throwable $e) {
                $picture = '';
            }
            $reviews[] = [
                'id' => (int) $r->id,
                'userid' => (int) $r->userid,
                'fullname' => fullname($user),
                'pictureurl' => $picture,
                'rating' => (int) $r->rating,
                'review' => trim((string) $r->review),
                'timecreated' => (int) $r->timecreated,
                'timemodified' => (int) $r->timemodified,
            ];
        }
        return ['total' => $total, 'reviews' => $reviews];
    }

    /**
     * Validated create/update for the app: like save(), but a bad rating, a
     * too-long text or a user who may not rate is a clear error instead of a
     * silent clamp / false.
     *
     * @param int $courseid
     * @param int $rating must be 1..5
     * @param string $review optional text, at most MAX_REVIEW_LENGTH characters
     * @param int $userid 0 = current user
     * @return \stdClass the saved review record
     * @throws \moodle_exception err_coursenotfound | err_cannotrate | err_invalidrating | err_reviewtoolong
     */
    public static function save_validated(int $courseid, int $rating, string $review = '', int $userid = 0): \stdClass {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        self::require_course($courseid);
        if (!self::can_rate($courseid, $userid)) {
            throw new \moodle_exception('err_cannotrate', 'local_nit_reviews');
        }
        if ($rating < 1 || $rating > 5) {
            throw new \moodle_exception('err_invalidrating', 'local_nit_reviews');
        }
        $review = trim($review);
        if (\core_text::strlen($review) > self::MAX_REVIEW_LENGTH) {
            throw new \moodle_exception('err_reviewtoolong', 'local_nit_reviews', '', self::MAX_REVIEW_LENGTH);
        }
        if (!self::save($courseid, $rating, $review, $userid)) {
            throw new \moodle_exception('err_cannotrate', 'local_nit_reviews');
        }
        return self::get_user_review($courseid, $userid);
    }

    /**
     * Delete the user's own review of a course.
     *
     * @param int $courseid
     * @param int $userid 0 = current user
     * @return bool true when a review was deleted, false when there was none
     */
    public static function delete_user_review(int $courseid, int $userid = 0): bool {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        $rec = self::get_user_review($courseid, $userid);
        if (!$rec) {
            return false;
        }
        $DB->delete_records('local_nit_reviews', ['id' => $rec->id]);
        unset(self::$aggcache[$courseid]);
        return true;
    }

    /**
     * Moderation: delete any review by id. Needs local/nit_reviews:manage in the
     * review's course.
     *
     * @param int $reviewid
     * @return \stdClass the deleted record
     * @throws \moodle_exception err_reviewnotfound
     * @throws \required_capability_exception
     */
    public static function delete_review(int $reviewid): \stdClass {
        global $DB;
        $rec = $DB->get_record('local_nit_reviews', ['id' => $reviewid]);
        if (!$rec) {
            throw new \moodle_exception('err_reviewnotfound', 'local_nit_reviews');
        }
        require_capability('local/nit_reviews:manage', \context_course::instance((int) $rec->courseid));
        $DB->delete_records('local_nit_reviews', ['id' => $rec->id]);
        unset(self::$aggcache[(int) $rec->courseid]);
        return $rec;
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
