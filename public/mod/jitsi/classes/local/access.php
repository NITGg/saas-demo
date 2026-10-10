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
 * A standalone room (no session) keeps Moodle's rules: mod/jitsi:moderate moderates.
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
            if (!$cm->available && !$moderator) {
                return $result(self::UNAVAILABLE);
            }
            return $result(self::OK);
        }

        $invited = $DB->record_exists('academy_session_students',
            ['sessionid' => $session->id, 'userid' => $userid]);
        if ((int) $session->teacherid !== $userid && !$invited && !is_siteadmin($userid)) {
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
        if ($now > (int) $session->start_time + (int) $session->duration * MINSECS) {
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
     * (and a site admin who is not one of its invited students); for a standalone room
     * whoever holds mod/jitsi:moderate.
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
        if (!$session) {
            return has_capability('mod/jitsi:moderate', \context_module::instance($cm->id), $userid);
        }
        if ((int) $session->teacherid === $userid) {
            return true;
        }
        return is_siteadmin($userid) && !$DB->record_exists('academy_session_students',
            ['sessionid' => $session->id, 'userid' => $userid]);
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
