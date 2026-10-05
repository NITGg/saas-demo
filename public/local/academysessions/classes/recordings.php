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
 * Live-session recordings (table academy_session_recordings), as stored by
 * mod/jitsi/record_notify.php after Jibri uploads a recording to Vimeo.
 *
 * Same selection + visibility rules as the web page (mod/jitsi/view.php
 * jitsi_print_recordings()): rows of the Jitsi activity (cmid) OR of its linked
 * session that carry a Vimeo video id, newest first; staff always see them,
 * students of a linked session only once the session is over.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recordings {

    /** Vimeo player base URL (the recording is embed-whitelisted to the academy domain). */
    const PLAYER_BASE = 'https://player.vimeo.com/video/';

    /**
     * Recording rows of a room: by Jitsi course-module id and/or live-session id.
     *
     * @param int $cmid Jitsi course-module id (0 = none)
     * @param int $sessionid linked live-session id (0 = none)
     * @return \stdClass[] newest first
     */
    public static function rows(int $cmid, int $sessionid = 0): array {
        global $DB;
        $where = [];
        $params = [];
        if ($cmid > 0) {
            $where[] = 'cmid = :cmid';
            $params['cmid'] = $cmid;
        }
        if ($sessionid > 0) {
            $where[] = 'sessionid = :sid';
            $params['sid'] = $sessionid;
        }
        if (!$where) {
            return [];
        }
        return array_values($DB->get_records_select('academy_session_recordings',
            '(' . implode(' OR ', $where) . ") AND vimeo_videoid IS NOT NULL AND vimeo_videoid <> ''",
            $params, 'timecreated DESC, id DESC'));
    }

    /**
     * App-facing shape of one recording.
     *
     * @param \stdClass $rec academy_session_recordings row
     * @return array
     */
    public static function export(\stdClass $rec): array {
        $url = self::PLAYER_BASE . rawurlencode((string) $rec->vimeo_videoid);
        return [
            'id'            => (int) $rec->id,
            'sessionid'     => $rec->sessionid !== null ? (int) $rec->sessionid : null,
            'cmid'          => $rec->cmid !== null ? (int) $rec->cmid : null,
            'title'         => format_string($rec->title ?: get_string('recording', 'local_academysessions')),
            'duration'      => (int) ($rec->duration ?? 0),
            'status'        => (string) $rec->status,
            'vimeo_videoid' => (string) $rec->vimeo_videoid,
            'playback_url'  => $url,
            'embed_url'     => $url,
            'timecreated'   => (int) $rec->timecreated,
        ];
    }

    /**
     * Exported recordings of a room.
     *
     * @param int $cmid
     * @param int $sessionid
     * @return array[]
     */
    public static function list_for(int $cmid, int $sessionid = 0): array {
        return array_map([self::class, 'export'], self::rows($cmid, $sessionid));
    }

    /**
     * May a non-staff participant see the recordings yet? Standalone rooms: always.
     * Linked sessions: once ended, or once the session window has passed.
     *
     * @param \stdClass|null $session academy_live_sessions row (null = standalone room)
     * @param int|null $now
     * @return bool
     */
    public static function visible_to_students(?\stdClass $session, ?int $now = null): bool {
        if (!$session) {
            return true;
        }
        if (($session->status ?? '') === 'ended') {
            return true;
        }
        $now = $now ?? time();
        return $now > ((int) $session->start_time + ((int) $session->duration * 60));
    }
}
