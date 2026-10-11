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

/**
 * Only a host of the room (mod_jitsi presence::is_moderator) may go on.
 *
 * @param stdClass $cm
 * @param int $userid
 */
function academysessions_require_host(stdClass $cm, int $userid): void {
    if (!\mod_jitsi\local\presence::is_moderator($cm, $userid)) {
        throw new required_capability_exception(context_module::instance($cm->id), 'mod/jitsi:moderate', 'nopermissions', '');
    }
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
            $session = sessions::require_session($sessionid);
            sessions::require_session_owner($session);
            session_manager::end_session($sessionid, (int) $session->teacherid === $userid ? 'teacher' : 'admin', $userid);
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

        // ── Any Jitsi room (course rooms and lesson rooms) ────────────────────────

        // The room's attendance log: call with present=1 on conference joined, present=0
        // on left (same as the web page). Joining is logged only while the room lets you in.
        case 'room_presence':
            api::require_post();
            $cm = academysessions_jitsi_cm(required_param('cmid', PARAM_INT));
            $present = required_param('present', PARAM_BOOL);
            if ($present) {
                $cminfo = get_fast_modinfo($cm->course, $userid)->get_cm($cm->id);
                $decision = \mod_jitsi\local\access::check($cminfo, $userid);
                if ($decision->allowed) {
                    \mod_jitsi\local\roomlog::joined((int) $cm->instance, $userid, $decision->moderator);
                }
            } else {
                \mod_jitsi\local\roomlog::left((int) $cm->instance, $userid);
            }
            return ['cmid' => (int) $cm->id, 'present' => $present];

        // A course room's waiting room, for its hosts: who is asking to come in.
        case 'get_room_admissions':
            $cm = academysessions_jitsi_cm(required_param('cmid', PARAM_INT));
            academysessions_require_host($cm, $userid);
            return \mod_jitsi\local\admission::waiting((int) $cm->instance);

        // Let one student in or turn them away (userid + admit=1/0), or everyone (all=1).
        // Returns who is still waiting.
        case 'decide_room_admission':
            api::require_post();
            $cm = academysessions_jitsi_cm(required_param('cmid', PARAM_INT));
            academysessions_require_host($cm, $userid);
            $admit = required_param('admit', PARAM_BOOL);
            if (optional_param('all', 0, PARAM_BOOL)) {
                foreach (\mod_jitsi\local\admission::waiting((int) $cm->instance) as $w) {
                    \mod_jitsi\local\admission::decide((int) $cm->instance, $w['userid'], $admit, $userid);
                }
            } else {
                \mod_jitsi\local\admission::decide((int) $cm->instance, required_param('userid', PARAM_INT), $admit, $userid);
            }
            return \mod_jitsi\local\admission::waiting((int) $cm->instance);

        // A student turned away asks again (mod_jitsi_get_session_info then says "waiting").
        case 'ask_room_admission':
            api::require_post();
            $cm = academysessions_jitsi_cm(required_param('cmid', PARAM_INT));
            return ['cmid' => (int) $cm->id,
                'state' => \mod_jitsi\local\admission::request((int) $cm->instance, $userid, (int) $cm->id)];

        // ── Recordings (Vimeo) ────────────────────────────────────────────────────

        case 'get_session_recordings':
            return sessions::recordings($userid,
                optional_param('sessionid', 0, PARAM_INT), optional_param('cmid', 0, PARAM_INT));
    }
    return api::unknown();
});
