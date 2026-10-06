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

/**
 * Course and teacher reviews — token-authenticated JSON API for the mobile app.
 *
 *   GET|POST /local/nit_reviews/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * Reads: get_course_ratings, get_course_reviews, get_teacher_ratings, get_teacher_reviews,
 *        get_my_review, get_my_reviews, get_rate_targets.
 * Writes (POST): save_review, delete_my_review.
 * Moderation: get_moderation_reviews; (POST) approve_review, reject_review, delete_review.
 *
 * Only approved reviews are listed or averaged. A review with a comment is
 * "pending" until a moderator approves it; stars alone are approved at once.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_reviews\api as reviews;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

/**
 * The status word of a review for the app.
 *
 * @param int $status
 * @return string pending | approved | rejected
 */
function local_nit_reviews_api_status(int $status): string {
    return [reviews::STATUS_PENDING => 'pending', reviews::STATUS_APPROVED => 'approved',
        reviews::STATUS_REJECTED => 'rejected'][$status] ?? 'pending';
}

/**
 * A review record (or a list row) as the app sees it — the author's own view,
 * with its moderation status.
 *
 * @param array|\stdClass $r
 * @return array
 */
function local_nit_reviews_api_review($r): array {
    $r = (array) $r;
    return [
        'id' => (int) $r['id'],
        'courseid' => isset($r['courseid']) ? (int) $r['courseid'] : null,
        'teacherid' => (int) ($r['teacherid'] ?? 0),
        'userid' => (int) $r['userid'],
        'rating' => (int) $r['rating'],
        'review' => trim((string) ($r['review'] ?? '')),
        'status' => local_nit_reviews_api_status((int) ($r['status'] ?? reviews::STATUS_APPROVED)),
        'rejectreason' => trim((string) ($r['rejectreason'] ?? '')),
        'timecreated' => (int) $r['timecreated'],
        'timemodified' => (int) $r['timemodified'],
    ];
}

/**
 * A public list row (approved review) for the app.
 *
 * @param array $r a list_reviews() row
 * @param int $userid the caller
 * @return array
 */
function local_nit_reviews_api_public(array $r, int $userid): array {
    return [
        'id' => $r['id'],
        'courseid' => $r['courseid'],
        'coursename' => $r['coursename'],
        'teacherid' => $r['teacherid'],
        'userid' => $r['userid'],
        'fullname' => $r['fullname'],
        'pictureurl' => api::file_url($r['pictureurl']),
        'rating' => $r['rating'],
        'review' => $r['review'],
        'timecreated' => $r['timecreated'],
        'timemodified' => $r['timemodified'],
        'ismine' => $r['userid'] === $userid,
    ];
}

/**
 * {avg, count} of a course, fresh from the database.
 *
 * @param int $courseid
 * @return array
 */
function local_nit_reviews_api_summary(int $courseid): array {
    $agg = reviews::get_aggregate($courseid);
    return ['avg' => (float) $agg->avg, 'count' => (int) $agg->count];
}

/**
 * {avg, count} of a teacher over every course.
 *
 * @param int $teacherid
 * @return array
 */
function local_nit_reviews_api_teacher_summary(int $teacherid): array {
    $agg = reviews::get_teacher_aggregate($teacherid);
    return ['avg' => (float) $agg->avg, 'count' => (int) $agg->count];
}

/**
 * Ids from a JSON array "[4,22]" or CSV "4,22" parameter.
 *
 * @param string $name
 * @return int[]
 */
function local_nit_reviews_api_ids(string $name): array {
    $raw = trim(required_param($name, PARAM_RAW));
    $ids = ($raw !== '' && $raw[0] === '[') ? api::json_param($name) : explode(',', $raw);
    return array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
}

/**
 * The teacher behind an id, or a business error.
 *
 * @param int $teacherid
 * @return \stdClass
 */
function local_nit_reviews_api_teacher(int $teacherid): \stdClass {
    $user = $teacherid > 0 ? core_user::get_user($teacherid) : false;
    if (!$user || $user->deleted) {
        throw new moodle_exception('err_teachernotfound', 'local_nit_reviews');
    }
    return $user;
}

/**
 * Writes and moderation need a signed-in user, not the shared visitor token.
 */
function local_nit_reviews_api_require_user(): void {
    if (api::is_shared_token()) {
        api::fail('nopermissions', get_string('err_nopermission', 'local_academy'));
    }
}

api::run(function (string $function) use ($userid) {
    switch ($function) {
        // Average + count for many courses (catalogue cards).
        // courseids = JSON array "[4,22]" or CSV "4,22".
        case 'get_course_ratings':
            $ids = array_values(array_filter(local_nit_reviews_api_ids('courseids'), fn($id) => $id > 1));
            if (count($ids) > 200) {
                return api::fail('toomanycourses', get_string('err_toomanycourses', 'local_nit_reviews', 200));
            }
            $aggs = $ids ? reviews::get_aggregates($ids) : [];
            $out = [];
            foreach ($ids as $id) {
                $out[] = ['courseid' => $id, 'avg' => (float) $aggs[$id]->avg, 'count' => (int) $aggs[$id]->count];
            }
            return ['courses' => $out];

        // Average + count for many teachers (teacher cards). teacherids like courseids.
        case 'get_teacher_ratings':
            $ids = local_nit_reviews_api_ids('teacherids');
            if (count($ids) > 200) {
                return api::fail('toomanycourses', get_string('err_toomanycourses', 'local_nit_reviews', 200));
            }
            $aggs = $ids ? reviews::get_teacher_aggregates($ids) : [];
            $out = [];
            foreach ($ids as $id) {
                $out[] = ['teacherid' => $id, 'avg' => (float) $aggs[$id]->avg, 'count' => (int) $aggs[$id]->count];
            }
            return ['teachers' => $out];

        // One page of a course's approved reviews, newest first, plus the summary.
        case 'get_course_reviews':
            $courseid = required_param('courseid', PARAM_INT);
            reviews::require_course($courseid);
            [$page, $perpage] = api::paging(20, 50);
            $list = reviews::get_reviews($courseid, $page, $perpage);
            $canmoderate = has_capability('local/nit_reviews:moderate', context_course::instance($courseid));
            return [
                'courseid' => $courseid,
                'summary' => local_nit_reviews_api_summary($courseid) + ['stars' => reviews::get_distribution($courseid)],
                'canrate' => reviews::can_rate($courseid),
                'canmoderate' => $canmoderate,
                'canmanage' => $canmoderate,
                'page' => $page,
                'perpage' => $perpage,
                'total' => $list['total'],
                'reviews' => array_map(fn($r) => local_nit_reviews_api_public($r, $userid), $list['reviews']),
            ];

        // One page of a teacher's approved reviews over every course, plus the summary
        // and where the caller may rate them.
        case 'get_teacher_reviews':
            $teacherid = required_param('teacherid', PARAM_INT);
            local_nit_reviews_api_teacher($teacherid);
            [$page, $perpage] = api::paging(20, 50);
            $list = reviews::get_teacher_reviews($teacherid, $page, $perpage);
            return [
                'teacherid' => $teacherid,
                'summary' => local_nit_reviews_api_teacher_summary($teacherid)
                    + ['stars' => reviews::get_distribution(0, $teacherid)],
                'ratecourseids' => api::is_shared_token() ? [] : reviews::rateable_courses_for_teacher($teacherid),
                'page' => $page,
                'perpage' => $perpage,
                'total' => $list['total'],
                'reviews' => array_map(fn($r) => local_nit_reviews_api_public($r, $userid), $list['reviews']),
            ];

        // The caller's own review of a course (teacherid 0) or of a teacher in it
        // (null when none), with its status, and whether they may rate.
        case 'get_my_review':
            $courseid = required_param('courseid', PARAM_INT);
            $teacherid = optional_param('teacherid', 0, PARAM_INT);
            if ($teacherid) {
                local_nit_reviews_api_teacher($teacherid);
            }
            if (!$teacherid || $courseid !== 0) {
                reviews::require_course($courseid);
            }
            $mine = reviews::get_user_review($courseid, 0, $teacherid);
            return [
                'courseid' => $courseid,
                'teacherid' => $teacherid,
                'canrate' => $teacherid ? reviews::can_rate_teacher($courseid, $teacherid) : reviews::can_rate($courseid),
                'review' => $mine ? local_nit_reviews_api_review($mine) : null,
                'summary' => $teacherid ? local_nit_reviews_api_teacher_summary($teacherid)
                    : local_nit_reviews_api_summary($courseid),
            ];

        // Everything the caller reviewed, any status, newest first.
        case 'get_my_reviews':
            local_nit_reviews_api_require_user();
            return ['reviews' => array_map('local_nit_reviews_api_review', reviews::get_user_reviews())];

        // What the caller may rate on one screen: courseid → the course and each of its
        // teachers; teacherid → that teacher in every course (0 = private lessons).
        // Each target comes with the caller's current review (or null).
        case 'get_rate_targets':
            local_nit_reviews_api_require_user();
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $teacherid = optional_param('teacherid', 0, PARAM_INT);
            $pairs = [];
            if ($teacherid) {
                local_nit_reviews_api_teacher($teacherid);
                foreach (reviews::rateable_courses_for_teacher($teacherid) as $cid) {
                    $pairs[] = [$cid, $teacherid];
                }
            } else {
                reviews::require_course($courseid);
                if (reviews::can_rate($courseid)) {
                    $pairs[] = [$courseid, 0];
                }
                foreach (reviews::get_course_teachers($courseid) as $tid => $unused) {
                    if (reviews::can_rate_teacher($courseid, $tid)) {
                        $pairs[] = [$courseid, $tid];
                    }
                }
            }
            $targets = [];
            foreach ($pairs as [$cid, $tid]) {
                $teacher = $tid ? core_user::get_user($tid) : null;
                $mine = reviews::get_user_review($cid, 0, $tid);
                $targets[] = [
                    'type' => $tid ? 'teacher' : 'course',
                    'courseid' => $cid,
                    'coursename' => $cid > 1 ? format_string(get_course($cid)->fullname, true,
                        ['context' => context_course::instance($cid)]) : '',
                    'teacherid' => $tid,
                    'teachername' => $teacher ? fullname($teacher) : '',
                    'review' => $mine ? local_nit_reviews_api_review($mine) : null,
                ];
            }
            return ['targets' => $targets];

        // Create or update the caller's review of a course (teacherid 0) or of a
        // teacher in it (courseid 0 = private lessons). One per user per target.
        case 'save_review':
            api::require_post();
            local_nit_reviews_api_require_user();
            $courseid = required_param('courseid', PARAM_INT);
            $teacherid = optional_param('teacherid', 0, PARAM_INT);
            $rating = required_param('rating', PARAM_INT);
            $text = optional_param('review', '', PARAM_TEXT);
            if ($teacherid) {
                local_nit_reviews_api_teacher($teacherid);
            }
            $saved = reviews::save_validated($courseid, $rating, $text, 0, $teacherid);
            return [
                'review' => local_nit_reviews_api_review($saved),
                'summary' => $teacherid ? local_nit_reviews_api_teacher_summary($teacherid)
                    : local_nit_reviews_api_summary($courseid),
            ];

        // Delete the caller's own review of a course or of a teacher in it.
        case 'delete_my_review':
            api::require_post();
            local_nit_reviews_api_require_user();
            $courseid = required_param('courseid', PARAM_INT);
            $teacherid = optional_param('teacherid', 0, PARAM_INT);
            if (!$teacherid || $courseid !== 0) {
                reviews::require_course($courseid);
            }
            if (!reviews::delete_user_review($courseid, 0, $teacherid)) {
                throw new moodle_exception('err_reviewnotfound', 'local_nit_reviews');
            }
            return [
                'deleted' => true,
                'summary' => $teacherid ? local_nit_reviews_api_teacher_summary($teacherid)
                    : local_nit_reviews_api_summary($courseid),
            ];

        // Moderation list: reviews of the courses the caller moderates, pending first.
        // status = pending|approved|rejected|all (default pending), type = course|teacher,
        // courseid (-1 = private lessons), q = author name or comment.
        case 'get_moderation_reviews':
            local_nit_reviews_api_require_user();
            if (!reviews::can_moderate_any()) {
                return api::fail('nopermissions', get_string('err_nopermission', 'local_academy'));
            }
            $statusword = optional_param('status', 'pending', PARAM_ALPHA);
            $statuses = ['pending' => reviews::STATUS_PENDING, 'approved' => reviews::STATUS_APPROVED,
                'rejected' => reviews::STATUS_REJECTED];
            [$page, $perpage] = api::paging(30, 100);
            $list = reviews::moderation_list([
                'status' => $statuses[$statusword] ?? null,
                'type' => optional_param('type', '', PARAM_ALPHA),
                'courseid' => optional_param('courseid', 0, PARAM_INT),
                'q' => optional_param('q', '', PARAM_TEXT),
            ], $page, $perpage);
            $items = [];
            foreach ($list['reviews'] as $r) {
                $teacher = $r['teacherid'] ? core_user::get_user($r['teacherid']) : null;
                $items[] = local_nit_reviews_api_public($r, $userid) + [
                    'teachername' => $teacher ? fullname($teacher) : '',
                    'status' => local_nit_reviews_api_status($r['status']),
                    'rejectreason' => $r['rejectreason'],
                ];
            }
            return [
                'pendingcount' => reviews::pending_count(),
                'page' => $page,
                'perpage' => $perpage,
                'total' => $list['total'],
                'reviews' => $items,
            ];

        // Moderation: approve / reject (optional reason) a review.
        case 'approve_review':
        case 'reject_review':
            api::require_post();
            local_nit_reviews_api_require_user();
            $reviewid = required_param('reviewid', PARAM_INT);
            $rec = $function === 'approve_review' ? reviews::approve($reviewid)
                : reviews::reject($reviewid, optional_param('reason', '', PARAM_TEXT));
            return ['review' => local_nit_reviews_api_review($rec)];

        // Moderation: delete anyone's review (local/nit_reviews:moderate in the course).
        case 'delete_review':
            api::require_post();
            local_nit_reviews_api_require_user();
            $deleted = reviews::delete_review(required_param('reviewid', PARAM_INT));
            return [
                'deleted' => true,
                'reviewid' => (int) $deleted->id,
                'courseid' => (int) $deleted->courseid,
                'teacherid' => (int) $deleted->teacherid,
                'summary' => (int) $deleted->teacherid ? local_nit_reviews_api_teacher_summary((int) $deleted->teacherid)
                    : local_nit_reviews_api_summary((int) $deleted->courseid),
            ];
    }
    return api::unknown();
});
