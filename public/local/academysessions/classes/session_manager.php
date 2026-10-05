<?php
namespace local_academysessions;

defined('MOODLE_INTERNAL') || die();

class session_manager {

    public static function create_session($courseid, $teacherid, $title, $starttime, $studentids, $meetinglink = '', $duration = 50, $googlemeetid = null, $jitsiid = null) {
        global $DB;

        $now = time();
        $session = new \stdClass();
        $session->googlemeetid = $googlemeetid;
        $session->jitsiid      = $jitsiid;
        $session->courseid = $courseid;
        $session->teacherid = $teacherid;
        $session->title = $title;
        $session->start_time = $starttime;
        $session->duration = $duration;
        $session->meeting_link = $meetinglink;
        $session->status = 'scheduled';
        $session->timecreated = $now;
        $session->timemodified = $now;

        $sessionid = $DB->insert_record('academy_live_sessions', $session);

        foreach ($studentids as $uid) {
            $student = new \stdClass();
            $student->sessionid = $sessionid;
            $student->userid = $uid;
            $DB->insert_record('academy_session_students', $student);
        }

        return $sessionid;
    }

    // NOTE: create_recording_activity() (Bunny embed + mod_sessionrecording) was
    // removed for the SaaS. Recording playback will be re-added on VdoCipher
    // (local_vdocipher) in a later phase.

    public static function get_session($sessionid) {
        global $DB;
        return $DB->get_record('academy_live_sessions', array('id' => $sessionid), '*', MUST_EXIST);
    }

    public static function get_sessions_for_course($courseid, $status = null) {
        global $DB;
        $params = array('courseid' => $courseid);
        if ($status) {
            $params['status'] = $status;
        }
        return $DB->get_records('academy_live_sessions', $params, 'start_time DESC');
    }

    public static function get_sessions_for_teacher($teacherid) {
        global $DB;
        return $DB->get_records('academy_live_sessions', array('teacherid' => $teacherid), 'start_time DESC');
    }

    public static function get_visible_sessions_for_student($userid) {
        global $DB;
        $now = time();
        $sql = "SELECT s.*
                FROM {academy_live_sessions} s
                JOIN {academy_session_students} ss ON s.id = ss.sessionid
                WHERE ss.userid = :userid
                AND s.status IN ('scheduled', 'live')
                AND s.start_time - 1800 <= :now1
                AND s.start_time + (s.duration * 60) >= :now2
                ORDER BY s.start_time ASC";
        return $DB->get_records_sql($sql, array(
            'userid' => $userid,
            'now1' => $now,
            'now2' => $now
        ));
    }

    public static function get_upcoming_sessions_for_student($userid) {
        global $DB;
        $now = time();
        $sql = "SELECT s.*
                FROM {academy_live_sessions} s
                JOIN {academy_session_students} ss ON s.id = ss.sessionid
                WHERE ss.userid = :userid
                AND s.status IN ('scheduled', 'live')
                AND s.start_time + (s.duration * 60) >= :now
                ORDER BY s.start_time ASC";
        return $DB->get_records_sql($sql, array('userid' => $userid, 'now' => $now));
    }

    public static function is_student_allowed($sessionid, $userid) {
        global $DB;
        return $DB->record_exists('academy_session_students', array(
            'sessionid' => $sessionid,
            'userid' => $userid
        ));
    }

    public static function is_link_visible($session) {
        $now = time();
        $start = $session->start_time;
        $end = $start + ($session->duration * 60);
        return ($now >= $start - 1800) && ($now <= $end);
    }

    /**
     * Record that a user entered the session (first join only).
     *
     * @param int $sessionid
     * @param int $userid
     * @param bool $rejoin true = a user who left earlier is back: clear left_at so the row is
     *                     closed again by record_leave() / end_session(). The default keeps the
     *                     original behaviour (an existing row is left untouched).
     * @return int attendance row id
     */
    public static function record_attendance($sessionid, $userid, $rejoin = false) {
        global $DB;
        $existing = $DB->get_record('academy_session_attendance', array(
            'sessionid' => $sessionid,
            'userid' => $userid
        ));
        if ($existing) {
            if ($rejoin && !empty($existing->left_at)) {
                $DB->set_field('academy_session_attendance', 'left_at', null, ['id' => $existing->id]);
            }
            return $existing->id;
        }
        $record = new \stdClass();
        $record->sessionid = $sessionid;
        $record->userid = $userid;
        $record->joined_at = time();
        $record->duration_seconds = 0;
        return $DB->insert_record('academy_session_attendance', $record);
    }

    /**
     * Record that a user left the session: stamps left_at and the time spent
     * (left_at - first joined_at). A later leave (after a rejoin) overwrites both.
     *
     * @param int $sessionid
     * @param int $userid
     * @param int|null $now defaults to time()
     * @return \stdClass|null the updated attendance row; null when the user never joined
     */
    public static function record_leave($sessionid, $userid, $now = null) {
        global $DB;
        $att = $DB->get_record('academy_session_attendance', ['sessionid' => $sessionid, 'userid' => $userid]);
        if (!$att) {
            return null;
        }
        $now = $now ?? time();
        $att->left_at = max((int) $now, (int) $att->joined_at);
        $att->duration_seconds = $att->left_at - (int) $att->joined_at;
        $DB->update_record('academy_session_attendance', $att);
        return $att;
    }

    public static function end_session($sessionid) {
        global $DB;
        $session = self::get_session($sessionid);
        $session->status = 'ended';
        $session->timemodified = time();
        $DB->update_record('academy_live_sessions', $session);

        $attendances = $DB->get_records('academy_session_attendance', array('sessionid' => $sessionid));
        foreach ($attendances as $att) {
            if (empty($att->left_at)) {
                $att->left_at = time();
                $att->duration_seconds = $att->left_at - $att->joined_at;
                $DB->update_record('academy_session_attendance', $att);
            }
        }
    }

    public static function start_session($sessionid) {
        global $DB;
        $session = self::get_session($sessionid);
        $session->status = 'live';
        $session->timemodified = time();
        $DB->update_record('academy_live_sessions', $session);
    }

    public static function get_attendance_report($sessionid) {
        global $DB;
        $sql = "SELECT a.*, u.firstname, u.lastname, u.email
                FROM {academy_session_attendance} a
                JOIN {user} u ON a.userid = u.id
                WHERE a.sessionid = :sessionid
                ORDER BY a.joined_at ASC";
        return $DB->get_records_sql($sql, array('sessionid' => $sessionid));
    }

    public static function get_allowed_students($sessionid) {
        global $DB;
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                FROM {academy_session_students} ss
                JOIN {user} u ON ss.userid = u.id
                WHERE ss.sessionid = :sessionid
                ORDER BY u.firstname ASC";
        return $DB->get_records_sql($sql, array('sessionid' => $sessionid));
    }

    public static function update_session($sessionid, $data) {
        global $DB;
        $session = self::get_session($sessionid);
        foreach ($data as $key => $value) {
            $session->$key = $value;
        }
        $session->timemodified = time();
        $DB->update_record('academy_live_sessions', $session);
    }

    public static function delete_session($sessionid) {
        global $DB;
        $DB->delete_records('academy_session_students', array('sessionid' => $sessionid));
        $DB->delete_records('academy_session_attendance', array('sessionid' => $sessionid));
        $DB->delete_records('academy_live_sessions', array('id' => $sessionid));
    }
}
