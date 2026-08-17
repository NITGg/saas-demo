<?php
/**
 * Session-authed AJAX endpoint: return VdoCipher S3 upload credentials so the
 * browser can upload a video directly (no bytes through PHP).
 *
 *   POST /local/vdocipher/upload_credentials.php
 *        sesskey, courseid, title
 *   → {"videoId":"…","clientPayload":{…,"uploadLink":"…"}} | {"error":"…"}
 *
 * Guarded by sesskey + local/vdocipher:manage in the course context. The secret
 * never leaves the server; the browser only gets short-lived S3 form fields.
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

$courseid = required_param('courseid', PARAM_INT);
$title    = optional_param('title', 'Video', PARAM_TEXT);

$context = ($courseid && $courseid != SITEID)
    ? context_course::instance($courseid)
    : context_system::instance();

try {
    require_capability('local/vdocipher:manage', $context);

    $creds = (new \local_vdocipher\api_client())->get_upload_credentials($title);
    if (empty($creds['videoId']) || empty($creds['clientPayload']['uploadLink'])) {
        throw new \moodle_exception('err_apifailed', 'local_vdocipher', '', 'no upload credentials');
    }

    echo json_encode([
        'videoId'       => $creds['videoId'],
        'clientPayload' => $creds['clientPayload'],
    ]);
} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
