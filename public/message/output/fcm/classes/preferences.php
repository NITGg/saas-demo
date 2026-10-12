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

namespace message_fcm;

/**
 * Notification preferences for FCM.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences {

    /**
     * Turns FCM on wherever airnotifier (the push it replaces) is on.
     *
     * Moodle only hands a notification to the outputs listed in its "enabled" preference. When FCM was
     * installed those lists already existed without it, so notifications never reached FCM. This copies
     * airnotifier's site defaults (locked + enabled) to FCM, and adds FCM to every user's own list that
     * has airnotifier. Safe to run again.
     */
    public static function mirror_airnotifier(): void {
        global $DB;

        $defaults = get_config('message');
        foreach ((array)$defaults as $name => $value) {
            if (preg_match('/^airnotifier_provider_(.+)_locked$/', $name, $m)) {
                set_config('fcm_provider_' . $m[1] . '_locked', $value, 'message');
            } else if (preg_match('/^message_provider_.+_enabled$/', $name)) {
                $list = self::add_fcm((string)$value);
                if ($list !== null) {
                    set_config($name, $list, 'message');
                }
            }
        }

        $like = $DB->sql_like('name', ':name');
        $rs = $DB->get_recordset_select('user_preferences', $like, ['name' => 'message\_provider\_%\_enabled'],
            '', 'id, userid, name, value');
        foreach ($rs as $pref) {
            $list = self::add_fcm((string)$pref->value);
            if ($list !== null) {
                $DB->set_field('user_preferences', 'value', $list, ['id' => $pref->id]);
                mark_user_preferences_changed($pref->userid);
            }
        }
        $rs->close();
        \cache_helper::purge_by_definition('core', 'message_processors_enabled');
    }

    /**
     * Adds fcm next to airnotifier in a comma-separated processor list.
     *
     * @param string $list e.g. "popup,airnotifier"
     * @return string|null The new list, or null when nothing changes.
     */
    public static function add_fcm(string $list): ?string {
        $names = array_filter(explode(',', $list), fn($n) => $n !== '' && $n !== 'none');
        if (!in_array('airnotifier', $names) || in_array('fcm', $names)) {
            return null;
        }
        $names[] = 'fcm';
        return implode(',', $names);
    }
}
