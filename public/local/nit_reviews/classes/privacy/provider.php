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

namespace local_nit_reviews\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_course;
use context;

/**
 * Privacy provider for local_nit_reviews (course star ratings + reviews).
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_nit_reviews', [
            'courseid'     => 'privacy:metadata:local_nit_reviews:courseid',
            'userid'       => 'privacy:metadata:local_nit_reviews:userid',
            'rating'       => 'privacy:metadata:local_nit_reviews:rating',
            'review'       => 'privacy:metadata:local_nit_reviews:review',
            'timecreated'  => 'privacy:metadata:local_nit_reviews:timecreated',
            'timemodified' => 'privacy:metadata:local_nit_reviews:timemodified',
        ], 'privacy:metadata:local_nit_reviews');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_nit_reviews} r
               JOIN {context} ctx ON ctx.instanceid = r.courseid AND ctx.contextlevel = :courselevel
              WHERE r.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_nit_reviews} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $rec = $DB->get_record('local_nit_reviews', ['courseid' => $context->instanceid, 'userid' => $userid]);
            if ($rec) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_nit_reviews')],
                    (object) [
                        'rating' => $rec->rating,
                        'review' => $rec->review,
                        'timecreated' => \core_privacy\local\request\transform::datetime($rec->timecreated),
                        'timemodified' => \core_privacy\local\request\transform::datetime($rec->timemodified),
                    ]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context instanceof context_course) {
            $DB->delete_records('local_nit_reviews', ['courseid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $DB->delete_records('local_nit_reviews', ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $context->instanceid], $inparams);
        $DB->delete_records_select('local_nit_reviews', "courseid = :courseid AND userid $insql", $params);
    }
}
