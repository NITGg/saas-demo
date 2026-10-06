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
 * Reviews API — learner ratings of courses and of teachers, and their moderation.
 *
 * A review is stars (1–5) plus an optional comment. It targets either a course
 * (teacherid 0) or a teacher in a course; courseid 0 with a teacher is a review
 * of that teacher's private (Flex) lessons. One review per learner per target.
 *
 * Moderation: stars alone are public at once; a review with a comment waits
 * (pending) until a moderator approves it, and a rejected one is hidden. Only
 * approved reviews are shown or counted in an average. Editing a review that
 * has a comment sends it back to pending.
 *
 * Who moderates: anyone with local/nit_reviews:moderate in the review's course —
 * a site manager everywhere, a manager assigned on a category or a course only
 * there. Lesson reviews (courseid 0) need the capability on the whole site.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** Waits for a moderator. */
    public const STATUS_PENDING = 0;
    /** Public and counted in averages. */
    public const STATUS_APPROVED = 1;
    /** Hidden; the author sees why. */
    public const STATUS_REJECTED = 2;

    /** Longest review text accepted (same as the web form's maxlength). */
    public const MAX_REVIEW_LENGTH = 2000;

    /** Longest rejection reason accepted. */
    public const MAX_REASON_LENGTH = 500;

    /** @var array<int,object> Per-request course aggregate cache: courseid => {avg,count}. */
    private static $aggcache = [];

    /** @var array<int,object> Per-request teacher aggregate cache: teacherid => {avg,count}. */
    private static $teacheraggcache = [];

    // =========================================================================
    // Averages.
    // =========================================================================

    /**
     * Aggregate rating for one course: {avg (float, 1 dp), count (int)}.
     * count is 0 and avg 0.0 when there are no approved reviews.
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
        return self::aggregates('courseid', 'r.teacherid = 0', $courseids, self::$aggcache);
    }

    /**
     * Aggregate rating of one teacher over every course (and private lessons).
     *
     * @param int $teacherid
     * @return object {avg, count}
     */
    public static function get_teacher_aggregate(int $teacherid): object {
        $all = self::get_teacher_aggregates([$teacherid]);
        return $all[$teacherid] ?? (object) ['avg' => 0.0, 'count' => 0];
    }

    /**
     * Aggregate ratings for many teachers in one query (home cards, teacher list).
     *
     * @param int[] $teacherids
     * @return array<int,object> teacherid => {avg, count}
     */
    public static function get_teacher_aggregates(array $teacherids): array {
        return self::aggregates('teacherid', 'r.teacherid > 0', $teacherids, self::$teacheraggcache);
    }

    /**
     * Shared body of the aggregate lookups: approved reviews by live authors,
     * grouped by one column, cached for the request.
     *
     * @param string $column courseid | teacherid
     * @param string $where extra condition on r
     * @param int[] $ids
     * @param array $cache the per-request cache to read and fill
     * @return array<int,object> id => {avg, count}
     */
    private static function aggregates(string $column, string $where, array $ids, array &$cache): array {
        global $DB;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $result = [];
        $need = [];
        foreach ($ids as $id) {
            if (isset($cache[$id])) {
                $result[$id] = $cache[$id];
            } else {
                $need[] = $id;
            }
        }
        if ($need) {
            foreach ($need as $id) {
                $result[$id] = (object) ['avg' => 0.0, 'count' => 0];
            }
            try {
                [$insql, $params] = $DB->get_in_or_equal($need, SQL_PARAMS_NAMED);
                $params['approved'] = self::STATUS_APPROVED;
                $rows = $DB->get_records_sql(
                    "SELECT r.$column AS target, AVG(r.rating) AS avgrating, COUNT(1) AS cnt
                       FROM {local_nit_reviews} r
                       JOIN {user} u ON u.id = r.userid AND u.deleted = 0
                      WHERE r.$column $insql AND $where AND r.status = :approved
                   GROUP BY r.$column", $params);
                foreach ($rows as $r) {
                    $result[(int) $r->target] = (object) [
                        'avg'   => round((float) $r->avgrating, 1),
                        'count' => (int) $r->cnt,
                    ];
                }
            } catch (\Throwable $e) {
                // Table missing / DB error → treat as no reviews.
            }
            foreach ($need as $id) {
                $cache[$id] = $result[$id];
            }
        }
        return $result;
    }

    /**
     * How many approved reviews have each star value: [1 => n, …, 5 => n].
     *
     * @param int $courseid the course (its own reviews), or 0 with a teacher
     * @param int $teacherid 0 = the course; else that teacher over every course
     * @return array<int,int>
     */
    public static function get_distribution(int $courseid, int $teacherid = 0): array {
        global $DB;
        $out = array_fill(1, 5, 0);
        if ($teacherid) {
            $where = 'teacherid = :teacherid';
            $params = ['teacherid' => $teacherid];
        } else {
            $where = 'courseid = :courseid AND teacherid = 0';
            $params = ['courseid' => $courseid];
        }
        $params['approved'] = self::STATUS_APPROVED;
        $rows = $DB->get_records_sql_menu(
            "SELECT rating, COUNT(1) FROM {local_nit_reviews} WHERE $where AND status = :approved GROUP BY rating", $params);
        foreach ($rows as $rating => $count) {
            if ((int) $rating >= 1 && (int) $rating <= 5) {
                $out[(int) $rating] = (int) $count;
            }
        }
        return $out;
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

    // =========================================================================
    // Who may rate what.
    // =========================================================================

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
     * Whether the user may rate a teacher: in a course, the learner may rate the
     * course and the teacher teaches it; for private lessons (courseid 0), the
     * learner finished a lesson with that teacher. Nobody rates themself.
     *
     * @param int $courseid the course, or 0 for private lessons
     * @param int $teacherid
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function can_rate_teacher(int $courseid, int $teacherid, int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        if ($teacherid <= 0 || !$userid || $teacherid === $userid || isguestuser($userid)) {
            return false;
        }
        if ($courseid === 0) {
            return self::had_lesson_with($userid, $teacherid);
        }
        return self::can_rate($courseid, $userid) && isset(self::get_course_teachers($courseid)[$teacherid]);
    }

    /**
     * The teachers of a course: users with a teacher or editing-teacher role in
     * it, minus site admins (they are enrolled as teacher in every course they
     * create, see local_academy's observer) — the same rule as the teacher pages.
     *
     * @param int $courseid
     * @return array<int,\stdClass> teacherid => user record (name + picture fields)
     */
    public static function get_course_teachers(int $courseid): array {
        global $DB;
        static $cache = [];
        if (isset($cache[$courseid])) {
            return $cache[$courseid];
        }
        $roleids = array_keys(get_archetype_roles('editingteacher') + get_archetype_roles('teacher'));
        $out = [];
        if ($courseid > 1 && $roleids) {
            [$rsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
            $params += ['ctxlevel' => CONTEXT_COURSE, 'courseid' => $courseid];
            $fields = \core_user\fields::for_userpic()->with_name()->get_sql('u', false, '', '', false)->selects;
            $rows = $DB->get_records_sql(
                "SELECT DISTINCT $fields
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel AND ctx.instanceid = :courseid
                   JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
                  WHERE ra.roleid $rsql", $params);
            foreach ($rows as $u) {
                if (!is_siteadmin((int) $u->id)) {
                    $out[(int) $u->id] = $u;
                }
            }
            \core_collator::asort_objects_by_property($out, 'firstname');
        }
        return $cache[$courseid] = $out;
    }

    /**
     * Where the user may rate a teacher: the shared courses they may rate in,
     * plus 0 when they finished a private lesson with them.
     *
     * @param int $teacherid
     * @param int $userid 0 = current user
     * @return int[] course ids (0 = private lessons)
     */
    public static function rateable_courses_for_teacher(int $teacherid, int $userid = 0): array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        if ($teacherid <= 0 || !$userid || $teacherid === $userid || isguestuser($userid)) {
            return [];
        }
        $out = [];
        foreach (enrol_get_all_users_courses($userid, true, 'id') as $course) {
            if (self::can_rate_teacher((int) $course->id, $teacherid, $userid)) {
                $out[] = (int) $course->id;
            }
        }
        if (self::had_lesson_with($userid, $teacherid)) {
            $out[] = 0;
        }
        return $out;
    }

    /**
     * Whether a learner finished a private lesson with a teacher (local_nit_lessons).
     *
     * @param int $studentid
     * @param int $teacherid
     * @return bool
     */
    private static function had_lesson_with(int $studentid, int $teacherid): bool {
        global $DB;
        try {
            return $DB->get_manager()->table_exists('nit_lesson') && $DB->record_exists('nit_lesson',
                ['studentid' => $studentid, 'teacherid' => $teacherid, 'status' => 'completed']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =========================================================================
    // A learner's own reviews.
    // =========================================================================

    /**
     * The user's review of a course (teacherid 0) or of a teacher in it, or null.
     *
     * @param int $courseid
     * @param int $userid 0 = current user
     * @param int $teacherid 0 = the course itself
     * @return \stdClass|null
     */
    public static function get_user_review(int $courseid, int $userid = 0, int $teacherid = 0): ?\stdClass {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        $rec = $DB->get_record('local_nit_reviews', ['courseid' => $courseid, 'teacherid' => $teacherid, 'userid' => $userid]);
        return $rec ?: null;
    }

    /**
     * Every review the user wrote (any status), newest first.
     *
     * @param int $userid 0 = current user
     * @return \stdClass[]
     */
    public static function get_user_reviews(int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        return array_values($DB->get_records('local_nit_reviews', ['userid' => $userid], 'timemodified DESC, id DESC'));
    }

    /**
     * Create or update the user's rating of a course (teacherid 0) or of a
     * teacher in it. Enforces who may rate and clamps the rating to 1..5.
     *
     * Stars alone are approved at once; a comment makes it pending. Saving the
     * same stars and comment again keeps the current status.
     *
     * @param int $courseid
     * @param int $rating 1..5
     * @param string $review
     * @param int $userid 0 = current user
     * @param int $teacherid 0 = the course itself
     * @return bool true on save
     */
    public static function save(int $courseid, int $rating, string $review = '', int $userid = 0, int $teacherid = 0): bool {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        $allowed = $teacherid ? self::can_rate_teacher($courseid, $teacherid, $userid) : self::can_rate($courseid, $userid);
        if (!$allowed) {
            return false;
        }
        $rating = max(1, min(5, $rating));
        $review = trim($review);
        $status = $review === '' ? self::STATUS_APPROVED : self::STATUS_PENDING;
        $now = time();
        $existing = self::get_user_review($courseid, $userid, $teacherid);
        if ($existing && (int) $existing->rating === $rating && trim((string) $existing->review) === $review) {
            return true;
        }
        if ($existing) {
            $existing->rating = $rating;
            $existing->review = $review;
            $existing->status = $status;
            $existing->rejectreason = null;
            $existing->reviewedby = 0;
            $existing->timereviewed = 0;
            $existing->timemodified = $now;
            $DB->update_record('local_nit_reviews', $existing);
            $record = $existing;
        } else {
            $record = (object) [
                'courseid' => $courseid,
                'teacherid' => $teacherid,
                'userid' => $userid,
                'rating' => $rating,
                'review' => $review,
                'status' => $status,
                'reviewedby' => 0,
                'timereviewed' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_nit_reviews', $record);
        }
        self::forget($record);
        if ($status === self::STATUS_PENDING) {
            notifier::review_pending($record);
        }
        return true;
    }

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
     * Validated create/update for the app: like save(), but a bad rating, a
     * too-long text or a user who may not rate is a clear error instead of a
     * silent clamp / false.
     *
     * @param int $courseid the course (0 = private lessons, teacher reviews only)
     * @param int $rating must be 1..5
     * @param string $review optional text, at most MAX_REVIEW_LENGTH characters
     * @param int $userid 0 = current user
     * @param int $teacherid 0 = the course itself
     * @return \stdClass the saved review record
     * @throws \moodle_exception err_coursenotfound | err_cannotrate | err_cannotrateteacher
     *     | err_invalidrating | err_reviewtoolong
     */
    public static function save_validated(int $courseid, int $rating, string $review = '', int $userid = 0,
            int $teacherid = 0): \stdClass {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        if (!$teacherid || $courseid !== 0) {
            self::require_course($courseid);
        }
        if ($teacherid && !self::can_rate_teacher($courseid, $teacherid, $userid)) {
            throw new \moodle_exception('err_cannotrateteacher', 'local_nit_reviews');
        }
        if (!$teacherid && !self::can_rate($courseid, $userid)) {
            throw new \moodle_exception('err_cannotrate', 'local_nit_reviews');
        }
        if ($rating < 1 || $rating > 5) {
            throw new \moodle_exception('err_invalidrating', 'local_nit_reviews');
        }
        $review = trim($review);
        if (\core_text::strlen($review) > self::MAX_REVIEW_LENGTH) {
            throw new \moodle_exception('err_reviewtoolong', 'local_nit_reviews', '', self::MAX_REVIEW_LENGTH);
        }
        if (!self::save($courseid, $rating, $review, $userid, $teacherid)) {
            throw new \moodle_exception('err_cannotrate', 'local_nit_reviews');
        }
        return self::get_user_review($courseid, $userid, $teacherid);
    }

    /**
     * Delete the user's own review of a course or of a teacher in it.
     *
     * @param int $courseid
     * @param int $userid 0 = current user
     * @param int $teacherid 0 = the course itself
     * @return bool true when a review was deleted, false when there was none
     */
    public static function delete_user_review(int $courseid, int $userid = 0, int $teacherid = 0): bool {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        $rec = self::get_user_review($courseid, $userid, $teacherid);
        if (!$rec) {
            return false;
        }
        $DB->delete_records('local_nit_reviews', ['id' => $rec->id]);
        self::forget($rec);
        return true;
    }

    // =========================================================================
    // Public lists (approved only).
    // =========================================================================

    /**
     * One page of a course's approved reviews, newest first, with the author's
     * name and picture. Reviews of deleted users are left out.
     *
     * @param int $courseid
     * @param int $page 0-based
     * @param int $perpage
     * @return array{total:int, reviews:array} each review: id, userid, fullname,
     *     pictureurl (raw, untokenized), rating, review, timecreated, timemodified
     */
    public static function get_reviews(int $courseid, int $page = 0, int $perpage = 20): array {
        return self::list_reviews('r.courseid = :courseid AND r.teacherid = 0 AND r.status = :approved',
            ['courseid' => $courseid, 'approved' => self::STATUS_APPROVED], $page, $perpage);
    }

    /**
     * One page of a teacher's approved reviews over every course, newest first.
     * Each review also has courseid and coursename ('' for private lessons).
     *
     * @param int $teacherid
     * @param int $page 0-based
     * @param int $perpage
     * @return array{total:int, reviews:array}
     */
    public static function get_teacher_reviews(int $teacherid, int $page = 0, int $perpage = 20): array {
        return self::list_reviews('r.teacherid = :teacherid AND r.status = :approved',
            ['teacherid' => $teacherid, 'approved' => self::STATUS_APPROVED], $page, $perpage);
    }

    /**
     * One page of reviews for a course (course reviews and/or teacher reviews in that course).
     *
     * @param int $courseid
     * @param array $filters [rating => 1..5, teacherid => int, status => int|string, type => string, q => string]
     * @param int $page 0-based
     * @param int $perpage
     * @return array{total:int, reviews:array}
     */
    public static function get_course_all_reviews(int $courseid, array $filters = [], int $page = 0, int $perpage = 20): array {
        global $DB;
        $where = ['r.courseid = :courseid'];
        $params = ['courseid' => $courseid];

        // Status filter: defaults to APPROVED if not specified.
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
            $where[] = 'r.status = :status';
            $params['status'] = (int) $filters['status'];
        } else if (!isset($filters['status'])) {
            $where[] = 'r.status = :status';
            $params['status'] = self::STATUS_APPROVED;
        }

        if (!empty($filters['rating']) && (int) $filters['rating'] >= 1 && (int) $filters['rating'] <= 5) {
            $where[] = 'r.rating = :rating';
            $params['rating'] = (int) $filters['rating'];
        }

        $type = $filters['type'] ?? '';
        if ($type === 'course') {
            $where[] = 'r.teacherid = 0';
        } else if ($type === 'teacher') {
            $where[] = 'r.teacherid > 0';
        }

        if (!empty($filters['teacherid'])) {
            $where[] = 'r.teacherid = :teacherid';
            $params['teacherid'] = (int) $filters['teacherid'];
        }

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $like = '%' . $DB->sql_like_escape($q) . '%';
            $where[] = '(' . $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':q1', false) . ' OR '
                . $DB->sql_like('r.review', ':q2', false) . ')';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        return self::list_reviews(implode(' AND ', $where), $params, $page, $perpage);
    }

    /**
     * Shared body of the review lists: a page of reviews matching $where, with
     * the author's name and picture and the course name.
     *
     * @param string $where condition on r (and u, c)
     * @param array $params named params for $where
     * @param int $page 0-based
     * @param int $perpage
     * @param string $order ORDER BY
     * @return array{total:int, reviews:array}
     */
    private static function list_reviews(string $where, array $params, int $page, int $perpage,
            string $order = 'r.timemodified DESC, r.id DESC'): array {
        global $DB;
        $from = "FROM {local_nit_reviews} r
                 JOIN {user} u ON u.id = r.userid AND u.deleted = 0
            LEFT JOIN {course} c ON c.id = r.courseid
                WHERE $where";
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);

        $ufields = \core_user\fields::for_userpic()->with_name()->get_sql('u', false, '', 'authorid', false)->selects;
        $rows = $DB->get_records_sql("SELECT r.id, r.courseid, r.teacherid, r.userid, r.rating, r.review, r.status,
                                             r.rejectreason, r.reviewedby, r.timereviewed, r.timecreated, r.timemodified,
                                             c.fullname AS coursename, $ufields
                                      $from
                                   ORDER BY $order",
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
                'courseid' => (int) $r->courseid,
                'coursename' => $r->coursename === null ? '' : format_string($r->coursename, true,
                    ['context' => \context_course::instance((int) $r->courseid, IGNORE_MISSING) ?: \context_system::instance()]),
                'teacherid' => (int) $r->teacherid,
                'userid' => (int) $r->userid,
                'fullname' => fullname($user),
                'pictureurl' => $picture,
                'rating' => (int) $r->rating,
                'review' => trim((string) $r->review),
                'status' => (int) $r->status,
                'rejectreason' => trim((string) $r->rejectreason),
                'reviewedby' => (int) $r->reviewedby,
                'timereviewed' => (int) $r->timereviewed,
                'timecreated' => (int) $r->timecreated,
                'timemodified' => (int) $r->timemodified,
            ];
        }
        return ['total' => $total, 'reviews' => $reviews];
    }

    // =========================================================================
    // Moderation.
    // =========================================================================

    /**
     * The context a review is moderated in: its course, or the site for
     * private-lesson reviews.
     *
     * @param \stdClass $review
     * @return \context
     */
    public static function review_context(\stdClass $review): \context {
        if ((int) $review->courseid > 1) {
            $context = \context_course::instance((int) $review->courseid, IGNORE_MISSING);
            if ($context) {
                return $context;
            }
        }
        return \context_system::instance();
    }

    /**
     * Whether the user may approve, reject or delete this review.
     *
     * @param \stdClass $review
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function can_moderate(\stdClass $review, int $userid = 0): bool {
        global $USER;
        return has_capability('local/nit_reviews:moderate', self::review_context($review), $userid ?: (int) $USER->id);
    }

    /**
     * The courses whose reviews the user moderates: null = every review (the
     * capability on the whole site), else the course ids (maybe empty).
     *
     * @param int $userid 0 = current user
     * @return int[]|null
     */
    public static function moderated_course_ids(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        if (has_capability('local/nit_reviews:moderate', \context_system::instance(), $userid)) {
            return null;
        }
        $courses = get_user_capability_course('local/nit_reviews:moderate', $userid, true, '', 'id', 0) ?: [];
        return array_values(array_filter(array_map(fn($c) => (int) $c->id, $courses), fn($id) => $id > 1));
    }

    /**
     * Whether the user moderates any review at all (the moderation page link).
     *
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function can_moderate_any(int $userid = 0): bool {
        $ids = self::moderated_course_ids($userid);
        return $ids === null || !empty($ids);
    }

    /**
     * One page of reviews for the moderation page, limited to what the user
     * moderates. Pending ones come first (oldest first, so nothing waits forever),
     * then the rest newest first.
     *
     * @param array $filters status (int|null), type ('course'|'teacher'|''), courseid, teacherid,
     *     q (author name or comment text)
     * @param int $page 0-based
     * @param int $perpage
     * @param int $userid 0 = current user
     * @return array{total:int, reviews:array}
     */
    public static function moderation_list(array $filters, int $page = 0, int $perpage = 30, int $userid = 0): array {
        global $DB;
        $where = ['1 = 1'];
        $params = [];
        $scope = self::moderated_course_ids($userid);
        if ($scope !== null) {
            if (!$scope) {
                return ['total' => 0, 'reviews' => []];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($scope, SQL_PARAMS_NAMED, 'scope');
            $where[] = "r.courseid $insql";
            $params += $inparams;
        }
        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = (int) $filters['status'];
        }
        if (($filters['type'] ?? '') === 'course') {
            $where[] = 'r.teacherid = 0';
        } else if (($filters['type'] ?? '') === 'teacher') {
            $where[] = 'r.teacherid > 0';
        }
        if (!empty($filters['courseid'])) {
            // -1 = private-lesson reviews (stored with courseid 0).
            $where[] = 'r.courseid = :fcourseid';
            $params['fcourseid'] = max(0, (int) $filters['courseid']);
        }
        if (!empty($filters['teacherid'])) {
            $where[] = 'r.teacherid = :fteacherid';
            $params['fteacherid'] = (int) $filters['teacherid'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $DB->sql_like_escape($q) . '%';
            $where[] = '(' . $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':q1', false) . ' OR '
                . $DB->sql_like('r.review', ':q2', false) . ')';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        $pending = self::STATUS_PENDING;
        $order = "CASE WHEN r.status = $pending THEN 0 ELSE 1 END,
                  CASE WHEN r.status = $pending THEN r.timemodified ELSE -r.timemodified END, r.id";
        return self::list_reviews(implode(' AND ', $where), $params, $page, $perpage, $order);
    }

    /**
     * How many reviews wait for the user's approval.
     *
     * @param int $userid 0 = current user
     * @return int
     */
    public static function pending_count(int $userid = 0): int {
        return self::moderation_list(['status' => self::STATUS_PENDING], 0, 1, $userid)['total'];
    }

    /**
     * Approve a review: it becomes public and counts in the averages.
     *
     * @param int $reviewid
     * @return \stdClass the updated record
     * @throws \moodle_exception err_reviewnotfound
     * @throws \required_capability_exception
     */
    public static function approve(int $reviewid): \stdClass {
        return self::moderate($reviewid, self::STATUS_APPROVED, '');
    }

    /**
     * Reject a review: it is hidden and leaves the averages; the author sees the reason.
     *
     * @param int $reviewid
     * @param string $reason optional, at most MAX_REASON_LENGTH characters
     * @return \stdClass the updated record
     * @throws \moodle_exception err_reviewnotfound | err_reasontoolong
     * @throws \required_capability_exception
     */
    public static function reject(int $reviewid, string $reason = ''): \stdClass {
        $reason = trim($reason);
        if (\core_text::strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new \moodle_exception('err_reasontoolong', 'local_nit_reviews', '', self::MAX_REASON_LENGTH);
        }
        return self::moderate($reviewid, self::STATUS_REJECTED, $reason);
    }

    /**
     * Set a review's moderation status, log it and tell the author.
     *
     * @param int $reviewid
     * @param int $status STATUS_APPROVED | STATUS_REJECTED
     * @param string $reason rejection reason
     * @return \stdClass
     */
    private static function moderate(int $reviewid, int $status, string $reason): \stdClass {
        global $DB, $USER;
        $rec = $DB->get_record('local_nit_reviews', ['id' => $reviewid]);
        if (!$rec) {
            throw new \moodle_exception('err_reviewnotfound', 'local_nit_reviews');
        }
        $context = self::review_context($rec);
        require_capability('local/nit_reviews:moderate', $context);
        $rec->status = $status;
        $rec->rejectreason = $status === self::STATUS_REJECTED && $reason !== '' ? $reason : null;
        $rec->reviewedby = (int) $USER->id;
        $rec->timereviewed = time();
        $DB->update_record('local_nit_reviews', $rec);
        self::forget($rec);
        event\review_moderated::create_from_review($rec, $context,
            $status === self::STATUS_APPROVED ? 'approved' : 'rejected')->trigger();
        notifier::review_moderated($rec);
        return $rec;
    }

    /**
     * Moderation: delete any review by id. Needs local/nit_reviews:moderate in the
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
        $context = self::review_context($rec);
        require_capability('local/nit_reviews:moderate', $context);
        $DB->delete_records('local_nit_reviews', ['id' => $rec->id]);
        self::forget($rec);
        event\review_moderated::create_from_review($rec, $context, 'deleted')->trigger();
        return $rec;
    }

    /**
     * Drop the cached averages a changed review belongs to.
     *
     * @param \stdClass $review
     */
    private static function forget(\stdClass $review): void {
        unset(self::$aggcache[(int) $review->courseid], self::$teacheraggcache[(int) $review->teacherid]);
    }
}
