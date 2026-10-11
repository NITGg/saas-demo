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
 * The waiting room of a course Jitsi room, kept by Moodle.
 *
 * A student asks to come in; a host (anyone with mod/jitsi:moderate in the room: the
 * course's teachers, managers, admins) lets them in or turns them away. Until let in, the
 * student gets no key (JWT) for the room, so this cannot be skipped from the browser.
 * Hosts never wait. It replaces the Jitsi lobby, which also made hosts knock.
 *
 * Applies to rooms not linked to a live session, when the room's "waiting room" setting
 * (jitsi.lobby_enabled) is on. A let-in lasts ADMIT_FOR seconds (rejoining in the same
 * class needs no new request) or until a host ends the room.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admission {

    /** @var string asked, not answered yet. */
    const WAITING = 'waiting';
    /** @var string let in. */
    const ADMITTED = 'admitted';
    /** @var string turned away. */
    const DENIED = 'denied';

    /** @var int how long a let-in lasts (seconds). */
    const ADMIT_FOR = 6 * HOURSECS;

    /**
     * Whether students of this room need a host's OK.
     *
     * @param \stdClass $jitsi the jitsi row
     * @param \stdClass|null $session the linked live session (those have their own rules)
     * @return bool
     */
    public static function required(\stdClass $jitsi, ?\stdClass $session): bool {
        return !$session && !empty($jitsi->lobby_enabled);
    }

    /**
     * A user's request in a room, if any.
     *
     * @param int $jitsiid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get(int $jitsiid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('jitsi_admission', ['jitsiid' => $jitsiid, 'userid' => $userid]) ?: null;
    }

    /**
     * The state of a user's request now: admitted (and still valid), waiting, denied, or none.
     *
     * @param int $jitsiid
     * @param int $userid
     * @param int $cmid (for the room's "ended" flag)
     * @param int|null $now
     * @return string|null one of the constants, or null when there is no live request
     */
    public static function state(int $jitsiid, int $userid, int $cmid, ?int $now = null): ?string {
        $now = $now ?? time();
        $row = self::get($jitsiid, $userid);
        if (!$row) {
            return null;
        }
        if ($row->status === self::ADMITTED) {
            $ended = presence::room_ended_at($cmid);
            $valid = $now - (int) $row->timedecided < self::ADMIT_FOR && (!$ended || $ended < (int) $row->timedecided);
            return $valid ? self::ADMITTED : null;
        }
        return $row->status;
    }

    /**
     * Ask to come in (or ask again after a let-in ran out or a refusal): the request
     * goes back to waiting. A request already waiting is left as it is.
     *
     * @param int $jitsiid
     * @param int $userid
     * @param int $cmid
     * @return string the state after asking
     */
    public static function request(int $jitsiid, int $userid, int $cmid): string {
        global $DB;
        $now = time();
        $row = self::get($jitsiid, $userid);
        $state = self::state($jitsiid, $userid, $cmid, $now);
        if ($state === self::ADMITTED || $state === self::WAITING) {
            return $state;
        }
        $data = ['jitsiid' => $jitsiid, 'userid' => $userid, 'status' => self::WAITING,
            'timerequested' => $now, 'timedecided' => 0, 'decidedby' => 0];
        if ($row) {
            $DB->update_record('jitsi_admission', (object) (['id' => $row->id] + $data));
        } else {
            $DB->insert_record('jitsi_admission', (object) $data);
        }
        return self::WAITING;
    }

    /**
     * A host answers a request.
     *
     * @param int $jitsiid
     * @param int $userid the student
     * @param bool $admit
     * @param int $by the host
     * @return bool false when there was nothing waiting
     */
    public static function decide(int $jitsiid, int $userid, bool $admit, int $by): bool {
        global $DB;
        $row = self::get($jitsiid, $userid);
        if (!$row || $row->status !== self::WAITING) {
            return false;
        }
        $row->status = $admit ? self::ADMITTED : self::DENIED;
        $row->timedecided = time();
        $row->decidedby = $by;
        $DB->update_record('jitsi_admission', $row);
        return true;
    }

    /**
     * Who is waiting now, oldest first.
     *
     * @param int $jitsiid
     * @return array[] {userid, name, waitingfor (minutes)}
     */
    public static function waiting(int $jitsiid): array {
        global $DB;
        $rows = $DB->get_records('jitsi_admission', ['jitsiid' => $jitsiid, 'status' => self::WAITING], 'timerequested ASC');
        if (!$rows) {
            return [];
        }
        $fields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
        $users = $DB->get_records_list('user', 'id', array_map(fn($r) => (int) $r->userid, $rows), '', 'id, ' . $fields);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'userid' => (int) $r->userid,
                'name' => isset($users[$r->userid]) ? fullname($users[$r->userid]) : '—',
                'waitingfor' => (int) floor((time() - (int) $r->timerequested) / MINSECS),
            ];
        }
        return $out;
    }
}
