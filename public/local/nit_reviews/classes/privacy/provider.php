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
use context_system;
use context;

/**
 * Privacy provider for local_nit_reviews (ratings of courses and teachers).
 *
 * A review lives in its course's context; a review of a teacher's private
 * lessons (courseid 0) lives in the system context.
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
            'teacherid'    => 'privacy:metadata:local_nit_reviews:teacherid',
            'userid'       => 'privacy:metadata:local_nit_reviews:userid',
            'rating'       => 'privacy:metadata:local_nit_reviews:rating',
            'review'       => 'privacy:metadata:local_nit_reviews:review',
            'status'       => 'privacy:metadata:local_nit_reviews:status',
            'rejectreason' => 'privacy:metadata:local_nit_reviews:rejectreason',
            'reviewedby'   => 'privacy:metadata:local_nit_reviews:reviewedby',
            'timecreated'  => 'privacy:metadata:local_nit_reviews:timecreated',
            'timemodified' => 'privacy:metadata:local_nit_reviews:timemodified',
        ], 'privacy:metadata:local_nit_reviews');
        return $collection;
    }

    /**
     * The courseid the reviews of a context are stored under, or null when the
     * context holds none (0 = the system context: private-lesson reviews).
     *
     * @param context $context
     * @return int|null
     */
    private static function courseid_of(context $context): ?int {
        if ($context instanceof context_course) {
            return (int) $context->instanceid;
        }
        if ($context instanceof context_system) {
            return 0;
        }
        return null;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_nit_reviews} r
               JOIN {context} ctx ON ctx.instanceid = r.courseid AND ctx.contextlevel = :courselevel
              WHERE r.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        if ($DB->record_exists('local_nit_reviews', ['userid' => $userid, 'courseid' => 0])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $courseid = self::courseid_of($userlist->get_context());
        if ($courseid === null) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_nit_reviews} WHERE courseid = :courseid",
            ['courseid' => $courseid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::courseid_of($context);
            if ($courseid === null) {
                continue;
            }
            $recs = $DB->get_records('local_nit_reviews', ['courseid' => $courseid, 'userid' => $userid], 'id');
            foreach ($recs as $rec) {
                $teacher = (int) $rec->teacherid ? \core_user::get_user((int) $rec->teacherid) : null;
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_nit_reviews'), (string) $rec->id],
                    (object) [
                        'teacher' => $teacher ? fullname($teacher) : '',
                        'rating' => $rec->rating,
                        'review' => $rec->review,
                        'status' => $rec->status,
                        'rejectreason' => $rec->rejectreason,
                        'timecreated' => \core_privacy\local\request\transform::datetime($rec->timecreated),
                        'timemodified' => \core_privacy\local\request\transform::datetime($rec->timemodified),
                    ]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        $courseid = self::courseid_of($context);
        if ($courseid !== null) {
            $DB->delete_records('local_nit_reviews', ['courseid' => $courseid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::courseid_of($context);
            if ($courseid !== null) {
                $DB->delete_records('local_nit_reviews', ['courseid' => $courseid, 'userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $courseid = self::courseid_of($userlist->get_context());
        if ($courseid === null || !$userlist->get_userids()) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $courseid], $inparams);
        $DB->delete_records_select('local_nit_reviews', "courseid = :courseid AND userid $insql", $params);
    }
}
