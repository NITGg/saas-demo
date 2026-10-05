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

namespace local_nit_flex\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_nit_flex. Personal data (purchases, payments, Flex ledger) lives at
 * the system context, keyed by userid.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /** @var string[] Tables holding per-user data, all keyed by userid. */
    private const TABLES = ['nit_package_purchase', 'nit_payment', 'nit_flex_tx'];

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('nit_package_purchase', [
            'userid'           => 'privacy:metadata:nit_package_purchase:userid',
            'price_paid_minor' => 'privacy:metadata:nit_package_purchase:price_paid_minor',
            'timecreated'      => 'privacy:metadata:nit_package_purchase:timecreated',
        ], 'privacy:metadata:nit_package_purchase');
        $collection->add_database_table('nit_payment', [
            'userid'       => 'privacy:metadata:nit_payment:userid',
            'amount_minor' => 'privacy:metadata:nit_payment:amount_minor',
            'timecreated'  => 'privacy:metadata:nit_payment:timecreated',
        ], 'privacy:metadata:nit_payment');
        $collection->add_database_table('nit_flex_tx', [
            'userid'      => 'privacy:metadata:nit_flex_tx:userid',
            'amount'      => 'privacy:metadata:nit_flex_tx:amount',
            'timecreated' => 'privacy:metadata:nit_flex_tx:timecreated',
        ], 'privacy:metadata:nit_flex_tx');
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
        foreach (self::TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
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
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {" . $table . "}", []);
        }
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
        foreach (self::TABLES as $table) {
            $records = $DB->get_records($table, ['userid' => $userid]);
            if ($records) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_nit_flex'), $table],
                    (object) ['records' => array_values($records)]);
            }
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
        foreach (self::TABLES as $table) {
            $DB->delete_records($table);
        }
    }

    /**
     * Delete a single user's data.
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
        foreach (self::TABLES as $table) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
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
        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "userid $insql", $params);
        }
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
