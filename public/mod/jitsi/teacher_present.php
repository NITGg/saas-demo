<?php
/**
 * Mark the teacher as present (or gone) in a lesson's Jitsi call.
 *
 * Called via fetch() from view.php on the teacher's videoConferenceJoined /
 * videoConferenceLeft events. Stamps academy_live_sessions.teacher_joined_at so the
 * student-entry gate in view.php (and the mobile session payload) only lets students
 * into the room while the teacher is actually in the call.
 *
 * Only moderators may call this; sesskey validates the request.
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

require_sesskey();

$cmid    = required_param('cmid', PARAM_INT);
$present = optional_param('present', 1, PARAM_BOOL);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'jitsi');
$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/jitsi:moderate', $context);

$session = $DB->get_record('academy_live_sessions', ['jitsiid' => $cm->instance]);

if ($session) {
    $DB->set_field('academy_live_sessions', 'teacher_joined_at',
        $present ? time() : null, ['id' => $session->id]);

    // Track first join time in attendance table for historical reports,
    // since teacher_joined_at is cleared when they leave the room.
    if ($present && !$DB->record_exists('academy_session_attendance', ['sessionid' => $session->id, 'userid' => $USER->id])) {
        $att = new \stdClass();
        $att->sessionid        = $session->id;
        $att->userid           = $USER->id;
        $att->joined_at        = time();
        $att->duration_seconds = 0;
        $DB->insert_record('academy_session_attendance', $att);
    }

    // Audit timeline: record when the teacher actually entered the meeting room — a distinct
    // step from clicking "Start" (which creates the room). record_once so leaving/rejoining
    // does not add duplicate rows. Keyed off the lesson that owns this session.
    if ($present && class_exists('\local_academy\audit_manager')
            && $DB->get_manager()->table_exists('academy_lessons')) {
        $lessonid = $DB->get_field('academy_lessons', 'id', ['sessionid' => $session->id]);
        if ($lessonid) {
            \local_academy\audit_manager::record_once($lessonid, 'teacher_joined', $USER->id, 'teacher');
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'present' => (bool)$present]);
