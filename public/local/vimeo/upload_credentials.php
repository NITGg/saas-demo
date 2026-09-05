<?php
/**
 * Session-authed AJAX endpoint: create a Vimeo video and return a resumable
 * (tus) upload link so the browser can upload the file directly (no bytes
 * through PHP).
 *
 *   POST /local/vimeo/upload_credentials.php
 *        sesskey, courseid, title, size (bytes)
 *   → {"videoId":"…","uploadLink":"…"} | {"error":"…"}
 *
 * Guarded by sesskey + local/vimeo:manage in the course context. The access
 * token never leaves the server; the browser only gets the pre-signed tus link.
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

$courseid = required_param('courseid', PARAM_INT);
$title    = optional_param('title', 'Video', PARAM_TEXT);
$size     = required_param('size', PARAM_INT);

try {
    $result = \local_vimeo\video_service::create_upload($title, $size, $courseid, 0);

    echo json_encode([
        'videoId'    => $result['videoid'],
        'uploadLink' => $result['upload_link'],
        'rowid'      => $result['rowid'],
    ]);
} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
