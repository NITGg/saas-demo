<?php
/**
 * Live sessions token-authenticated JSON API for the mobile app.
 *
 *   GET|POST /local/academysessions/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * HTTP 401 = missing/dead token, 403 = academy suspended/expired. Optional
 * `lang=ar|en`. The original functions (create_session … update_session) keep
 * their names, params and fields and still accept GET (older app builds); the
 * functions added later (start_session, leave_session, set_teacher_present,
 * end_room) require POST. Business logic: \local_academysessions\mobile_service.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_academysessions\mobile_service as sessions;
use local_academysessions\session_manager;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

/**
 * Load a Jitsi course module for the presence / room functions, or fail.
 *
 * @param int $cmid
 * @return stdClass
 */
function academysessions_jitsi_cm(int $cmid): stdClass {
    if (!class_exists('\mod_jitsi\local\presence')) {
        throw new moodle_exception('err_notjitsiactivity', 'local_academysessions');
    }
    $cm = $cmid ? get_coursemodule_from_id('jitsi', $cmid, 0, false, IGNORE_MISSING) : false;
    if (!$cm) {
        throw new moodle_exception('err_notjitsiactivity', 'local_academysessions');
    }
    return $cm;
}

api::run(function (string $function) use ($userid) {
    switch ($function) {
        // ── Teacher ───────────────────────────────────────────────────────────────

        // Create a session (managesessions in the course). jitsiid = jitsi INSTANCE id.
        case 'create_session':
            return sessions::create($userid,
                required_param('courseid', PARAM_INT),
                required_param('title', PARAM_TEXT),
                required_param('starttime', PARAM_INT),
                required_param('studentids', PARAM_SEQUENCE),
                optional_param('meetinglink', '', PARAM_URL),
                optional_param('duration', 50, PARAM_INT),
                optional_param('googlemeetid', 0, PARAM_INT),
                optional_param('jitsiid', 0, PARAM_INT));

        // With courseid: that course's sessions (managesessions or viewattendance there).
        // Without: the caller's own sessions.
        case 'get_teacher_sessions':
            return sessions::teacher_sessions($userid, optional_param('courseid', 0, PARAM_INT));

        // Mark a session live (managesessions).
        case 'start_session':
            api::require_post();
            return sessions::start(required_param('sessionid', PARAM_INT));

        // End a session: status → ended, open attendance rows closed (managesessions).
        case 'end_session':
            $sessionid = required_param('sessionid', PARAM_INT);
            sessions::require_session_owner(sessions::require_session($sessionid));
            session_manager::end_session($sessionid);
            return ['sessionid' => $sessionid, 'status' => 'ended'];

        case 'get_attendance':
            $sessionid = required_param('sessionid', PARAM_INT);
            sessions::require_course_capability(sessions::require_session($sessionid), sessions::CAP_ATTENDANCE);
            return array_values(session_manager::get_attendance_report($sessionid));

        case 'delete_session':
            $sessionid = required_param('sessionid', PARAM_INT);
            sessions::require_session_owner(sessions::require_session($sessionid));
            session_manager::delete_session($sessionid);
            return ['sessionid' => $sessionid, 'deleted' => true];

        case 'update_session':
            $sessionid = required_param('sessionid', PARAM_INT);
            sessions::require_session_owner(sessions::require_session($sessionid));
            $data = [];
            $title = trim(optional_param('title', '', PARAM_TEXT));
            $starttime = optional_param('starttime', 0, PARAM_INT);
            $meetinglink = optional_param('meetinglink', '', PARAM_URL);
            $duration = optional_param('duration', 0, PARAM_INT);
            if ($title !== '') {
                $data['title'] = $title;
            }
            if ($starttime > 0) {
                $data['start_time'] = $starttime;
            }
            if ($meetinglink !== '') {
                $data['meeting_link'] = $meetinglink;
            }
            if ($duration > 0) {
                $data['duration'] = min($duration, 1440);
            }
            session_manager::update_session($sessionid, $data);
            return ['sessionid' => $sessionid, 'updated' => array_keys($data)];

        // Teacher is in (present=1) / left (present=0) the Jitsi call: opens / closes
        // the student gate (waitingforteacher). Same logic as the web's teacher_present.php.
        case 'set_teacher_present':
            api::require_post();
            $cm = academysessions_jitsi_cm(required_param('cmid', PARAM_INT));
            $present = (bool) optional_param('present', 1, PARAM_BOOL);
            if (!\mod_jitsi\local\presence::is_moderator($cm, $userid)) {
                throw new required_capability_exception(context_module::instance($cm->id),
                    'mod/jitsi:moderate', 'nopermissions', '');
            }
            $session = \mod_jitsi\local\presence::set($cm, $userid, $present);
            return [
                'cmid'              => (int) $cm->id,
                'present'           => $present,
                'sessionid'         => $session ? (int) $session->id : null,
                'teacher_joined_at' => ($session && $session->teacher_joined_at) ? (int) $session->teacher_joined_at : null,
            ];

        // Close a (standalone) Jitsi room so nobody can rejoin — same as the web's
        // ajax.php end_room. For a room linked to a session use end_session.
        case 'end_room':
            api::require_post();
            $cm = academysessions_jitsi_cm(required_param('cmid', PARAM_INT));
            if (!\mod_jitsi\local\presence::is_moderator($cm, $userid)) {
                throw new required_capability_exception(context_module::instance($cm->id),
                    'mod/jitsi:moderate', 'nopermissions', '');
            }
            return ['cmid' => (int) $cm->id, 'ended' => true,
                'ended_at' => \mod_jitsi\local\presence::end_room((int) $cm->id)];

        // ── Student ───────────────────────────────────────────────────────────────

        case 'get_student_sessions':
            return sessions::student_sessions($userid);

        // Records attendance and returns the meeting link + Jitsi cmid.
        case 'join_session':
            return sessions::join(required_param('sessionid', PARAM_INT), $userid);

        // Records left_at + time spent (any participant who joined).
        case 'leave_session':
            api::require_post();
            return sessions::leave(required_param('sessionid', PARAM_INT), $userid);

        // ── Recordings (Vimeo) ────────────────────────────────────────────────────

        case 'get_session_recordings':
            return sessions::recordings($userid,
                optional_param('sessionid', 0, PARAM_INT), optional_param('cmid', 0, PARAM_INT));
    }
    return api::unknown();
});
