<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_system;

/**
 * Privacy: the devices (browser / app install, IP, dates) of each account,
 * kept in the system context.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_nit_devices', [
            'userid' => 'privacy:metadata:userid',
            'kind' => 'privacy:metadata:kind',
            'name' => 'privacy:metadata:name',
            'platform' => 'privacy:metadata:platform',
            'ip' => 'privacy:metadata:ip',
            'timecreated' => 'privacy:metadata:timecreated',
            'lastseen' => 'privacy:metadata:lastseen',
        ], 'privacy:metadata:table');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_nit_devices', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context() instanceof context_system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_nit_devices}', []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }
            $devices = array_values(array_map(static fn($d) => [
                'kind' => $d->kind,
                'name' => $d->name,
                'platform' => $d->platform,
                'ip' => $d->ip,
                'timecreated' => \core_privacy\local\request\transform::datetime($d->timecreated),
                'lastseen' => \core_privacy\local\request\transform::datetime($d->lastseen),
            ], $DB->get_records('local_nit_devices', ['userid' => $userid])));
            writer::with_context($context)->export_data([get_string('devices', 'local_nit_devices')], (object) ['devices' => $devices]);
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('local_nit_devices');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_nit_devices', ['userid' => $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof context_system || !$userlist->get_userids()) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_nit_devices', "userid $sql", $params);
    }
}
