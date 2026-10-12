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

    $byteacher = $jitsi ? \mod_jitsi\local\access::is_session_teacher($session, (int) $USER->id, $cm)
        : (int) $session->teacherid === (int) $USER->id;
    \local_academysessions\session_manager::end_session($sessionid, $byteacher ? 'teacher' : 'admin', (int) $USER->id);

    echo json_encode(['status' => 'success']);
    exit;
}

// ── leave_session ──────────────────────────────────────────────────────────
// A participant left the room (view.php, on hang-up or leaving the page): closes
// their own attendance row. Only ever their own; nothing happens if they never joined.
if ($function === 'leave_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    \local_academysessions\session_manager::record_leave($sessionid, (int) $USER->id);
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

// ── room_presence ──────────────────────────────────────────────────────────
// The room's attendance log: the caller came into the call (present=1) or left it
// (present=0). Joining is only logged for someone the room lets in right now.
if ($function === 'room_presence') {
    $cmid = required_param('cmid', PARAM_INT);
    $present = required_param('present', PARAM_BOOL);
    [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jitsi');
    if ($present) {
        $cminfo = get_fast_modinfo($course, (int) $USER->id)->get_cm($cm->id);
        $decision = \mod_jitsi\local\access::check($cminfo, (int) $USER->id);
        if ($decision->allowed) {
            \mod_jitsi\local\roomlog::joined((int) $cm->instance, (int) $USER->id, $decision->moderator);
        }
    } else {
        \mod_jitsi\local\roomlog::left((int) $cm->instance, (int) $USER->id);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// ── get_session_recordings ─────────────────────────────────────────────────
// The room's recordings for the panel shown after leaving the call (view.php), with the
// "Session recordings" tab's rules (jitsi_recordings_for_user()).
if ($function === 'get_session_recordings') {
    $cmid = required_param('cmid', PARAM_INT);
    [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jitsi');
    require_login($course, false, $cm);
    require_once(__DIR__ . '/lib.php');
    $cminfo = get_fast_modinfo($course, (int) $USER->id)->get_cm($cm->id);
    echo json_encode(['status' => 'success', 'data' => jitsi_recordings_for_user($cminfo, (int) $USER->id)]);
    exit;
}
// Bunny/MinIO recording status (removed for the SaaS): a lingering client poll is harmless.
if ($function === 'check_recording_status') {
    echo json_encode(['status' => 'unavailable']);
    exit;
}

echo json_encode(['status' => 'fail', 'error' => 'Unknown function']);
