<?php
/**
 * REST endpoint — returns Jitsi JWT token + room info for Flutter / mobile clients.
 *
 * GET  /mod/jitsi/api_token.php?id={cm_id}&wstoken={moodle_token}   (or &token=)
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
 * { "error": "message", "errorcode": "<code>" }   (HTTP 401 / 403 / 404)
 *
 * The token is validated by \local_academy\token_auth (expiry, IP restriction,
 * service enabled, account state) like every other token API.
 */

define('NO_MOODLE_COOKIES', true);   // stateless — authenticate via token only

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

function api_error(string $msg, int $code = 400, string $errorcode = ''): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    $out = ['error' => $msg];
    if ($errorcode !== '') {
        $out['errorcode'] = $errorcode;
    }
    echo json_encode($out);
    exit;
}

ob_start();

// ── Authenticate via Moodle web service token ────────────────────────────
// Same validation as every token API (expiry, IP restriction, service enabled,
// suspended / unconfirmed / deleted account). `wstoken` (original) or `token`.
$wstoken = optional_param('wstoken', '', PARAM_ALPHANUM);
if ($wstoken === '') {
    $wstoken = optional_param('token', '', PARAM_ALPHANUM);
}
if ($wstoken === '') {
    api_error('Authentication required', 401, 'authrequired');
}
$user = \local_academy\token_auth::validate($wstoken);
if (!$user) {
    api_error('Invalid token', 401, 'invalidtoken');
}
\core\session\manager::set_user($user);
if (class_exists('\local_academy\api\endpoint') && \local_academy\api\endpoint::site_locked((int) $user->id)) {
    api_error(get_string('err_siteunavailable', 'local_academy'), 403, 'siteunavailable');
}

// ── Load activity ─────────────────────────────────────────────────────────
$id = optional_param('id', 0, PARAM_INT);
$cm = $id ? get_coursemodule_from_id('jitsi', $id, 0, false, IGNORE_MISSING) : false;
if (!$cm) {
    api_error('Jitsi activity not found', 404, 'invalidcoursemodule');
}
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jitsi  = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

try {
    require_login($course, false, $cm, false, true);
} catch (\Throwable $e) {
    api_error('You cannot access this activity', 403, 'nopermissions');
}
$context    = context_module::instance($cm->id);
if (!has_capability('mod/jitsi:view', $context)) {
    api_error('You cannot access this activity', 403, 'nopermissions');
}

$is_moderator = has_capability('mod/jitsi:moderate', $context);

// ── Access control (same rules as view.php) ──────────────────────────────
$session = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
if ($session && !$is_moderator) {
    $allowed = $DB->record_exists('academy_session_students', [
        'sessionid' => $session->id,
        'userid'    => $USER->id,
    ]);
    if (!$allowed) {
        api_error('You are not enrolled in this session', 403, 'notallowed');
    }
    $now = time();
    if ($now < $session->start_time - 1800) {
        api_error('Session not open yet', 403, 'sessionnotavailable');
    }
    if ($now > $session->start_time + ($session->duration * 60)) {
        api_error('Session has ended', 403, 'sessionended');
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
