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

namespace local_nit_notifications\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the notifications a user sent and the ones they received
 * (the log). All of it lives in the system context; the messages themselves are
 * core notifications, covered by core_message.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_nit_notif', [
            'senderid' => 'privacy:metadata:local_nit_notif:senderid',
            'title' => 'privacy:metadata:local_nit_notif:title',
            'body' => 'privacy:metadata:local_nit_notif:body',
            'timecreated' => 'privacy:metadata:local_nit_notif:timecreated',
        ], 'privacy:metadata:local_nit_notif');
        $collection->add_database_table('local_nit_notif_rcpt', [
            'userid' => 'privacy:metadata:local_nit_notif_rcpt:userid',
            'status' => 'privacy:metadata:local_nit_notif_rcpt:status',
            'timesent' => 'privacy:metadata:local_nit_notif_rcpt:timesent',
        ], 'privacy:metadata:local_nit_notif_rcpt');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_nit_notif', ['senderid' => $userid])
                || $DB->record_exists('local_nit_notif_rcpt', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('senderid', 'SELECT senderid FROM {local_nit_notif} WHERE senderid > 0', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_nit_notif_rcpt}', []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $sent = $DB->get_records('local_nit_notif', ['senderid' => $userid], 'id');
            $received = $DB->get_records_sql("SELECT r.id, r.status, r.timesent, n.title, n.body
                                                FROM {local_nit_notif_rcpt} r
                                                JOIN {local_nit_notif} n ON n.id = r.notifid
                                               WHERE r.userid = ?", [$userid]);
            $writer = writer::with_context($context);
            $writer->export_data([get_string('pluginname', 'local_nit_notifications'), 'sent'], (object) [
                'notifications' => array_values(array_map(fn($n) => [
                    'title' => $n->title, 'body' => $n->body, 'audience' => $n->audience,
                    'timecreated' => transform::datetime($n->timecreated)], $sent)),
            ]);
            $writer->export_data([get_string('pluginname', 'local_nit_notifications'), 'received'], (object) [
                'notifications' => array_values(array_map(fn($r) => [
                    'title' => $r->title, 'body' => $r->body, 'status' => $r->status,
                    'timesent' => $r->timesent ? transform::datetime($r->timesent) : ''], $received)),
            ]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records('local_nit_notif_rcpt');
            $DB->delete_records('local_nit_notif');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::forget_users([(int) $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context() instanceof \context_system) {
            self::forget_users(array_map('intval', $userlist->get_userids()));
        }
    }

    /**
     * Drop the users' recipient rows and detach them as senders (the notification
     * stays in the log for the other recipients, sent by "the system").
     *
     * @param int[] $userids
     */
    private static function forget_users(array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $DB->delete_records_select('local_nit_notif_rcpt', "userid $insql", $params);
        $DB->set_field_select('local_nit_notif', 'senderid', 0, "senderid $insql", $params);
    }
}
