<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API: a signed-in user's conversations, tool calls and cost rows live in
 * the system context; they can be exported and deleted.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * {@inheritDoc}
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_nit_asst_conv', [
            'userid' => 'privacy:metadata:conv:userid', 'lang' => 'privacy:metadata:conv:lang',
            'summary' => 'privacy:metadata:conv:summary', 'timecreated' => 'privacy:metadata:conv:timecreated',
        ], 'privacy:metadata:conv');
        $collection->add_database_table('local_nit_asst_msg', [
            'content' => 'privacy:metadata:msg:content', 'timecreated' => 'privacy:metadata:msg:timecreated',
        ], 'privacy:metadata:msg');
        $collection->add_database_table('local_nit_asst_audit', [
            'userid' => 'privacy:metadata:audit:userid', 'tool' => 'privacy:metadata:audit:tool',
        ], 'privacy:metadata:audit');
        $collection->add_database_table('local_nit_asst_event', [
            'userid' => 'privacy:metadata:event:userid', 'cost' => 'privacy:metadata:event:cost',
        ], 'privacy:metadata:event');
        $collection->add_database_table('local_nit_asst_lead', [
            'userid' => 'privacy:metadata:lead:userid', 'name' => 'privacy:metadata:lead:name',
            'phone' => 'privacy:metadata:lead:phone', 'email' => 'privacy:metadata:lead:email',
            'interest' => 'privacy:metadata:lead:interest',
        ], 'privacy:metadata:lead');
        $collection->add_database_table('local_nit_asst_ticket', [
            'userid' => 'privacy:metadata:ticket:userid', 'subject' => 'privacy:metadata:ticket:subject',
            'description' => 'privacy:metadata:ticket:description',
        ], 'privacy:metadata:ticket');
        $collection->add_database_table('local_nit_asst_followup', [
            'userid' => 'privacy:metadata:followup:userid', 'title' => 'privacy:metadata:followup:title',
            'body' => 'privacy:metadata:followup:body', 'timesent' => 'privacy:metadata:followup:timesent',
        ], 'privacy:metadata:followup');
        $collection->add_external_location_link('anthropic', [
            'messages' => 'privacy:metadata:anthropic:messages',
        ], 'privacy:metadata:anthropic');
        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if (
            $DB->record_exists('local_nit_asst_conv', ['userid' => $userid])
                || $DB->record_exists('local_nit_asst_audit', ['userid' => $userid])
                || $DB->record_exists('local_nit_asst_event', ['userid' => $userid])
                || $DB->record_exists('local_nit_asst_lead', ['userid' => $userid])
                || $DB->record_exists('local_nit_asst_ticket', ['userid' => $userid])
                || $DB->record_exists('local_nit_asst_followup', ['userid' => $userid])
        ) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach (
            ['local_nit_asst_conv', 'local_nit_asst_audit', 'local_nit_asst_event', 'local_nit_asst_lead',
                'local_nit_asst_ticket', 'local_nit_asst_followup'] as $table
        ) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE userid > 0", []);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            self::export_followups($context, $userid);
            foreach ($DB->get_records('local_nit_asst_conv', ['userid' => $userid], 'timecreated') as $conv) {
                [$insql, $params] = $DB->get_in_or_equal(['visitor', 'assistant', 'staff'], SQL_PARAMS_NAMED);
                $msgs = $DB->get_records_select(
                    'local_nit_asst_msg',
                    "convid = :convid AND status = 'sent' AND role {$insql}",
                    $params + ['convid' => $conv->id],
                    'timecreated, id',
                    'role, content, timecreated'
                );
                writer::with_context($context)->export_data(
                    [get_string('privacy:conversations', 'local_nit_assistant'), (string) $conv->id],
                    (object) [
                        'started' => \core_privacy\local\request\transform::datetime($conv->timecreated),
                        'language' => $conv->lang,
                        'summary' => $conv->summary,
                        'contact' => array_values(array_map(static fn($l) => [
                            'name' => $l->name, 'phone' => $l->phone, 'email' => $l->email, 'interest' => $l->interest,
                        ], $DB->get_records('local_nit_asst_lead', ['convid' => $conv->id]))),
                        'tickets' => array_values(array_map(static fn($t) => [
                            'number' => (int) $t->id, 'subject' => $t->subject, 'description' => $t->description,
                            'status' => $t->status,
                        ], $DB->get_records('local_nit_asst_ticket', ['convid' => $conv->id, 'userid' => $userid]))),
                        'messages' => array_values(array_map(static fn($m) => [
                            'from' => $m->role === 'visitor' ? 'you' : $m->role,
                            'text' => $m->content,
                            'time' => \core_privacy\local\request\transform::datetime($m->timecreated),
                        ], $msgs)),
                    ]
                );
            }
        }
    }

    /**
     * Export the follow-up messages sent to a user.
     *
     * @param \context $context
     * @param int $userid
     * @return void
     */
    private static function export_followups(\context $context, int $userid): void {
        global $DB;
        $sent = $DB->get_records('local_nit_asst_followup', ['userid' => $userid, 'status' => 'sent'], 'timesent');
        if ($sent) {
            writer::with_context($context)->export_data(
                [get_string('privacy:followups', 'local_nit_assistant')],
                (object) ['messages' => array_values(array_map(static fn($f) => [
                    'title' => $f->title, 'text' => $f->body,
                    'sent' => \core_privacy\local\request\transform::datetime($f->timesent),
                ], $sent))]
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_nit_asst_msg');
        $DB->delete_records('local_nit_asst_audit');
        $DB->delete_records('local_nit_asst_conv');
        $DB->delete_records('local_nit_asst_event');
        $DB->delete_records('local_nit_asst_lead');
        $DB->delete_records('local_nit_asst_ticket');
        $DB->delete_records('local_nit_asst_followup');
    }

    /**
     * {@inheritDoc}
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::delete_users([(int) $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context() instanceof \context_system) {
            self::delete_users(array_map('intval', $userlist->get_userids()));
        }
    }

    /**
     * Delete everything stored for these users.
     *
     * @param int[] $userids
     * @return void
     */
    private static function delete_users(array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $convids = $DB->get_fieldset_select('local_nit_asst_conv', 'id', "userid {$insql}", $params);
        if ($convids) {
            [$cin, $cparams] = $DB->get_in_or_equal($convids, SQL_PARAMS_NAMED, 'c');
            $DB->delete_records_select('local_nit_asst_msg', "convid {$cin}", $cparams);
            $DB->delete_records_select('local_nit_asst_audit', "convid {$cin}", $cparams);
            $DB->delete_records_select('local_nit_asst_lead', "convid {$cin}", $cparams);
            $DB->delete_records_select('local_nit_asst_ticket', "convid {$cin}", $cparams);
            $DB->delete_records_select('local_nit_asst_conv', "id {$cin}", $cparams);
        }
        $DB->delete_records_select('local_nit_asst_audit', "userid {$insql}", $params);
        $DB->delete_records_select('local_nit_asst_event', "userid {$insql}", $params);
        $DB->delete_records_select('local_nit_asst_lead', "userid {$insql}", $params);
        $DB->delete_records_select('local_nit_asst_ticket', "userid {$insql}", $params);
        $DB->delete_records_select('local_nit_asst_followup', "userid {$insql}", $params);
    }
}
