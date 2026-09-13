<?php
/**
 * REST endpoint — returns Jitsi JWT token + room info for Flutter / mobile clients.
 *
 * GET  /mod/jitsi/api_token.php?id={cm_id}&wstoken={moodle_token}
 *
 * Response (JSON):
 * {
 *   "server_url": "https://localhost:8443",
 *   "room":       "academy_jitsi_12_abc123",
 *   "jwt":        "eyJ...",
 *   "is_moderator": true,
 *   "subject":    "Session Name",
 *   "display_name": "Ziad Tawakol",
 *   "email":      "ziad@example.com"
 * }
 *
 * Error response:
 * { "error": "message" }
 */

define('NO_MOODLE_COOKIES', true);   // stateless — authenticate via token only

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

function api_error(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// ── Authenticate via Moodle web service token ────────────────────────────
$wstoken = required_param('wstoken', PARAM_ALPHANUM);
$token   = $DB->get_record('external_tokens', ['token' => $wstoken]);
if (!$token) {
    api_error('Invalid token', 401);
}
$user = $DB->get_record('user', ['id' => $token->userid, 'deleted' => 0]);
if (!$user) {
    api_error('User not found', 401);
}
\core\session\manager::set_user($user);

// ── Load activity ─────────────────────────────────────────────────────────
$id = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('jitsi', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jitsi  = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context    = context_module::instance($cm->id);
require_capability('mod/jitsi:view', $context);

$is_moderator = has_capability('mod/jitsi:moderate', $context);

// ── Access control (same rules as view.php) ──────────────────────────────
$session = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
if ($session && !$is_moderator) {
    $allowed = $DB->record_exists('academy_session_students', [
        'sessionid' => $session->id,
        'userid'    => $USER->id,
    ]);
    if (!$allowed) {
        api_error('You are not enrolled in this session', 403);
    }
    $now = time();
    if ($now < $session->start_time - 1800) {
        api_error('Session not open yet', 403);
    }
    if ($now > $session->start_time + ($session->duration * 60)) {
        api_error('Session has ended', 403);
    }
}

// ── Build Jitsi params ───────────────────────────────────────────────────
$jitsi_host  = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
$jitsi_scheme = 'https';
$jitsi_room  = jitsi_room_name($jitsi, $cm);
$display_name = fullname($USER);

$jwt = \local_academysessions\jitsi_jwt::generate(
    $jitsi_room, $display_name, $USER->email, $is_moderator
);

echo json_encode([
    'server_url'   => $jitsi_scheme . '://' . $jitsi_host,
    'room'         => $jitsi_room,
    'jwt'          => $jwt,
    'is_moderator' => $is_moderator,
    'subject'      => $jitsi->name,
    'display_name' => $display_name,
    'email'        => $USER->email,
]);
