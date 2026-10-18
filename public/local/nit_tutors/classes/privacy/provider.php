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

namespace local_nit_tutors\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy: a teacher's application, video, qualifications and answers (all in their user context).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('nit_tutor', [
            'userid' => 'privacy:metadata:tutor:userid',
            'status' => 'privacy:metadata:tutor:status',
            'reviewnote' => 'privacy:metadata:tutor:reviewnote',
            'videourl' => 'privacy:metadata:tutor:videourl',
            'badge' => 'privacy:metadata:tutor:badge',
        ], 'privacy:metadata:tutor');
        $collection->add_database_table('nit_tutor_qual', [
            'userid' => 'privacy:metadata:qual:userid',
            'title' => 'privacy:metadata:qual:title',
            'institution' => 'privacy:metadata:qual:institution',
            'year' => 'privacy:metadata:qual:year',
        ], 'privacy:metadata:qual');
        $collection->add_database_table('nit_tutor_data', [
            'userid' => 'privacy:metadata:data:userid',
            'value' => 'privacy:metadata:data:value',
        ], 'privacy:metadata:data');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }

    /**
     * The teacher's user context when they have an application.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists('nit_tutor', ['userid' => $userid])
                || $DB->record_exists('nit_tutor_qual', ['userid' => $userid])) {
            $list->add_user_context($userid);
        }
        return $list;
    }

    /**
     * Users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof \context_user) {
            global $DB;
            if ($DB->record_exists('nit_tutor', ['userid' => $context->instanceid])) {
                $userlist->add_user($context->instanceid);
            }
        }
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || (int) $context->instanceid !== $userid) {
                continue;
            }
            $path = [get_string('pluginname', 'local_nit_tutors')];
            $data = (object) [
                'application' => $DB->get_record('nit_tutor', ['userid' => $userid]),
                'qualifications' => array_values($DB->get_records('nit_tutor_qual', ['userid' => $userid])),
                'answers' => array_values($DB->get_records('nit_tutor_data', ['userid' => $userid])),
            ];
            writer::with_context($context)->export_data($path, $data);
            foreach (['video', 'qualification', 'field'] as $area) {
                writer::with_context($context)->export_area_files($path, 'local_nit_tutors', $area, false);
            }
        }
    }

    /**
     * Delete everyone in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            self::delete_user((int) $context->instanceid);
        }
    }

    /**
     * Delete one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int) $context->instanceid === (int) $contextlist->get_user()->id) {
                self::delete_user((int) $context->instanceid);
            }
        }
    }

    /**
     * Delete users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof \context_user && in_array((int) $context->instanceid, $userlist->get_userids())) {
            self::delete_user((int) $context->instanceid);
        }
    }

    /**
     * Remove a teacher's data and files.
     *
     * @param int $userid
     */
    private static function delete_user(int $userid): void {
        global $DB;
        get_file_storage()->delete_area_files(\context_user::instance($userid)->id, 'local_nit_tutors');
        $DB->delete_records('nit_tutor_data', ['userid' => $userid]);
        $DB->delete_records('nit_tutor_qual', ['userid' => $userid]);
        $DB->delete_records('nit_tutor', ['userid' => $userid]);
    }
}
