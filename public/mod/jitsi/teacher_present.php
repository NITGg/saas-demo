<?php
/**
 * Mark the teacher as present (or gone) in a lesson's Jitsi call.
 *
 * Called via fetch() from view.php on the teacher's videoConferenceJoined /
 * videoConferenceLeft events. Stamps academy_live_sessions.teacher_joined_at so the
 * student-entry gate in view.php (and the mobile session payload) only lets students
 * into the room while the teacher is actually in the call.
 *
 * Only moderators may call this; sesskey validates the request. The logic lives in
 * \mod_jitsi\local\presence::set() (shared with the mobile token API).
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

\mod_jitsi\local\presence::set($cm, (int) $USER->id, (bool) $present);

header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'present' => (bool)$present]);
