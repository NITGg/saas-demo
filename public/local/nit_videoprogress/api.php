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
 * Video progress — token-authenticated JSON API for the mobile player.
 *
 *   GET|POST /local/nit_videoprogress/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * save_progress (POST) is the app twin of the local_nit_videoprogress_save web
 * service used by js/tracker.js: same params, same rules, same access checks.
 * Everything here is about the token's own user.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_videoprogress\progress;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

/**
 * Add the resume point to overview() lessons.
 *
 * @param array $course one overview() entry
 * @return array
 */
function local_nit_videoprogress_api_course(array $course): array {
    foreach ($course['lessons'] as &$lesson) {
        $lesson['resume_position'] = progress::resume_position((object) [
            'position' => $lesson['position'], 'duration' => $lesson['duration']]);
    }
    return $course;
}

api::run(function (string $function) use ($userid, $DB) {
    switch ($function) {
        // The player's periodic report: position + slices played since the last report.
        case 'save_progress':
            api::require_post();
            $cmid = required_param('cmid', PARAM_INT);
            $position = required_param('position', PARAM_INT);
            $duration = required_param('duration', PARAM_INT);
            $slices = progress::parse_slices(optional_param('slices', '', PARAM_RAW_TRIMMED));
            [, $cm] = progress::require_lesson($cmid);
            $row = progress::record($userid, $cm, $position, $duration, $slices);
            return [
                'percent' => (int) $row->percent,
                'position' => (int) $row->position,
                'duration' => (int) $row->duration,
                'resume_position' => progress::resume_position($row),
            ];

        // One lesson: where to resume and what was watched.
        case 'get_progress':
            [, $cm] = progress::require_lesson(required_param('cmid', PARAM_INT));
            return progress::export($cm, progress::get($userid, (int) $cm->id));

        // Every video lesson of one course + the course average. Must be enrolled.
        case 'get_course_progress':
            $courseid = required_param('courseid', PARAM_INT);
            $course = $courseid > 1 ? $DB->get_record('course', ['id' => $courseid]) : null;
            if (!$course || !is_enrolled(context_course::instance($courseid), $userid, '', true)) {
                throw new moodle_exception('err_notenrolled', 'local_nit_videoprogress');
            }
            $overview = progress::overview($userid, [$courseid]);
            if (!$overview) {
                return [
                    'courseid' => $courseid,
                    'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($courseid)]),
                    'percent' => 0,
                    'lessons' => [],
                ];
            }
            return local_nit_videoprogress_api_course($overview[0]);

        // All my courses (or the given ones I am enrolled in) with their video lessons.
        case 'get_overview':
            $enrolled = array_map('intval', array_keys(enrol_get_all_users_courses($userid, true, 'id')));
            $raw = trim(optional_param('courseids', '', PARAM_RAW));
            if ($raw !== '') {
                $asked = $raw[0] === '[' ? api::json_param('courseids') : explode(',', $raw);
                $asked = array_map('intval', $asked);
                $enrolled = array_values(array_filter($enrolled, fn($id) => in_array($id, $asked, true)));
            }
            $courses = array_map('local_nit_videoprogress_api_course', progress::overview($userid, $enrolled));
            $lessons = array_merge([], ...array_column($courses, 'lessons'));
            return [
                'percent' => $lessons ? (int) round(array_sum(array_column($lessons, 'percent')) / count($lessons)) : 0,
                'courses' => $courses,
            ];
    }
    return api::unknown();
});
