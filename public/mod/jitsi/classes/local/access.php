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

namespace mod_jitsi\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Who may enter a Jitsi room, and as what.
 *
 * The one rule behind every place that hands out a room JWT: the web page (view.php),
 * the mobile WS (mod_jitsi_get_session_info) and the legacy api_token.php. Before this
 * each had its own copy and the mobile ones drifted: they made every course
 * editingteacher a moderator of any room and gave the JWT to a student who was still
 * waiting for the teacher.
 *
 * Rules for a room linked to a live session (academy_live_sessions.jitsiid):
 *  - only the session's teacher, its invited students and site admins get in;
 *  - the moderator is the session's teacher (a site admin too, for support, unless
 *    they are an invited student of it);
 *  - an ended or cancelled session is closed for everyone;
 *  - a student gets in from OPEN_BEFORE seconds before the start until the end, and
 *    only while the teacher is in the call (teacher_joined_at is set).
 * A course session (a Jitsi activity scheduled from its settings, audience = course) is
 * for the whole course: every user enrolled in it gets in (a student enrolled after it
 * was scheduled is added to its list on the way in), and whoever holds
 * mod/jitsi:moderate hosts it, like a standalone room; the time window and "the
 * teacher is in" still apply, except that it runs over its end while a teacher is
 * still in the call (session_lifecycle ends it after they leave).
 * A standalone room (no session) keeps Moodle's rules: mod/jitsi:moderate moderates, and
 * only users enrolled in the course get in (not a guest).
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {

    /** @var int seconds before the start that students may enter. */
    const OPEN_BEFORE = 1800;

    /** @var string the user may enter. */
    const OK = 'ok';
    /** @var string Moodle availability (dates, groups...) hides the room. */
    const UNAVAILABLE = 'unavailable';
    /** @var string not the session's teacher nor an invited student. */
    const NOT_ALLOWED = 'notallowed';
    /** @var string too early. */
    const NOT_OPEN = 'sessionnotavailable';
    /** @var string ended, cancelled or past its time. */
    const ENDED = 'sessionended';
    /** @var string the teacher is not in the call yet. */
    const WAITING = 'waitingforteacher';

    /**
     * Decide whether a user may enter a room.
     *
     * @param \cm_info $cm the room, loaded for $userid (get_fast_modinfo($course, $userid))
     * @param int $userid
     * @param int|null $now defaults to time()
     * @return \stdClass {allowed: bool, moderator: bool, code: string, session: ?stdClass,
     *                    opensin: int minutes until it opens (NOT_OPEN only), message: string}
     */
    public static function check(\cm_info $cm, int $userid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $session = presence::linked_session($cm);
        $moderator = self::is_moderator($cm, $userid, $session);

        $result = function(string $code, int $opensin = 0) use ($session, $moderator): \stdClass {
            return (object) [
                'allowed'   => $code === self::OK,
                'moderator' => $moderator,
                'code'      => $code,
                'session'   => $session,
                'opensin'   => $opensin,
                'message'   => self::message($code, $opensin),
            ];
        };

        if (presence::room_ended_at((int) $cm->id)) {
            return $result(self::ENDED);
        }
        if (!$session) {
            // The course's own members only: a guest (guest access, or the guest user) may
            // see the course but not join its calls.
            if (!$moderator && !is_enrolled(\context_course::instance($cm->course), $userid, '', true)) {
                return $result(self::NOT_ALLOWED);
            }
            if (!$cm->available && !$moderator) {
                return $result(self::UNAVAILABLE);
            }
            // Course room with a waiting room: the room's Jitsi lobby holds a student until a
            // host lets them in (inside the meeting). A host turns the lobby on when they join,
            // so a student only gets a key while a host is in the call; before that they wait
            // here, like a lesson room's students wait for the teacher.
            if (!$moderator && self::waiting_room($cm) && !roomlog::host_in((int) $cm->instance, $now)) {
                return $result(self::WAITING);
            }
            return $result(self::OK);
        }

        $invited = $DB->record_exists('academy_session_students',
            ['sessionid' => $session->id, 'userid' => $userid]);
        $member = $invited;
        if (\local_academysessions\session_manager::is_course_session($session)) {
            $member = $invited || $moderator
                || is_enrolled(\context_course::instance((int) $session->courseid), $userid, '', true);
            // A student enrolled after the session was scheduled: on its list from now on
            // (attendance, reports), without waiting for the next sync.
            if (!$invited && !$moderator && in_array($session->status, ['scheduled', 'live'], true)
                    && \local_academysessions\session_manager::is_course_student((int) $session->courseid, $userid)) {
                \local_academysessions\session_manager::add_student((int) $session->id, $userid);
            }
        }
        if ((int) $session->teacherid !== $userid && !$member && !is_siteadmin($userid)) {
            return $result(self::NOT_ALLOWED);
        }
        if ($session->status === 'ended' || $session->status === 'cancelled') {
            return $result(self::ENDED);
        }
        if ($moderator) {
            return $result(self::OK);
        }
        $opens = (int) $session->start_time - self::OPEN_BEFORE;
        if ($now < $opens) {
            return $result(self::NOT_OPEN, (int) ceil(($opens - $now) / MINSECS));
        }
        // Past its end: closed, except a course session while a teacher is still in the call
        // (it runs over, and ends once they leave).
        if ($now > (int) $session->start_time + (int) $session->duration * MINSECS
                && !(\local_academysessions\session_manager::is_course_session($session)
                    && !empty($session->teacher_joined_at))) {
            return $result(self::ENDED);
        }
        // Other Moodle restrictions on the activity (dates, groups) still apply. Checked
        // after the window, since jitsi_cm_info_dynamic() also hides it before the window.
        if (!$cm->available) {
            return $result(self::UNAVAILABLE);
        }
        if (empty($session->teacher_joined_at)) {
            return $result(self::WAITING);
        }
        return $result(self::OK);
    }

    /**
     * Whether a user moderates a room. For a linked room only the session's teacher
     * (and a site admin who is not one of its invited students); for a course session or
     * a standalone room whoever holds mod/jitsi:moderate.
     *
     * @param \stdClass|\cm_info $cm the room (needs ->id, ->instance)
     * @param int $userid
     * @param \stdClass|null|false $session the linked session; false = look it up
     * @return bool
     */
    public static function is_moderator($cm, int $userid, $session = false): bool {
        global $DB;
        if ($session === false) {
            $session = presence::linked_session($cm);
        }
        if ((int) ($session->teacherid ?? 0) === $userid) {
            return true;
        }
        if (!$session || \local_academysessions\session_manager::is_course_session($session)) {
            return has_capability('mod/jitsi:moderate', \context_module::instance($cm->id), $userid);
        }
        return is_siteadmin($userid) && !$DB->record_exists('academy_session_students',
            ['sessionid' => $session->id, 'userid' => $userid]);
    }

    /**
     * Whether a user is a teacher of a linked session: the one whose arrival lets the
     * students in, who starts the recording, and whose lateness the reports measure. The
     * session's own teacher; for a course session also any other teacher of the course who
     * hosts the room (enrolled, with mod/jitsi:moderate). A site admin or manager dropping
     * in is not.
     *
     * @param \stdClass $session academy_live_sessions row
     * @param int $userid
     * @param \stdClass|\cm_info|null $cm the room (null = looked up from the session)
     * @return bool
     */
    public static function is_session_teacher(\stdClass $session, int $userid, $cm = null): bool {
        if ((int) $session->teacherid === $userid) {
            return true;
        }
        if (!\local_academysessions\session_manager::is_course_session($session) || empty($session->jitsiid)) {
            return false;
        }
        $cmid = $cm ? (int) $cm->id
            : (\local_academysessions\mobile_service::jitsi_cmids([(int) $session->jitsiid])[(int) $session->jitsiid] ?? 0);
        if (!$cmid || !is_enrolled(\context_course::instance((int) $session->courseid), $userid, '', true)) {
            return false;
        }
        return has_capability('mod/jitsi:moderate', \context_module::instance($cmid), $userid);
    }

    /**
     * Note that a student was let into a linked room: opens (or reopens, after a
     * leave) their attendance row. Moderators are tracked by presence::set().
     *
     * @param \stdClass $decision a check() result
     * @param int $userid
     */
    public static function record_entry(\stdClass $decision, int $userid): void {
        if ($decision->allowed && !$decision->moderator && $decision->session) {
            \local_academysessions\session_manager::record_attendance((int) $decision->session->id, $userid, true);
        }
    }

    /**
     * Whether a room uses a waiting room (Jitsi lobby): a course room (standalone, or a course
     * session) with its "students wait for the teacher" setting on, and every private lesson
     * room (the student asks and the teacher lets them in). Other live-session rooms do not:
     * Moodle already lets in only the session's teacher, its invited students and admins.
     *
     * @param \cm_info|\stdClass $cm the room (->instance, ->course)
     * @return bool
     */
    public static function waiting_room($cm): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/jitsi/lib.php');
        if (jitsi_is_lesson_room($cm)) {
            return true;
        }
        $session = presence::linked_session($cm);
        if ($session && !\local_academysessions\session_manager::is_course_session($session)) {
            return false;
        }
        return (bool) $DB->get_field('jitsi', 'lobby_enabled', ['id' => $cm->instance]);
    }

    /**
     * The names of a room's hosts, as Jitsi shows them: the people a host's page lets
     * straight out of the lobby (everyone else waits for the teacher's answer). The room's
     * teachers first (jitsi_teacher_names()), then every site admin.
     *
     * @param \cm_info|\stdClass $cm
     * @param \stdClass|null $session
     * @return string[]
     */
    public static function host_names($cm, ?\stdClass $session): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/jitsi/lib.php');
        $names = jitsi_teacher_names($cm, $session);
        foreach (get_admins() as $admin) {
            $names[] = fullname($admin);
        }
        return array_values(array_unique($names));
    }

    /**
     * The user-facing message for a refusal code.
     *
     * @param string $code
     * @param int $opensin minutes, for NOT_OPEN
     * @return string
     */
    public static function message(string $code, int $opensin = 0): string {
        switch ($code) {
            case self::OK:
                return '';
            case self::NOT_OPEN:
                return get_string('sessionopening', 'jitsi', $opensin);
            case self::UNAVAILABLE:
                return get_string('roomunavailable', 'jitsi');
            default:
                return get_string($code, 'jitsi');
        }
    }
}
