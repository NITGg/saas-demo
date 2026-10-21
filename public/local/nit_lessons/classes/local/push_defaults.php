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

namespace local_nit_lessons\local;

/**
 * Turns the phone push outputs on for this plugin's notifications on an existing site.
 *
 * db/messages.php only sets defaults when a provider is first installed: lessonupdate and
 * instantoffer were installed without push, so Moodle stored "popup,email" and never handed
 * them to FCM. This adds the push outputs to the site defaults and to users' own lists (except
 * a list a user set to "none"). Safe to run again.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class push_defaults {
    /** @var string[] Providers that must reach the phone. */
    const PROVIDERS = ['lessonupdate', 'instantoffer'];

    /** @var string[] Push outputs (the academy app uses fcm; airnotifier is the Moodle app's). */
    const OUTPUTS = ['fcm', 'airnotifier'];

    /**
     * @return void
     */
    public static function enable(): void {
        global $DB;
        $outputs = array_values(array_filter(self::OUTPUTS,
            fn($name) => $DB->record_exists('message_processors', ['name' => $name])));
        if (!$outputs) {
            return;
        }
        foreach (self::PROVIDERS as $provider) {
            $base = 'local_nit_lessons_' . $provider;
            foreach ($outputs as $output) {
                set_config($output . '_provider_' . $base . '_locked', 0, 'message');
            }
            $name = 'message_provider_' . $base . '_enabled';
            $list = self::add((string) get_config('message', $name), $outputs);
            if ($list !== null) {
                set_config($name, $list, 'message');
            }
            $rs = $DB->get_recordset('user_preferences', ['name' => $name], '', 'id, userid, value');
            foreach ($rs as $pref) {
                $list = self::add((string) $pref->value, $outputs);
                if ($list !== null) {
                    $DB->set_field('user_preferences', 'value', $list, ['id' => $pref->id]);
                    mark_user_preferences_changed($pref->userid);
                }
            }
            $rs->close();
        }
        \cache_helper::purge_by_definition('core', 'message_processors_enabled');
    }

    /**
     * Adds outputs to a comma-separated output list.
     *
     * @param string $list e.g. "popup,email"; "none" = the user turned this notification off
     * @param string[] $outputs
     * @return string|null the new list, or null when nothing changes
     */
    public static function add(string $list, array $outputs): ?string {
        if ($list === 'none') {
            return null;
        }
        $names = array_values(array_filter(explode(',', $list), 'strlen'));
        $missing = array_diff($outputs, $names);
        return $missing ? implode(',', array_merge($names, $missing)) : null;
    }
}
