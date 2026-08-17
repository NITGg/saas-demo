<?php
/**
 * VdoCipher token-authenticated JSON API (teacher CRUD).
 *
 * Protocol mirrors local_academy:
 *   GET|POST /local/vdocipher/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...} | {"status":"fail","error":"..."}
 *
 * The token is validated by \local_academy\token_auth (expiry, IP, service
 * enabled, account state) and $USER is set to its owner. All CRUD functions
 * additionally require local/vdocipher:manage in the relevant course context.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
ob_start();

/**
 * Emit a JSON envelope and stop, dropping any stray output first.
 */
function vdocipher_respond($payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

/**
 * Reject non-POST requests for state-changing calls.
 */
function vdocipher_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        vdocipher_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
    }
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_ALPHANUM);

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    vdocipher_respond(['status' => 'fail', 'error' => get_string('err_authrequired', 'local_academy')]);
}
$USER = \local_academy\token_auth::validate($token);
if (!$USER) {
    vdocipher_respond(['status' => 'fail', 'error' => get_string('err_invalidtoken', 'local_academy')]);
}
\core\session\manager::set_user($USER);

try {
    switch ($function) {

        // ── Playback ────────────────────────────────────────────────────────────
        // Mint a short-lived OTP for the video on this activity, watermarked with
        // the requesting user's identity. Call this immediately before playback.
        case 'get_playback':
            $cmid = required_param('cmid', PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\playback_service::get_playback($cmid, $USER)]);
            break;

        // ── Teacher CRUD ────────────────────────────────────────────────────────

        // Get S3 upload credentials for a new video + record a pending row.
        // Teacher then uploads bytes straight to VdoCipher.
        case 'create_upload':
            vdocipher_require_post();
            $title    = required_param('title', PARAM_TEXT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $cmid     = optional_param('cmid', 0, PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::create_upload($title, $courseid, $cmid)]);
            break;

        // Refresh + return a video's processing status (PRE-Upload…ready).
        case 'video_status':
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::refresh_status($videoid)]);
            break;

        // List videos, optionally scoped to a course.
        case 'list_videos':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::list_videos($courseid)]);
            break;

        // Attach an existing video to a course module (resource2).
        case 'attach_video':
            vdocipher_require_post();
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            $cmid    = required_param('cmid', PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::attach($videoid, $cmid)]);
            break;

        // Delete a video from VdoCipher and remove our mapping row.
        case 'delete_video':
            vdocipher_require_post();
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            vdocipher_respond(['status' => 'success',
                'data' => ['deleted' => \local_vdocipher\video_service::delete_video($videoid)]]);
            break;

        default:
            vdocipher_respond(['status' => 'fail', 'error' => get_string('err_unknownfunction', 'local_academy')]);
    }
} catch (\required_capability_exception $e) {
    vdocipher_respond(['status' => 'fail', 'error' => $e->getMessage()]);
} catch (\local_vdocipher\api_exception $e) {
    // VdoCipher-side or mapping errors are safe and useful to surface to teachers.
    vdocipher_respond(['status' => 'fail', 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    debugging('local_vdocipher api error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    vdocipher_respond(['status' => 'fail', 'error' => get_string('err_internal', 'local_academy')]);
}
