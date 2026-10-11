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
 * Every time someone is in a Jitsi room: one row per stretch in the call (joined →
 * left), for every room (course rooms and lesson rooms), shown on the room's
 * "Attendance log" page (attendance.php).
 *
 * The web page reports "joined" when Jitsi says the user is in the conference and
 * "left" on hang-up / leaving the page; the app does the same through the API. A
 * stretch still open when the next "joined" comes is kept (a second tab / repeated ping).
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roomlog {

    /**
     * Someone came into the call (idempotent while their stretch is open).
     *
     * @param int $jitsiid
     * @param int $userid
     * @param bool $host whether they are a host of the room
     * @param int|null $now
     */
    public static function joined(int $jitsiid, int $userid, bool $host, ?int $now = null): void {
        global $DB;
        if ($DB->record_exists_select('jitsi_presence', 'jitsiid = :j AND userid = :u AND left_at IS NULL',
                ['j' => $jitsiid, 'u' => $userid])) {
            return;
        }
        $DB->insert_record('jitsi_presence', (object) [
            'jitsiid' => $jitsiid,
            'userid' => $userid,
            'host' => $host ? 1 : 0,
            'joined_at' => $now ?? time(),
            'left_at' => null,
        ]);
    }

    /**
     * Someone left the call: their open stretch ends.
     *
     * @param int $jitsiid
     * @param int $userid
     * @param int|null $now
     */
    public static function left(int $jitsiid, int $userid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        foreach ($DB->get_records_select('jitsi_presence', 'jitsiid = :j AND userid = :u AND left_at IS NULL',
                ['j' => $jitsiid, 'u' => $userid]) as $p) {
            $DB->set_field('jitsi_presence', 'left_at', max($now, (int) $p->joined_at), ['id' => $p->id]);
        }
    }

    /** @var int an open host stretch older than this is not trusted (a crashed browser never says "left"). */
    const HOST_STALE = 6 * HOURSECS;

    /**
     * Whether a host (teacher, manager, admin) is in the room's call now: a course room
     * with a waiting room only lets students in while one is, so someone can let them in.
     *
     * @param int $jitsiid
     * @param int|null $now
     * @return bool
     */
    public static function host_in(int $jitsiid, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        return $DB->record_exists_select('jitsi_presence',
            'jitsiid = :j AND host = 1 AND left_at IS NULL AND joined_at > :since',
            ['j' => $jitsiid, 'since' => $now - self::HOST_STALE]);
    }

    /**
     * Close every open stretch of a room (the room was ended).
     *
     * @param int $jitsiid
     * @param int|null $now
     */
    public static function close_all(int $jitsiid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $DB->execute('UPDATE {jitsi_presence} SET left_at = :now WHERE jitsiid = :j AND left_at IS NULL',
            ['now' => $now, 'j' => $jitsiid]);
    }

    /**
     * The days the room was used, newest first (Y-m-d in the viewer's timezone).
     *
     * @param int $jitsiid
     * @return string[]
     */
    public static function days(int $jitsiid): array {
        global $DB;
        $days = [];
        foreach ($DB->get_fieldset_select('jitsi_presence', 'joined_at', 'jitsiid = ?', [$jitsiid]) as $t) {
            $days[userdate((int) $t, '%Y-%m-%d')] = true;
        }
        krsort($days);
        return array_keys($days);
    }

    /**
     * Everyone in the room on one day, with their stretches, for the attendance page:
     * hosts first, then by first join.
     *
     * @param int $jitsiid
     * @param string $day Y-m-d (viewer's timezone)
     * @param int|null $now
     * @return array {from, to, people: [{userid, name, host, visits, seconds, first, last, open, stretches: [[joined, left|null]]}]}
     */
    public static function day(int $jitsiid, string $day, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        [$y, $m, $d] = array_map('intval', explode('-', $day));
        $from = make_timestamp($y, $m, $d);
        $to = make_timestamp($y, $m, $d + 1);
        $rows = $DB->get_records_select('jitsi_presence', 'jitsiid = :j AND joined_at >= :f AND joined_at < :t',
            ['j' => $jitsiid, 'f' => $from, 't' => $to], 'joined_at, id');
        $people = [];
        foreach ($rows as $r) {
            $uid = (int) $r->userid;
            $p = $people[$uid] ?? ['userid' => $uid, 'host' => false, 'visits' => 0, 'seconds' => 0,
                'first' => (int) $r->joined_at, 'last' => 0, 'open' => false, 'stretches' => []];
            $left = $r->left_at ? (int) $r->left_at : null;
            $p['host'] = $p['host'] || (bool) $r->host;
            $p['visits']++;
            $p['seconds'] += max(0, ($left ?? min($now, $to)) - (int) $r->joined_at);
            $p['last'] = max($p['last'], (int) $left);
            $p['open'] = $left === null;
            $p['stretches'][] = [(int) $r->joined_at, $left];
            $people[$uid] = $p;
        }
        if ($people) {
            $fields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
            $users = $DB->get_records_list('user', 'id', array_keys($people), '', 'id, ' . $fields);
            foreach ($people as $uid => $p) {
                $people[$uid]['name'] = isset($users[$uid]) ? fullname($users[$uid]) : '—';
            }
        }
        usort($people, fn($a, $b) => [$b['host'], $a['first']] <=> [$a['host'], $b['first']]);
        return ['from' => $from, 'to' => $to, 'people' => $people];
    }
}
