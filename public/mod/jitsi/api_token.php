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
 * { "error": "message", "errorcode": "<code>" }   (HTTP 401 / 403 / 404 / 503)
 * 403 codes: nopermissions, notallowed, sessionnotavailable, sessionended,
 * waitingforteacher (poll again; the teacher is not in the call yet).
 * 503 jitsinotconfigured: the site has no Jitsi JWT secret.
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

// ── Access control (the same rule as view.php: \mod_jitsi\local\access) ──
// Only the session's teacher moderates a linked room; a student gets no JWT before
// the window opens, after it closes, or while the teacher is not in the call.
$cminfo   = get_fast_modinfo($course, (int) $USER->id)->get_cm($cm->id);
$decision = \mod_jitsi\local\access::check($cminfo, (int) $USER->id);
if (!$decision->allowed) {
    $code = $decision->code === \mod_jitsi\local\access::UNAVAILABLE ? 'nopermissions' : $decision->code;
    api_error($decision->message, 403, $code);
}
$is_moderator = $decision->moderator;
\mod_jitsi\local\access::record_entry($decision, (int) $USER->id);

// ── Build Jitsi params ───────────────────────────────────────────────────
$jitsi_host  = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
$jitsi_scheme = 'https';
$jitsi_room  = jitsi_room_name($jitsi, $cm);
$display_name = fullname($USER);

try {
    $jwt = \local_academysessions\jitsi_jwt::generate(
        $jitsi_room, $display_name, $USER->email, $is_moderator
    );
} catch (\moodle_exception $e) {
    api_error($e->getMessage(), 503, $e->errorcode);
}

echo json_encode([
    'server_url'   => $jitsi_scheme . '://' . $jitsi_host,
    'room'         => $jitsi_room,
    'jwt'          => $jwt,
    'is_moderator' => $is_moderator,
    'subject'      => $jitsi->name,
    'display_name' => $display_name,
    'email'        => $USER->email,
]);
