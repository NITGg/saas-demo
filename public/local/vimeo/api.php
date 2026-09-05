<?php
/**
 * Vimeo token-authenticated JSON API (teacher CRUD + playback).
 *
 * Protocol mirrors local_academy / local_vdocipher:
 *   GET|POST /local/vimeo/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<translated>","errorcode":"<stable code>"}
 *
 * The token is validated by \local_academy\token_auth (expiry, IP, service
 * enabled, account state) and $USER is set to its owner. All CRUD functions
 * additionally require local/vimeo:manage in the relevant course context.
 *
 * A dead token is answered with HTTP 401 — see vimeo_fail() below.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
ob_start();

/**
 * Emit a JSON envelope and stop, dropping any stray output first.
 *
 * @param mixed $payload the envelope to encode
 * @param int $http the HTTP status to send with it
 */
function vimeo_respond($payload, int $http = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($http);
    echo json_encode($payload);
    exit;
}

/**
 * Emit a failure and stop.
 *
 * The 401 matters: a password change or a block deletes every one of the user's
 * web-service tokens (\local_academy\session_terminator), and a device with a
 * dead token will next call here. Answering 401 lets the app's generic handler
 * end the session and return to login — exactly as for Moodle's own WS endpoint.
 * Anything that is not a credentials problem stays 200.
 *
 * The `errorcode` matches the vocabulary local_academy's api.php already uses,
 * so one table of codes covers both endpoints; `error` stays the human sentence.
 *
 * @param string $errorcode stable machine-readable code
 * @param string $message translated, ready to show
 * @param int $http HTTP status; 401 only when the caller's credentials are dead
 */
function vimeo_fail(string $errorcode, string $message, int $http = 200) {
    vimeo_respond(['status' => 'fail', 'error' => $message, 'errorcode' => $errorcode], $http);
}

/**
 * Reject non-POST requests for state-changing calls.
 */
function vimeo_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        vimeo_fail('postrequired', get_string('err_postrequired', 'local_academy'));
    }
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_ALPHANUM);

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    vimeo_fail('authrequired', get_string('err_authrequired', 'local_academy'), 401);
}
$USER = \local_academy\token_auth::validate($token);
if (!$USER) {
    vimeo_fail('invalidtoken', get_string('err_invalidtoken', 'local_academy'), 401);
}
\core\session\manager::set_user($USER);

try {
    switch ($function) {

        // ── Playback ──────────────────────────────────────────────────────────
        // Return the Vimeo embed URL for the video on this activity. No OTP: the
        // video is embed-whitelisted to the academy domain and access is checked
        // here (enrolment + capability). Call right before playback.
        case 'get_playback':
            $cmid = required_param('cmid', PARAM_INT);
            vimeo_respond(['status' => 'success',
                'data' => \local_vimeo\playback_service::get_playback($cmid, $USER)]);
            break;

        // ── Teacher CRUD ──────────────────────────────────────────────────────

        // Create a Vimeo video and get a resumable (tus) upload link + video id.
        // The client then PATCHes the file bytes straight to the upload link.
        case 'create_upload':
            vimeo_require_post();
            $title    = required_param('title', PARAM_TEXT);
            $size     = required_param('size', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $cmid     = optional_param('cmid', 0, PARAM_INT);
            vimeo_respond(['status' => 'success',
                'data' => \local_vimeo\video_service::create_upload($title, $size, $courseid, $cmid)]);
            break;

        // Refresh + return a video's processing status (PRE-Upload…complete).
        case 'video_status':
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            vimeo_respond(['status' => 'success',
                'data' => \local_vimeo\video_service::refresh_status($videoid)]);
            break;

        // List videos, optionally scoped to a course.
        case 'list_videos':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            vimeo_respond(['status' => 'success',
                'data' => \local_vimeo\video_service::list_videos($courseid)]);
            break;

        // Attach an existing video to a course module (resource2).
        case 'attach_video':
            vimeo_require_post();
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            $cmid    = required_param('cmid', PARAM_INT);
            vimeo_respond(['status' => 'success',
                'data' => \local_vimeo\video_service::attach($videoid, $cmid)]);
            break;

        // Delete a video from Vimeo and remove our mapping row.
        case 'delete_video':
            vimeo_require_post();
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            vimeo_respond(['status' => 'success',
                'data' => ['deleted' => \local_vimeo\video_service::delete_video($videoid)]]);
            break;

        default:
            vimeo_fail('unknownfunction', get_string('err_unknownfunction', 'local_academy'));
    }
} catch (\required_capability_exception $e) {
    // Not 401: the token is good, the permission is missing. Only a dead token
    // may tell the app to sign out.
    vimeo_fail('nopermissions', $e->getMessage());
} catch (\local_vimeo\api_exception $e) {
    // Vimeo-side or mapping errors are safe and useful to surface to teachers.
    vimeo_fail('vimeoapi', $e->getMessage());
} catch (\Throwable $e) {
    debugging('local_vimeo api error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    vimeo_fail('internalerror', get_string('err_internal', 'local_academy'));
}
