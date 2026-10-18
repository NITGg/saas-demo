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

namespace local_nit_analytics\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use local_nit_analytics\tracker;

/**
 * Privacy provider: the sign-up attribution kept as user preferences, and the external trackers.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {

    public static function get_metadata(collection $collection): collection {
        foreach (tracker::UTM_KEYS as $key) {
            $collection->add_user_preference(tracker::PREF_PREFIX . $key, 'privacy:metadata:preference:' . $key);
        }
        $collection->add_user_preference(tracker::PREF_PENDING, 'privacy:metadata:preference:signup_pending');
        $collection->add_external_location_link('trackers', [
            'pageurl' => 'privacy:metadata:trackers:pageurl',
        ], 'privacy:metadata:trackers');
        return $collection;
    }

    /**
     * Export the user's attribution preferences.
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        foreach (tracker::UTM_KEYS as $key) {
            $value = get_user_preferences(tracker::PREF_PREFIX . $key, null, $userid);
            if ($value !== null) {
                writer::export_user_preference('local_nit_analytics', tracker::PREF_PREFIX . $key, $value,
                    get_string('privacy:metadata:preference:' . $key, 'local_nit_analytics'));
            }
        }
        $pending = get_user_preferences(tracker::PREF_PENDING, null, $userid);
        if ($pending !== null) {
            writer::export_user_preference('local_nit_analytics', tracker::PREF_PENDING, transform::yesno($pending),
                get_string('privacy:metadata:preference:signup_pending', 'local_nit_analytics'));
        }
    }
}
