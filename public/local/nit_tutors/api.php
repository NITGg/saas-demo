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
 * Teachers token-authenticated JSON API for the mobile app (docs/apis/teachers-mobile-api.md).
 *
 *   GET|POST /local/nit_tutors/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"[,"errors":{field: message}]}
 *
 * HTTP 401 = missing/dead token, 403 = academy suspended or expired. State-changing calls
 * require POST (multipart/form-data when they carry files). Optional `lang=ar|en`. Times are
 * unix seconds. The logic lives in \local_nit_tutors\local\mobile_api.
 *
 *   pre-login (the shared registration token): get_teacher_signup_form, register_teacher
 *   anyone (a visitor with the shared token, or a member): get_teacher_filters, search_teachers, get_tutor
 *   the signed-in teacher / applicant: get_application, save_application, submit_application
 *   reviewer (local/nit_tutors:review): admin_list_applications, admin_get_application, admin_decide
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_tutors\local\invalid_signup;
use local_nit_tutors\local\mobile_api as tutorsapi;

api::boot();
$USER = api::authenticate();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}
tutorsapi::set_file_url(fn(string $url) => api::file_url($url));

api::run(function (string $function) use ($DB) {
    global $USER;
    $json = fn(string $name) => api::json_param($name, false);
    // The teacher's own calls need the teacher's token, not the shared pre-login one.
    $own = function () {
        global $USER;
        if (api::is_shared_token() || isguestuser()) {
            api::fail('authrequired', get_string('err_authrequired', 'local_academy'), 401);
        }
        return (int) $USER->id;
    };
    $fielderrors = function (invalid_signup $e, string $code): void {
        api::emit(['status' => 'fail', 'error' => get_string('err_form', 'local_nit_tutors'), 'errorcode' => $code,
            // Plain text: Moodle's password-policy message comes as <div> lines.
            'errors' => (object) array_map(fn($m) => trim(preg_replace('/\s+/u', ' ', strip_tags(
                str_replace('</div>', ' ', (string) $m)))), $e->errors)]);
    };
    if (strpos($function, 'admin_') === 0) {
        api::require_capability('local/nit_tutors:review');
    }

    switch ($function) {
        // ── Joining as a teacher (pre-login: shared registration token) ─────────

        case 'get_teacher_signup_form':
            return tutorsapi::signup_form();

        // The account and the teacher's data in one go (multipart). The application is sent for
        // review at once; the account is a student account until a reviewer approves it.
        case 'register_teacher':
            api::require_post();
            $raw = [];
            foreach (\local_nit_tutors\local\signup::FIELDS as $name) {
                $raw[$name] = optional_param($name, '', PARAM_RAW);
            }
            [$in, $files] = tutorsapi::read_request($json);
            try {
                $newuser = \local_nit_tutors\local\signup::register_teacher($raw, optional_param('password2', '', PARAM_RAW),
                    (bool) optional_param('agree', 0, PARAM_BOOL), $in, $files);
            } catch (invalid_signup $e) {
                $fielderrors($e, 'invalidregistration');
            }
            if (empty($newuser->confirmed)) {
                return [
                    'userid' => (int) $newuser->id,
                    'confirmationrequired' => true,
                    'email' => $newuser->email,
                    'message' => strip_tags(get_string('emailconfirmsent', '', $newuser->email)),
                ];
            }
            // Become the new teacher and mint their mobile token (as register_student does).
            \core\session\manager::set_user($newuser);
            $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE, 'enabled' => 1]);
            if (!$service) {
                api::fail('servicenotavailable', get_string('servicenotavailable', 'webservice'));
            }
            $deviceid = class_exists('\local_nit_devices\hook_callbacks')
                ? \local_nit_devices\hook_callbacks::request_device_id() : '';
            $tokenrec = $deviceid !== ''
                ? \local_nit_devices\manager::issue_token($newuser, $service, $deviceid,
                    optional_param('devicename', '', PARAM_TEXT), optional_param('platform', '', PARAM_ALPHANUMEXT))
                : \core_external\util::generate_token_for_current_user($service);
            \core_external\util::log_token_request($tokenrec);
            return [
                'userid' => (int) $newuser->id,
                'token' => $tokenrec->token,
                'privatetoken' => is_https() ? ($tokenrec->privatetoken ?? null) : null,
                'confirmationrequired' => false,
                'application' => tutorsapi::application((int) $newuser->id),
            ];

        // ── The teacher profile (the teacher's own token) ──────────────────────

        case 'get_application':
            return tutorsapi::application($own());

        // Save everything the form shows (send the whole form each time). send=1 also sends a
        // draft / sent-back / rejected application for review.
        case 'save_application':
            api::require_post();
            $userid = $own();
            [$in, $files, $card] = tutorsapi::read_request($json);
            try {
                return tutorsapi::save($userid, $in, $files, $card, (bool) optional_param('send', 0, PARAM_BOOL));
            } catch (invalid_signup $e) {
                $fielderrors($e, 'invalidteacherdata');
            }
            return null;

        case 'submit_application':
            api::require_post();
            return tutorsapi::submit($own());

        // ── Finding a teacher (visitor or member) ──────────────────────────────

        case 'get_teacher_filters':
            api::public_viewer();
            return tutorsapi::filters();

        case 'search_teachers':
            $viewer = api::public_viewer();
            [$page, $perpage] = api::paging(\local_nit_tutors\local\search_view::PERPAGE, 100);
            return tutorsapi::search([
                'q' => optional_param('q', '', PARAM_TEXT),
                'subject' => optional_param('subject', '', PARAM_TEXT),
                'pricemin' => optional_param('pricemin', '', PARAM_RAW_TRIMMED),
                'pricemax' => optional_param('pricemax', '', PARAM_RAW_TRIMMED),
                'day' => optional_param('day', '', PARAM_RAW_TRIMMED),
                'time' => optional_param('time', '', PARAM_ALPHA),
                'rating' => optional_param('rating', '', PARAM_RAW_TRIMMED),
                'badge' => optional_param('badge', '', PARAM_RAW_TRIMMED),
                'sort' => optional_param('sort', 'recommended', PARAM_ALPHAEXT),
                'f' => array_map('strval', $json('fields')),
            ], $viewer, $page, $perpage);

        // The public teacher page (the same as local/academy/api.php → get_teacher_page).
        case 'get_tutor':
            return \local_academy\api\pages::teacher(required_param('teacherid', PARAM_INT), api::public_viewer());

        // ── Reviewer (local/nit_tutors:review) ─────────────────────────────────

        case 'admin_list_applications':
            [$page, $perpage] = api::paging(30, 100);
            return tutorsapi::admin_list(optional_param('status', 'pending', PARAM_ALPHA),
                optional_param('q', '', PARAM_TEXT), $page, $perpage);

        case 'admin_get_application':
            $userid = required_param('userid', PARAM_INT);
            if (!\core_user::get_user($userid) || \core_user::get_user($userid)->deleted) {
                api::fail('usernotfound', get_string('invaliduser', 'error'));
            }
            return tutorsapi::admin_get($userid);

        case 'admin_decide':
            api::require_post();
            return tutorsapi::admin_decide(required_param('userid', PARAM_INT), required_param('action', PARAM_ALPHA),
                optional_param('note', '', PARAM_TEXT), (int) $USER->id);
    }
    return api::unknown();
});
