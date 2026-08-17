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

namespace mod_jobform\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\helper;
use context_module;

/**
 * Privacy provider for mod_jobform: student submissions and their answers.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('jobform_submission', [
            'jobformid'    => 'privacy:metadata:jobform_submission:jobformid',
            'userid'       => 'privacy:metadata:jobform_submission:userid',
            'status'       => 'privacy:metadata:jobform_submission:status',
            'timemodified' => 'privacy:metadata:jobform_submission:timemodified',
        ], 'privacy:metadata:jobform_submission');

        $collection->add_database_table('jobform_submission_data', [
            'value' => 'privacy:metadata:jobform_submission_data:value',
        ], 'privacy:metadata:jobform_submission_data');

        return $collection;
    }

    /**
     * Contexts that contain the given user's submissions.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {jobform_submission} s
                  JOIN {jobform} j ON j.id = s.jobformid
                  JOIN {course_modules} cm ON cm.instance = j.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'modname'  => 'jobform',
            'modlevel' => CONTEXT_MODULE,
            'userid'   => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Users within a given module context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $sql = "SELECT s.userid
                  FROM {jobform_submission} s
                  JOIN {jobform} j ON j.id = s.jobformid
                  JOIN {course_modules} cm ON cm.instance = j.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'jobform',
            'cmid'    => $context->instanceid,
        ]);
    }

    /**
     * Export all submissions for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('jobform', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $submission = $DB->get_record('jobform_submission',
                ['jobformid' => $cm->instance, 'userid' => $userid]);
            if (!$submission) {
                continue;
            }

            $answers = [];
            $rows = $DB->get_records_sql(
                "SELECT d.id, d.value, f.name AS fieldname
                   FROM {jobform_submission_data} d
              LEFT JOIN {jobform_field} f ON f.id = d.fieldid
                  WHERE d.submissionid = :sid",
                ['sid' => $submission->id]);
            foreach ($rows as $row) {
                $answers[] = ['field' => $row->fieldname, 'value' => $row->value];
            }

            $data = (object) [
                'status'       => $submission->status,
                'timemodified' => \core_privacy\local\request\transform::datetime($submission->timemodified),
                'answers'      => $answers,
            ];
            $context_data = helper::get_context_data($context, $contextlist->get_user());
            writer::with_context($context)->export_data([], $context_data);
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'mod_jobform')], $data);
        }
    }

    /**
     * Delete all submissions in a context (all users).
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('jobform', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_submissions($DB->get_fieldset_select(
            'jobform_submission', 'id', 'jobformid = ?', [$cm->instance]));
    }

    /**
     * Delete a specific user's submissions across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('jobform', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_submissions($DB->get_fieldset_select(
                'jobform_submission', 'id', 'jobformid = ? AND userid = ?', [$cm->instance, $userid]));
        }
    }

    /**
     * Delete the given users' submissions in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('jobform', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $params[] = $cm->instance;
        self::delete_submissions($DB->get_fieldset_select(
            'jobform_submission', 'id', "userid $insql AND jobformid = ?", $params));
    }

    /**
     * Delete a set of submissions and their answers.
     *
     * @param int[] $submissionids
     * @return void
     */
    protected static function delete_submissions(array $submissionids): void {
        global $DB;
        if (!$submissionids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        $DB->delete_records_select('jobform_submission_data', "submissionid $insql", $params);
        $DB->delete_records_select('jobform_submission', "id $insql", $params);
    }
}
