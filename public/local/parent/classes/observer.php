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

namespace local_parent;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns a student's "parent phone" profile field into a link row.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event): void {
        self::sync((int) $event->objectid);
    }

    /**
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event): void {
        self::sync((int) $event->objectid);
    }

    /**
     * Read the user's parent-phone profile field and (re)record the link.
     * Idempotent — record_parent_phone() upserts, so repeat updates are safe.
     *
     * @param int $userid
     */
    private static function sync(int $userid): void {
        if ($userid <= 0) {
            return;
        }
        $shortname = (string) (get_config('local_parent', 'parentphonefield') ?: 'parentphone');
        $phone = self::profile_field_value($userid, $shortname);
        if ($phone !== '') {
            link_manager::record_parent_phone($userid, $phone);
        }
    }

    /**
     * The raw value of a custom profile field for a user, or '' if unset.
     *
     * @param int $userid
     * @param string $shortname
     * @return string
     */
    private static function profile_field_value(int $userid, string $shortname): string {
        global $DB;
        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        if (!$fieldid) {
            return '';
        }
        $data = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
        return trim((string) $data);
    }
}
