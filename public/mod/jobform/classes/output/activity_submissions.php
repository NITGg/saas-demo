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

namespace mod_jobform\output;

use moodle_url;
use html_writer;
use html_table;

/**
 * Renders the submissions list for a single Job Form activity (teacher view).
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_submissions {

    /**
     * Render the submissions table for one activity.
     *
     * @param \cm_info|object $cm the course module
     * @param object $jobform the activity record
     * @param bool $candelete whether to show a delete action per row
     * @return string HTML
     */
    public static function render($cm, object $jobform, bool $candelete = false): string {
        global $DB;

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT s.id, s.userid, s.status, s.timemodified, $userfields
                  FROM {jobform_submission} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.jobformid = :jobformid AND s.status = :submitted
              ORDER BY s.timemodified DESC";
        $rows = $DB->get_records_sql($sql,
            ['jobformid' => $jobform->id, 'submitted' => 'submitted']);

        if (!$rows) {
            return html_writer::div(get_string('nosubmissions', 'local_jobform'), 'alert alert-info');
        }

        $table = new html_table();
        $table->head = [
            get_string('student', 'local_jobform'),
            get_string('submittedon', 'local_jobform'),
            get_string('actions', 'local_jobform'),
        ];
        $table->attributes['class'] = 'generaltable';

        $submissionsurl = new moodle_url('/mod/jobform/view.php',
            ['id' => $cm->id, 'tab' => 'submissions']);
        foreach ($rows as $row) {
            $view = new moodle_url('/mod/jobform/view_submission.php',
                ['id' => $cm->id, 'submissionid' => $row->id]);
            $actions = html_writer::link($view, get_string('view'),
                ['class' => 'btn btn-sm btn-secondary']);
            if ($candelete) {
                $delete = new moodle_url($submissionsurl,
                    ['submissionaction' => 'delete', 'submissionid' => $row->id, 'sesskey' => sesskey()]);
                $actions .= ' ' . html_writer::link($delete, get_string('delete'),
                    ['class' => 'btn btn-sm btn-outline-danger']);
            }
            $table->data[] = [
                fullname($row),
                userdate($row->timemodified),
                $actions,
            ];
        }

        return html_writer::table($table);
    }
}
