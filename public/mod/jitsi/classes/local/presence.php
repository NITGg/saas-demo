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
     * academy_live_sessions.teacher_joined_at (the student entry gate), records the
     * teacher's first join in the attendance table and the lesson audit timeline.
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

        $session->teacher_joined_at = $present ? time() : null;
        $DB->set_field('academy_live_sessions', 'teacher_joined_at',
            $session->teacher_joined_at, ['id' => $session->id]);

        // Track first join time in attendance table for historical reports,
        // since teacher_joined_at is cleared when they leave the room.
        if ($present && !$DB->record_exists('academy_session_attendance',
                ['sessionid' => $session->id, 'userid' => $userid])) {
            $att = new \stdClass();
            $att->sessionid        = $session->id;
            $att->userid           = $userid;
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
                \local_academy\audit_manager::record_once($lessonid, 'teacher_joined', $userid, 'teacher');
            }
        }

        return $session;
    }

    /**
     * Whether a user moderates this room — the same rule as view.php: a holder of
     * mod/jitsi:moderate; for a room linked to a live session only the session's
     * assigned teacher (site admins keep moderator for support unless they are a
     * whitelisted student of it).
     *
     * @param \stdClass|\cm_info $cm the jitsi course module
     * @param int $userid
     * @return bool
     */
    public static function is_moderator($cm, int $userid): bool {
        global $DB;
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/jitsi:moderate', $context, $userid)) {
            return false;
        }
        $session = self::linked_session($cm);
        if (!$session) {
            return true;
        }
        if ((int) $session->teacherid === $userid) {
            return true;
        }
        return is_siteadmin($userid) && !$DB->record_exists('academy_session_students',
            ['sessionid' => $session->id, 'userid' => $userid]);
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
