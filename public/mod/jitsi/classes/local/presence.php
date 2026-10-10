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
 * Teacher presence in a Jitsi room + ending a room.
 *
 * Shared by the web (teacher_present.php / ajax.php, sesskey) and the mobile token
 * API (local/academysessions/api.php set_teacher_present / end_room) so both stamp
 * exactly the same data. The caller does the auth + capability checks.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence {

    /**
     * The academy live session linked to a Jitsi activity, if any.
     *
     * @param \stdClass|\cm_info $cm the jitsi course module (needs ->instance)
     * @return \stdClass|null academy_live_sessions row
     */
    public static function linked_session($cm): ?\stdClass {
        global $DB;
        return $DB->get_record('academy_live_sessions', ['jitsiid' => $cm->instance]) ?: null;
    }

    /**
     * Mark the teacher as present in (or gone from) the room. Stamps
     * academy_live_sessions.teacher_joined_at (the student entry gate: set while the
     * teacher is in the call, cleared when they leave), the session teacher's first
     * join (teacher_first_join, never changed afterwards: what lateness is measured
     * from) and their attendance row.
     * Standalone rooms (no linked session) have no gate: nothing is stored.
     *
     * @param \stdClass|\cm_info $cm the jitsi course module
     * @param int $userid the teacher
     * @param bool $present true = joined, false = left
     * @return \stdClass|null the linked session after the change; null for a standalone room
     */
    public static function set($cm, int $userid, bool $present): ?\stdClass {
        global $DB;

        $session = self::linked_session($cm);
        if (!$session) {
            return null;
        }

        $now = time();
        $session->teacher_joined_at = $present ? $now : null;
        $DB->set_field('academy_live_sessions', 'teacher_joined_at',
            $session->teacher_joined_at, ['id' => $session->id]);

        // The session teacher's first entry, kept for lateness reports. Only stamped while
        // still empty (in SQL, so two racing "joined" pings cannot overwrite it); a site
        // admin dropping in for support does not count as the teacher arriving.
        if ($present && (int) $session->teacherid === $userid) {
            $DB->execute('UPDATE {academy_live_sessions} SET teacher_first_join = :now
                           WHERE id = :id AND teacher_first_join IS NULL',
                ['now' => $now, 'id' => $session->id]);
            $session->teacher_first_join = $DB->get_field('academy_live_sessions', 'teacher_first_join',
                ['id' => $session->id]);
        }

        // The teacher's attendance row (first join; a rejoin after a leave reopens it).
        if ($present) {
            \local_academysessions\session_manager::record_attendance((int) $session->id, $userid, true);
        }

        return $session;
    }

    /**
     * Whether a user moderates this room. See access::is_moderator().
     *
     * @param \stdClass|\cm_info $cm the jitsi course module
     * @param int $userid
     * @return bool
     */
    public static function is_moderator($cm, int $userid): bool {
        return access::is_moderator($cm, $userid);
    }

    /**
     * End a room: lock the activity so nobody can (re)join it (view.php checks
     * the mod_jitsi "ended_<cmid>" flag). Used for standalone rooms; a linked
     * session is ended with session_manager::end_session().
     *
     * @param int $cmid
     * @return int the time the room was ended
     */
    public static function end_room(int $cmid): int {
        $now = time();
        set_config('ended_' . $cmid, $now, 'mod_jitsi');
        return $now;
    }

    /**
     * When the room was ended (0 = still open).
     *
     * @param int $cmid
     * @return int
     */
    public static function room_ended_at(int $cmid): int {
        return (int) get_config('mod_jitsi', 'ended_' . $cmid);
    }
}
