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

namespace local_nit_finance\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_nit_finance.
 *
 * Personal data lives at the system context: a teacher's earnings and withdrawal requests.
 *
 * @package    local_nit_finance
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
        $collection->add_database_table('nit_earning', [
            'teacherid'            => 'privacy:metadata:nit_earning:teacherid',
            'teacher_amount_minor' => 'privacy:metadata:nit_earning:teacher_amount_minor',
            'timecreated'          => 'privacy:metadata:nit_earning:timecreated',
        ], 'privacy:metadata:nit_earning');
        $collection->add_database_table('nit_withdrawal', [
            'teacherid'    => 'privacy:metadata:nit_withdrawal:teacherid',
            'amount_minor' => 'privacy:metadata:nit_withdrawal:amount_minor',
            'account'      => 'privacy:metadata:nit_withdrawal:account',
            'timecreated'  => 'privacy:metadata:nit_withdrawal:timecreated',
        ], 'privacy:metadata:nit_withdrawal');
        return $collection;
    }

    /**
     * Contexts holding data for a user (always the system context when they have any).
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT 1 FROM {nit_earning} WHERE teacherid = :t1
                 UNION SELECT 1 FROM {nit_withdrawal} WHERE teacherid = :t2";
        global $DB;
        if ($DB->record_exists_sql($sql, ['t1' => $userid, 't2' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users within a context (system context only).
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('teacherid', "SELECT teacherid FROM {nit_earning}", []);
        $userlist->add_from_sql('teacherid', "SELECT teacherid FROM {nit_withdrawal}", []);
    }

    /**
     * Export a user's finance data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(CONTEXT_SYSTEM, array_map(static fn($c) => $c->contextlevel, $contextlist->get_contexts()), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $earnings = $DB->get_records('nit_earning', ['teacherid' => $userid]);
        if ($earnings) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_nit_finance'), 'earnings'], (object) ['records' => array_values($earnings)]);
        }
        $withdrawals = $DB->get_records('nit_withdrawal', ['teacherid' => $userid]);
        if ($withdrawals) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_nit_finance'), 'withdrawals'], (object) ['records' => array_values($withdrawals)]);
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
        $DB->delete_records('nit_earning');
        $DB->delete_records('nit_withdrawal');
    }

    /**
     * Delete a single user's data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(CONTEXT_SYSTEM, array_map(static fn($c) => $c->contextlevel, $contextlist->get_contexts()), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('nit_earning', ['teacherid' => $userid]);
        $DB->delete_records('nit_withdrawal', ['teacherid' => $userid]);
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
        $DB->delete_records_select('nit_earning', "teacherid $insql", $params);
        $DB->delete_records_select('nit_withdrawal', "teacherid $insql", $params);
    }
}
