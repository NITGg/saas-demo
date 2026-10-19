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

namespace mod_games\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_games.
 *
 * The rounds a child plays are recorded twice, in two places, for two reasons -
 * once by the corner as their lifetime standing, and once here as what happened
 * inside one course module. This provider answers only for the second: the
 * corner has its own provider for the first, and a request against a course
 * should not reach into a child's site-wide play history.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe what this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('games_play', [
            'userid'       => 'privacy:metadata:play:userid',
            'plays'        => 'privacy:metadata:play:plays',
            'points'       => 'privacy:metadata:play:points',
            'bestscore'    => 'privacy:metadata:play:bestscore',
            'beststreak'   => 'privacy:metadata:play:beststreak',
            'timemodified' => 'privacy:metadata:play:timemodified',
        ], 'privacy:metadata:play');

        return $collection;
    }

    /**
     * The contexts holding data for this user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $contextlist->add_from_sql('
                SELECT ctx.id
                  FROM {games_play} gp
                  JOIN {games} g ON g.id = gp.gamesid
                  JOIN {course_modules} cm ON cm.instance = g.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE gp.userid = :userid',
            ['modname' => 'games', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * The users with data in this context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $userlist->add_from_sql('userid', '
                SELECT gp.userid
                  FROM {games_play} gp
                  JOIN {games} g ON g.id = gp.gamesid
                  JOIN {course_modules} cm ON cm.instance = g.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid',
            ['modname' => 'games', 'cmid' => $context->instanceid]);
    }

    /**
     * Export this user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('games', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $record = $DB->get_record('games_play', ['gamesid' => $cm->instance, 'userid' => $userid]);
            if (!$record) {
                continue;
            }

            writer::with_context($context)->export_data([], (object) [
                'plays'        => (int) $record->plays,
                'points'       => (int) $record->points,
                'bestscore'    => (int) $record->bestscore,
                'beststreak'   => (int) $record->beststreak,
                'timemodified' => transform::datetime($record->timemodified),
            ]);
        }
    }

    /**
     * Delete everything stored for one activity.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('games', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('games_play', ['gamesid' => $cm->instance]);
    }

    /**
     * Delete everything stored for one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('games', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('games_play', ['gamesid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Delete the data of a named set of users in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('games', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['gamesid'] = $cm->instance;
        $DB->delete_records_select('games_play', "gamesid = :gamesid AND userid $insql", $params);
    }
}
