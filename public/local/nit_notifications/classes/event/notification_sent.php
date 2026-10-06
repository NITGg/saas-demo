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

namespace local_nit_notifications\event;

/**
 * An admin or a manager sent a notification by hand — the audit trail in
 * Site administration → Reports → Logs.
 *
 * other: audience, type, total (recipients).
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_sent extends \core\event\base {

    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_nit_notif';
    }

    /**
     * The event for a notification just created.
     *
     * @param \stdClass $notif
     * @return self
     */
    public static function create_from_notif(\stdClass $notif): self {
        $context = (int) $notif->courseid > 1
            ? (\context_course::instance((int) $notif->courseid, IGNORE_MISSING) ?: \context_system::instance())
            : \context_system::instance();
        return self::create([
            'context' => $context,
            'objectid' => (int) $notif->id,
            'other' => ['audience' => $notif->audience, 'type' => $notif->type, 'total' => (int) $notif->total],
        ]);
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventnotificationsent', 'local_nit_notifications');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' sent the notification with id '{$this->objectid}' "
            . "to '{$this->other['audience']}' ({$this->other['total']} recipients).";
    }

    /**
     * The notification in the log.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/nit_notifications/log.php', ['id' => $this->objectid]);
    }

    /**
     * Validate.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['audience'])) {
            throw new \coding_exception('The \'audience\' value must be set in other.');
        }
    }

    /**
     * Restore mapping (not backed up).
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_nit_notif', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Other mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
