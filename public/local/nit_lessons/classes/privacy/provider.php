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

namespace local_nit_lessons\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_nit_lessons. Lessons (student + teacher) and proposals live at the
 * system context.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('nit_lesson', [
            'studentid'   => 'privacy:metadata:nit_lesson:studentid',
            'teacherid'   => 'privacy:metadata:nit_lesson:teacherid',
            'note'        => 'privacy:metadata:nit_lesson:note',
            'timecreated' => 'privacy:metadata:nit_lesson:timecreated',
        ], 'privacy:metadata:nit_lesson');
        $collection->add_database_table('nit_lesson_proposal', [
            'proposedby'    => 'privacy:metadata:nit_lesson_proposal:proposedby',
            'proposed_time' => 'privacy:metadata:nit_lesson_proposal:proposed_time',
            'timecreated'   => 'privacy:metadata:nit_lesson_proposal:timecreated',
        ], 'privacy:metadata:nit_lesson_proposal');
        return $collection;
    }

    /**
     * Contexts holding data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $exists = $DB->record_exists_select('nit_lesson', 'studentid = :s OR teacherid = :t',
                ['s' => $userid, 't' => $userid])
            || $DB->record_exists('nit_lesson_proposal', ['proposedby' => $userid]);
        if ($exists) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users in a context (system only).
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('studentid', "SELECT studentid FROM {nit_lesson}", []);
        $userlist->add_from_sql('teacherid', "SELECT teacherid FROM {nit_lesson}", []);
        $userlist->add_from_sql('proposedby', "SELECT proposedby FROM {nit_lesson_proposal}", []);
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();
        $lessons = $DB->get_records_select('nit_lesson', 'studentid = :s OR teacherid = :t',
            ['s' => $userid, 't' => $userid]);
        if ($lessons) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_nit_lessons'), 'lessons'],
                (object) ['records' => array_values($lessons)]);
        }
        $proposals = $DB->get_records('nit_lesson_proposal', ['proposedby' => $userid]);
        if ($proposals) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_nit_lessons'), 'proposals'],
                (object) ['records' => array_values($proposals)]);
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('nit_lesson_proposal');
        $DB->delete_records('nit_lesson');
    }

    /**
     * Delete a single user's data. Lessons involving a counterparty are retained but the requesting
     * user's note is cleared; proposals authored by the user are removed.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('nit_lesson_proposal', ['proposedby' => $userid]);
        $DB->set_field('nit_lesson', 'note', null, ['studentid' => $userid]);
    }

    /**
     * Delete data for several users in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('nit_lesson_proposal', "proposedby $insql", $params);
    }

    /**
     * Whether the approved contextlist includes the system context.
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                return true;
            }
        }
        return false;
    }
}
