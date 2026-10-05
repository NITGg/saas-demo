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
 * Live 1:1 lessons token-authenticated JSON API for the mobile app.
 *
 *   GET|POST /local/nit_lessons/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * HTTP 401 = missing/dead token, 403 = academy suspended or expired. State-changing calls
 * require POST. Optional `lang=ar|en`. Times are unix seconds. The logic lives in
 * \local_nit_lessons\local\mobile_api.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_academy\api\endpoint as api;
use local_nit_lessons\local\mobile_api as lessonsapi;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

/** Lesson actions: each takes lessonid + optional value / time / reason (see mobile_api::ACTIONS). */
const NIT_LESSONS_ACTIONS = ['teacher_respond_lesson', 'student_respond_lesson', 'start_lesson', 'complete_lesson',
    'report_student_absent', 'report_teacher_absent', 'cancel_lesson_request', 'cancel_lesson_student',
    'cancel_lesson_teacher', 'request_time_update', 'respond_time_update'];

api::run(function (string $function) use ($userid) {
    if (!local_nit_flex_enabled()) {
        throw new \local_nit_flex\exception\flex_exception('err_featureunavailable');
    }
    if (strpos($function, 'admin_') === 0) {
        api::require_capability('local/nit_lessons:managesettings');
    }
    if (in_array($function, NIT_LESSONS_ACTIONS, true)) {
        api::require_post();
        return lessonsapi::act($userid, $function, required_param('lessonid', PARAM_INT), [
            'value' => optional_param('value', '', PARAM_ALPHA),
            'time' => optional_param('time', 0, PARAM_INT),
            'reason' => optional_param('reason', '', PARAM_TEXT),
        ]);
    }

    switch ($function) {
        // ── Booking (student) ───────────────────────────────────────────────

        case 'get_teachers':
            return lessonsapi::get_teachers($userid, optional_param('subject', '', PARAM_TEXT));

        case 'get_teacher_slots':
            return lessonsapi::get_teacher_slots(required_param('teacherid', PARAM_INT),
                optional_param('lessonid', 0, PARAM_INT));

        case 'request_lesson':
            api::require_post();
            api::require_capability('local/nit_lessons:request');
            return lessonsapi::request_lesson($userid, required_param('teacherid', PARAM_INT),
                required_param('subject', PARAM_TEXT), required_param('time', PARAM_INT),
                optional_param('note', '', PARAM_TEXT));

        // ── Lessons (student and teacher) ───────────────────────────────────

        case 'get_my_lessons':
            return lessonsapi::get_my_lessons($userid, optional_param('role', '', PARAM_ALPHA),
                optional_param('status', '', PARAM_ALPHAEXT));

        case 'get_lesson':
            return lessonsapi::get_lesson($userid, required_param('lessonid', PARAM_INT));

        case 'get_lesson_join':
            return lessonsapi::get_lesson_join($userid, required_param('lessonid', PARAM_INT));

        // ── Teacher profile ─────────────────────────────────────────────────

        case 'get_teacher_profile':
            return lessonsapi::get_teacher_profile($userid);

        case 'update_teacher_profile':
            api::require_post();
            return lessonsapi::update_teacher_profile($userid, (bool) optional_param('available', 0, PARAM_BOOL),
                optional_param('headline', '', PARAM_TEXT), api::json_param('subjects'),
                api::json_param('hours', false));

        // ── Admin (local/nit_lessons:managesettings) ───────────────────────

        case 'admin_list_lessons':
            [$page, $perpage] = api::paging(50, 200);
            return lessonsapi::admin_list_lessons(optional_param('status', '', PARAM_ALPHAEXT), $page, $perpage);

        case 'admin_reverse_flex':
            api::require_post();
            return lessonsapi::admin_reverse_flex($userid, required_param('lessonid', PARAM_INT),
                required_param('reason', PARAM_TEXT));

        case 'admin_get_settings':
            return lessonsapi::admin_get_settings();

        case 'admin_update_settings':
            api::require_post();
            return lessonsapi::admin_update_settings(api::json_param('settings'));
    }
    return api::unknown();
});
