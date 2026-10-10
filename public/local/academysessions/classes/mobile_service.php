<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_academysessions;

defined('MOODLE_INTERNAL') || die();

/**
 * Live sessions for the mobile token API (local/academysessions/api.php): access
 * checks + app-facing payloads on top of session_manager. Business errors are
 * moodle_exception('err_<code>', 'local_academysessions'); capability problems are
 * required_capability_exception (the endpoint maps them to "nopermissions").
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_service {

    /** Capability to create / edit / start / end sessions in a course. */
    const CAP_MANAGE = 'local/academysessions:managesessions';
    /** Capability to read a course's sessions + attendance. */
    const CAP_ATTENDANCE = 'local/academysessions:viewattendance';

    // ── Loading + access ──────────────────────────────────────────────────────

    /**
     * Load a session or fail with "sessionnotfound".
     *
     * @param int $sessionid
     * @return \stdClass
     */
    public static function require_session(int $sessionid): \stdClass {
        global $DB;
        $session = $sessionid > 0 ? $DB->get_record('academy_live_sessions', ['id' => $sessionid]) : false;
        if (!$session) {
            throw new \moodle_exception('err_sessionnotfound', 'local_academysessions');
        }
        return $session;
    }

    /**
     * Course context of an existing course, or fail with "coursenotfound".
     *
     * @param int $courseid
     * @return \context_course
     */
    public static function course_context(int $courseid): \context_course {
        global $DB;
        if ($courseid <= SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
            throw new \moodle_exception('err_coursenotfound', 'local_academy');
        }
        return \context_course::instance($courseid);
    }

    /**
     * Require one capability in a session's course.
     *
     * @param \stdClass $session
     * @param string $capability
     */
    public static function require_course_capability(\stdClass $session, string $capability): void {
        require_capability($capability, self::course_context((int) $session->courseid));
    }

    /**
     * Require the right to change a session (start / end / update / delete): the
     * manage capability in its course AND being its own teacher — or a platform
     * manager / site admin. Stops one teacher of a shared course from ending or
     * deleting another teacher's session.
     *
     * @param \stdClass $session
     */
    public static function require_session_owner(\stdClass $session): void {
        global $USER;
        self::require_course_capability($session, self::CAP_MANAGE);
        if ((int) $session->teacherid === (int) $USER->id || is_siteadmin()
                || has_capability('local/academy:manageplatform', \context_system::instance())) {
            return;
        }
        throw new \moodle_exception('err_notsessionteacher', 'local_academysessions');
    }

    /**
     * Whether the user is staff for a session: its teacher, or holds manage /
     * attendance in its course.
     *
     * @param \stdClass $session
     * @param int $userid
     * @return bool
     */
    public static function is_staff(\stdClass $session, int $userid): bool {
        if ((int) $session->teacherid === $userid) {
            return true;
        }
        $context = \context_course::instance((int) $session->courseid, IGNORE_MISSING);
        return $context && has_any_capability([self::CAP_MANAGE, self::CAP_ATTENDANCE], $context, $userid);
    }

    // ── Payloads ──────────────────────────────────────────────────────────────

    /**
     * Course-module ids of Jitsi instances (jitsiid → cmid).
     *
     * @param int[] $jitsiids
     * @return array<int,int>
     */
    public static function jitsi_cmids(array $jitsiids): array {
        global $DB;
        $jitsiids = array_values(array_unique(array_filter(array_map('intval', $jitsiids))));
        if (!$jitsiids || !$DB->get_manager()->table_exists('jitsi')) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($jitsiids, SQL_PARAMS_NAMED);
        $sql = "SELECT cm.instance, cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'jitsi'
                 WHERE cm.instance $in AND cm.deletioninprogress = 0";
        $map = [];
        foreach ($DB->get_records_sql_menu($sql, $params) as $instance => $cmid) {
            $map[(int) $instance] = (int) $cmid;
        }
        return $map;
    }

    /**
     * Add the app-facing extras to a session row, keeping every original column
     * (existing clients read them as-is): cmid of the Jitsi activity (for
     * mod_jitsi_get_session_info / api_token.php) and teacher_present.
     *
     * @param \stdClass $session academy_live_sessions row
     * @param array<int,int> $cmids from jitsi_cmids()
     * @return \stdClass
     */
    public static function export_session(\stdClass $session, array $cmids): \stdClass {
        $out = clone $session;
        $jid = (int) ($session->jitsiid ?? 0);
        $out->cmid = ($jid && isset($cmids[$jid])) ? $cmids[$jid] : null;
        $out->teacher_present = !empty($session->teacher_joined_at);
        $out->title_formatted = format_string($session->title);
        return $out;
    }

    /**
     * Export a list of session rows.
     *
     * @param \stdClass[] $sessions
     * @return \stdClass[]
     */
    protected static function export_sessions(array $sessions): array {
        $cmids = self::jitsi_cmids(array_map(fn($s) => $s->jitsiid ?? 0, $sessions));
        return array_map(fn($s) => self::export_session($s, $cmids), array_values($sessions));
    }

    // ── Teacher ───────────────────────────────────────────────────────────────

    /**
     * Sessions for a teacher screen. With a course: every session of that course
     * (needs manage or attendance capability there). Without: the caller's own.
     *
     * @param int $userid
     * @param int $courseid 0 = the caller's own sessions
     * @return \stdClass[]
     */
    public static function teacher_sessions(int $userid, int $courseid = 0): array {
        if ($courseid) {
            $context = self::course_context($courseid);
            if (!has_any_capability([self::CAP_MANAGE, self::CAP_ATTENDANCE], $context, $userid)) {
                throw new \required_capability_exception($context, self::CAP_ATTENDANCE, 'nopermissions', '');
            }
            $sessions = session_manager::get_sessions_for_course($courseid);
        } else {
            $sessions = session_manager::get_sessions_for_teacher($userid);
        }
        $out = [];
        foreach (self::export_sessions($sessions) as $s) {
            $s->students = array_values(session_manager::get_allowed_students($s->id));
            $s->attendance_count = count(session_manager::get_attendance_report($s->id));
            $out[] = $s;
        }
        return $out;
    }

    /**
     * Validate + create a session (manage capability in the course).
     *
     * @param int $userid the teacher (creator)
     * @param int $courseid
     * @param string $title
     * @param int $starttime
     * @param string $studentidscsv comma-separated user ids
     * @param string $meetinglink
     * @param int $duration minutes
     * @param int $googlemeetid
     * @param int $jitsiid jitsi INSTANCE id (0 = none)
     * @return array ['sessionid','cmid']
     */
    public static function create(int $userid, int $courseid, string $title, int $starttime, string $studentidscsv,
            string $meetinglink = '', int $duration = 50, int $googlemeetid = 0, int $jitsiid = 0): array {
        global $DB;
        $context = self::course_context($courseid);
        require_capability(self::CAP_MANAGE, $context, $userid);

        $title = trim($title);
        if ($title === '') {
            throw new \moodle_exception('err_invalidvalue', 'local_academysessions', '', 'title');
        }
        if ($starttime <= 0) {
            throw new \moodle_exception('err_invalidvalue', 'local_academysessions', '', 'starttime');
        }
        if ($duration <= 0 || $duration > 1440) {
            throw new \moodle_exception('err_invalidvalue', 'local_academysessions', '', 'duration');
        }
        $cmid = null;
        if ($jitsiid) {
            if (!$DB->record_exists('jitsi', ['id' => $jitsiid, 'course' => $courseid])) {
                throw new \moodle_exception('err_invalidvalue', 'local_academysessions', '', 'jitsiid');
            }
            $cmid = self::jitsi_cmids([$jitsiid])[$jitsiid] ?? null;
        }
        $studentids = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $studentidscsv)), fn($id) => $id > 0)));
        foreach ($studentids as $studentid) {
            if (!is_enrolled($context, $studentid, '', true)) {
                throw new \moodle_exception('err_studentnotenrolled', 'local_academysessions', '', $studentid);
            }
        }

        $sessionid = session_manager::create_session($courseid, $userid, $title, $starttime, $studentids,
            $meetinglink, $duration, $googlemeetid ?: null, $jitsiid ?: null);
        return ['sessionid' => (int) $sessionid, 'cmid' => $cmid];
    }

    /**
     * Mark a session live (manage capability). The app then opens the room (cmid)
     * and calls set_teacher_present once the teacher is in the call.
     *
     * @param int $sessionid
     * @return \stdClass the session payload
     */
    public static function start(int $sessionid): \stdClass {
        $session = self::require_session($sessionid);
        self::require_session_owner($session);
        if ($session->status === 'ended' || $session->status === 'cancelled') {
            throw new \moodle_exception('err_sessionended', 'local_academysessions');
        }
        session_manager::start_session($sessionid);
        return self::export_sessions([session_manager::get_session($sessionid)])[0];
    }

    // ── Student ───────────────────────────────────────────────────────────────

    /**
     * The student's upcoming / current sessions. The meeting link is blanked until
     * it opens (30 min before start).
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function student_sessions(int $userid): array {
        $out = [];
        foreach (self::export_sessions(session_manager::get_upcoming_sessions_for_student($userid)) as $s) {
            $s->link_visible = session_manager::is_link_visible($s);
            if (!$s->link_visible) {
                $s->meeting_link = '';
            }
            $out[] = $s;
        }
        return $out;
    }

    /**
     * A whitelisted student joins: checks the window and returns the meeting details.
     * For a Jitsi room, attendance is only recorded once the teacher is in the call (a
     * rejoin after leave_session reopens the row); before that the student is still
     * waiting outside, and mod_jitsi_get_session_info records it when it lets them in.
     *
     * @param int $sessionid
     * @param int $userid
     * @return array
     */
    public static function join(int $sessionid, int $userid): array {
        $session = self::require_session($sessionid);
        if (!session_manager::is_student_allowed($sessionid, $userid)) {
            throw new \moodle_exception('err_notallowed', 'local_academysessions');
        }
        if ($session->status === 'ended' || $session->status === 'cancelled') {
            throw new \moodle_exception('err_sessionended', 'local_academysessions');
        }
        if (!session_manager::is_link_visible($session)) {
            $code = time() < (int) $session->start_time ? 'err_sessionnotavailable' : 'err_sessionended';
            throw new \moodle_exception($code, 'local_academysessions');
        }
        // A session without a Jitsi room (external meeting link) has no teacher gate.
        if (empty($session->jitsiid) || !empty($session->teacher_joined_at)) {
            session_manager::record_attendance($sessionid, $userid, true);
        }
        $cmids = self::jitsi_cmids([(int) $session->jitsiid]);
        return [
            'meeting_link'    => (string) $session->meeting_link,
            'sessionid'       => (int) $session->id,
            'jitsiid'         => $session->jitsiid !== null ? (int) $session->jitsiid : null,
            'cmid'            => $cmids[(int) $session->jitsiid] ?? null,
            'status'          => (string) $session->status,
            'teacher_present' => !empty($session->teacher_joined_at),
        ];
    }

    /**
     * A participant leaves: stamps left_at + the time spent.
     *
     * @param int $sessionid
     * @param int $userid
     * @return array
     */
    public static function leave(int $sessionid, int $userid): array {
        self::require_session($sessionid);
        $att = session_manager::record_leave($sessionid, $userid);
        if (!$att) {
            throw new \moodle_exception('err_notjoined', 'local_academysessions');
        }
        return [
            'sessionid'        => (int) $sessionid,
            'joined_at'        => (int) $att->joined_at,
            'left_at'          => (int) $att->left_at,
            'duration_seconds' => (int) $att->duration_seconds,
        ];
    }

    // ── Recordings ────────────────────────────────────────────────────────────

    /**
     * Recordings of a session or of a Jitsi room, with the web page's visibility
     * rules. Pass a sessionid or a (jitsi) cmid.
     *
     * @param int $userid
     * @param int $sessionid
     * @param int $cmid
     * @return array ['sessionid','cmid','available','recordings']
     */
    public static function recordings(int $userid, int $sessionid = 0, int $cmid = 0): array {
        global $DB;
        if ($sessionid) {
            $session = self::require_session($sessionid);
            $cmid = self::jitsi_cmids([(int) $session->jitsiid])[(int) $session->jitsiid] ?? 0;
            $staff = self::is_staff($session, $userid);
            if (!$staff && !session_manager::is_student_allowed($sessionid, $userid)) {
                throw new \moodle_exception('err_notallowed', 'local_academysessions');
            }
        } else if ($cmid) {
            $cm = get_coursemodule_from_id('jitsi', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                throw new \moodle_exception('err_notjitsiactivity', 'local_academysessions');
            }
            $context = \context_module::instance($cm->id);
            require_capability('mod/jitsi:view', $context, $userid);
            $cminfo = get_fast_modinfo($cm->course, $userid)->get_cm($cm->id);
            $moderator = has_capability('mod/jitsi:moderate', $context, $userid);
            if (!$cminfo->uservisible && !$moderator) {
                throw new \required_capability_exception($context, 'mod/jitsi:view', 'nopermissions', '');
            }
            $session = $DB->get_record('academy_live_sessions', ['jitsiid' => $cm->instance]) ?: null;
            $staff = $moderator;
            if ($session) {
                $staff = self::is_staff($session, $userid) || is_siteadmin($userid);
                if (!$staff && !session_manager::is_student_allowed((int) $session->id, $userid)) {
                    throw new \moodle_exception('err_notallowed', 'local_academysessions');
                }
            }
        } else {
            throw new \moodle_exception('err_invalidvalue', 'local_academysessions', '', 'sessionid / cmid');
        }

        $available = $staff || recordings::visible_to_students($session);
        return [
            'sessionid'  => $session ? (int) $session->id : null,
            'cmid'       => $cmid ?: null,
            'available'  => $available,
            'recordings' => $available ? recordings::list_for((int) $cmid, $session ? (int) $session->id : 0) : [],
        ];
    }
}
