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

namespace local_games\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the Games Corner.
 *
 * The corner stores children's play data, so this is not optional paperwork:
 * a parent asking for an export or a deletion has to get a real answer.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe what we store.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_games_progress', [
            'userid'       => 'privacy:metadata:progress:userid',
            'gameid'       => 'privacy:metadata:progress:gameid',
            'points'       => 'privacy:metadata:progress:points',
            'plays'        => 'privacy:metadata:progress:plays',
            'bestscore'    => 'privacy:metadata:progress:bestscore',
            'beststreak'   => 'privacy:metadata:progress:beststreak',
            'timemodified' => 'privacy:metadata:progress:timemodified',
        ], 'privacy:metadata:progress');

        $collection->add_database_table('local_games_badge', [
            'userid'      => 'privacy:metadata:badge:userid',
            'gameid'      => 'privacy:metadata:badge:gameid',
            'badge'       => 'privacy:metadata:badge:badge',
            'timeawarded' => 'privacy:metadata:badge:timeawarded',
        ], 'privacy:metadata:badge');

        return $collection;
    }

    /**
     * Contexts holding data for a user. Everything here is site level.
     *
     * @param int $userid
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        global $DB;

        $contextlist = new \core_privacy\local\request\contextlist();

        $has = $DB->record_exists('local_games_progress', ['userid' => $userid])
            || $DB->record_exists('local_games_badge', ['userid' => $userid]);

        if ($has) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_games_progress}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_games_badge}', []);
    }

    /**
     * Export a user's play data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $userid = $contextlist->get_user()->id;

            $data = (object) [
                'progress' => array_values($DB->get_records('local_games_progress', ['userid' => $userid])),
                'badges'   => array_values($DB->get_records('local_games_badge', ['userid' => $userid])),
            ];

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_games')],
                $data
            );
        }
    }

    /**
     * Delete every user's data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_games_progress');
        $DB->delete_records('local_games_badge');
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $userid = $contextlist->get_user()->id;
            $DB->delete_records('local_games_progress', ['userid' => $userid]);
            $DB->delete_records('local_games_badge', ['userid' => $userid]);
        }
    }

    /**
     * Delete data for a list of users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_games_progress', "userid $insql", $params);
        $DB->delete_records_select('local_games_badge', "userid $insql", $params);
    }
}
