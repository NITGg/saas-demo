<?php
/**
 * AJAX handler for mod_jitsi — called from the browser during/after a session.
 * Uses Moodle session auth + sesskey (no external token needed).
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_login();

header('Content-Type: application/json');

require_sesskey();

$function = required_param('function', PARAM_ALPHANUMEXT);

// ── end_session ────────────────────────────────────────────────────────────
if ($function === 'end_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session   = $DB->get_record('academy_live_sessions', ['id' => $sessionid], '*', MUST_EXIST);

    // Only the room's moderator (the session's teacher, or a site admin) may end it, not
    // any other teacher of the course.
    $jitsi = $DB->get_record('jitsi', ['id' => $session->jitsiid]);
    if ($jitsi) {
        $cm      = get_coursemodule_from_instance('jitsi', $jitsi->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        if (!\mod_jitsi\local\presence::is_moderator($cm, (int) $USER->id)) {
            throw new required_capability_exception($context, 'mod/jitsi:moderate', 'nopermissions', '');
        }
    } else {
        // No room: the session's owner (the mobile API rule).
        \local_academysessions\mobile_service::require_session_owner($session);
    }

    \local_academysessions\session_manager::end_session($sessionid);

    echo json_encode(['status' => 'success']);
    exit;
}

// ── end_room ───────────────────────────────────────────────────────────────
// Locks a standalone Jitsi activity (no linked academy session) so no one
// can rejoin after the teacher ends it.
if ($function === 'end_room') {
    $cmid = required_param('cmid', PARAM_INT);
    $cm   = get_coursemodule_from_id('jitsi', $cmid, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    if (!\mod_jitsi\local\presence::is_moderator($cm, (int) $USER->id)) {
        throw new required_capability_exception($context, 'mod/jitsi:moderate', 'nopermissions', '');
    }

    \mod_jitsi\local\presence::end_room((int) $cm->id);

    echo json_encode(['status' => 'success']);
    exit;
}

// ── Recording endpoints (Bunny/MinIO) removed for the SaaS ──────────────────
// Session recording moves to VdoCipher (local_vdocipher) in a later phase. Until
// then these return an empty result so any lingering client poll is harmless.
if ($function === 'get_session_recordings') {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}
if ($function === 'check_recording_status') {
    echo json_encode(['status' => 'unavailable']);
    exit;
}

echo json_encode(['status' => 'fail', 'error' => 'Unknown function']);
