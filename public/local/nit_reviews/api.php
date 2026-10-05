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
 * Course reviews — token-authenticated JSON API for the mobile app.
 *
 *   GET|POST /local/nit_reviews/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * Reads: get_course_ratings, get_course_reviews, get_my_review.
 * Writes (POST): save_review, delete_my_review, delete_review (moderators).
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
 * A review record (or a get_reviews() row) as the app sees it.
 *
 * @param array|\stdClass $r
 * @return array
 */
function local_nit_reviews_api_review($r): array {
    $r = (array) $r;
    return [
        'id' => (int) $r['id'],
        'courseid' => isset($r['courseid']) ? (int) $r['courseid'] : null,
        'userid' => (int) $r['userid'],
        'rating' => (int) $r['rating'],
        'review' => trim((string) ($r['review'] ?? '')),
        'timecreated' => (int) $r['timecreated'],
        'timemodified' => (int) $r['timemodified'],
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

api::run(function (string $function) use ($userid) {
    switch ($function) {
        // Average + count for many courses (catalogue cards).
        // courseids = JSON array "[4,22]" or CSV "4,22".
        case 'get_course_ratings':
            $raw = trim(required_param('courseids', PARAM_RAW));
            $ids = ($raw !== '' && $raw[0] === '[') ? api::json_param('courseids') : explode(',', $raw);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 1)));
            if (count($ids) > 200) {
                return api::fail('toomanycourses', get_string('err_toomanycourses', 'local_nit_reviews', 200));
            }
            $aggs = $ids ? reviews::get_aggregates($ids) : [];
            $out = [];
            foreach ($ids as $id) {
                $out[] = ['courseid' => $id, 'avg' => (float) $aggs[$id]->avg, 'count' => (int) $aggs[$id]->count];
            }
            return ['courses' => $out];

        // One page of a course's reviews, newest first, plus the summary.
        case 'get_course_reviews':
            $courseid = required_param('courseid', PARAM_INT);
            reviews::require_course($courseid);
            [$page, $perpage] = api::paging(20, 50);
            $list = reviews::get_reviews($courseid, $page, $perpage);
            $items = [];
            foreach ($list['reviews'] as $r) {
                $items[] = [
                    'id' => $r['id'],
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
            return [
                'courseid' => $courseid,
                'summary' => local_nit_reviews_api_summary($courseid) + ['stars' => reviews::get_distribution($courseid)],
                'canrate' => reviews::can_rate($courseid),
                'canmanage' => has_capability('local/nit_reviews:manage', context_course::instance($courseid)),
                'page' => $page,
                'perpage' => $perpage,
                'total' => $list['total'],
                'reviews' => $items,
            ];

        // The caller's own review of a course (null when none) + whether they may rate.
        case 'get_my_review':
            $courseid = required_param('courseid', PARAM_INT);
            reviews::require_course($courseid);
            $mine = reviews::get_user_review($courseid);
            return [
                'courseid' => $courseid,
                'canrate' => reviews::can_rate($courseid),
                'review' => $mine ? local_nit_reviews_api_review($mine) : null,
                'summary' => local_nit_reviews_api_summary($courseid),
            ];

        // Create or update the caller's review (one per user per course).
        case 'save_review':
            api::require_post();
            $courseid = required_param('courseid', PARAM_INT);
            $rating = required_param('rating', PARAM_INT);
            $text = optional_param('review', '', PARAM_TEXT);
            $saved = reviews::save_validated($courseid, $rating, $text);
            return [
                'review' => local_nit_reviews_api_review($saved),
                'summary' => local_nit_reviews_api_summary($courseid),
            ];

        // Delete the caller's own review of a course.
        case 'delete_my_review':
            api::require_post();
            $courseid = required_param('courseid', PARAM_INT);
            reviews::require_course($courseid);
            if (!reviews::delete_user_review($courseid)) {
                throw new moodle_exception('err_reviewnotfound', 'local_nit_reviews');
            }
            return ['deleted' => true, 'summary' => local_nit_reviews_api_summary($courseid)];

        // Moderation: delete anyone's review (local/nit_reviews:manage in the course).
        case 'delete_review':
            api::require_post();
            $deleted = reviews::delete_review(required_param('reviewid', PARAM_INT));
            return [
                'deleted' => true,
                'reviewid' => (int) $deleted->id,
                'courseid' => (int) $deleted->courseid,
                'summary' => local_nit_reviews_api_summary((int) $deleted->courseid),
            ];
    }
    return api::unknown();
});
