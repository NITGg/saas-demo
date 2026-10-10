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

namespace local_academysessions\event;

/**
 * A supervisor opened the live monitoring wall, or one lesson on it — the audit
 * trail in Site administration → Reports → Logs.
 *
 * objectid: the session opened in detail (none for the wall itself).
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class monitor_viewed extends \core\event\base {

    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'academy_live_sessions';
    }

    /**
     * The event for the wall (no session) or one session.
     *
     * @param int $sessionid 0 = the wall
     * @return self
     */
    public static function for_session(int $sessionid = 0): self {
        $data = ['context' => \context_system::instance()];
        if ($sessionid) {
            $data['objectid'] = $sessionid;
        }
        return self::create($data);
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventmonitorviewed', 'local_academysessions');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        return $this->objectid
            ? "The user with id '{$this->userid}' viewed the live session with id '{$this->objectid}' on the monitoring wall."
            : "The user with id '{$this->userid}' viewed the live monitoring wall.";
    }

    /**
     * The wall.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/academysessions/monitor.php');
    }

    /**
     * No object mapping for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'academy_live_sessions', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
