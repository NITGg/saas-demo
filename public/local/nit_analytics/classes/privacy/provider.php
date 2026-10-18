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
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_nit_analytics\conversions;
use local_nit_analytics\tracker;

/**
 * Privacy provider: sign-up attribution (user preferences), recorded purchases (system context),
 * and the external trackers. Landings hold no personal data.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\user_preference_provider {

    public static function get_metadata(collection $collection): collection {
        foreach (tracker::UTM_KEYS as $key) {
            $collection->add_user_preference(tracker::PREF_PREFIX . $key, 'privacy:metadata:preference:' . $key);
        }
        $collection->add_user_preference(tracker::PREF_PENDING, 'privacy:metadata:preference:signup_pending');
        $collection->add_user_preference(conversions::PREF_PENDING, 'privacy:metadata:preference:purchase_pending');
        $collection->add_database_table(conversions::TABLE, [
            'userid' => 'privacy:metadata:conv:userid',
            'kind' => 'privacy:metadata:conv:kind',
            'amount_minor' => 'privacy:metadata:conv:amount_minor',
            'currency' => 'privacy:metadata:conv:currency',
            'timecreated' => 'privacy:metadata:conv:timecreated',
        ], 'privacy:metadata:conv');
        $collection->add_external_location_link('trackers', [
            'pageurl' => 'privacy:metadata:trackers:pageurl',
        ], 'privacy:metadata:trackers');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql("SELECT ctx.id FROM {context} ctx
                              WHERE ctx.contextlevel = :lvl
                                AND EXISTS (SELECT 1 FROM {" . conversions::TABLE . "} c WHERE c.userid = :uid)",
            ['lvl' => CONTEXT_SYSTEM, 'uid' => $userid]);
        return $list;
    }

    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . conversions::TABLE . '}', []);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $rows = [];
            foreach ($DB->get_records(conversions::TABLE, ['userid' => $userid], 'timecreated') as $r) {
                $rows[] = ['kind' => $r->kind, 'amount' => number_format($r->amount_minor / 100, 2) . ' ' . $r->currency,
                    'time' => transform::datetime($r->timecreated)];
            }
            if ($rows) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_nit_analytics')],
                    (object) ['purchases' => $rows]);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records(conversions::TABLE);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records(conversions::TABLE, ['userid' => (int) $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM || !$userlist->get_userids()) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(conversions::TABLE, "userid $in", $params);
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
