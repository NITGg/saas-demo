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

namespace local_jobform;

use moodle_url;
use html_writer;
use html_table;

/**
 * Reads and renders the submitted Job Forms across the whole site.
 *
 * Submissions are owned by mod_jobform. This class reads those tables when the
 * activity module is installed, and degrades gracefully when it is not.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submissions_ui {

    /**
     * Whether the mod_jobform submission tables exist yet.
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('jobform_submission');
    }

    /**
     * Fetch every submitted form, newest first.
     *
     * @return array rows with submission, course and user metadata
     */
    public static function get_all_submissions(): array {
        global $DB;
        if (!self::available()) {
            return [];
        }
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT s.id, s.jobformid, s.userid, s.status, s.timemodified, s.timecreated,
                       j.name AS formname, j.course AS courseid,
                       c.fullname AS coursename, $userfields
                  FROM {jobform_submission} s
                  JOIN {jobform} j ON j.id = s.jobformid
                  JOIN {course} c ON c.id = j.course
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.status = :submitted
              ORDER BY s.timemodified DESC";
        return $DB->get_records_sql($sql, ['submitted' => 'submitted']);
    }

    /**
     * Render the submissions table for the admin Submissions tab.
     *
     * @param moodle_url $viewurl page that shows one submission (gets ?submissionid=)
     * @param moodle_url|null $actionurl page that handles delete (gets ?submissionaction=delete&submissionid=&sesskey=)
     * @return string HTML
     */
    public static function render(moodle_url $viewurl, ?moodle_url $actionurl = null): string {
        if (!self::available()) {
            return html_writer::div(
                get_string('modnotinstalled', 'local_jobform'), 'alert alert-warning');
        }

        $rows = self::get_all_submissions();
        if (!$rows) {
            return html_writer::div(get_string('nosubmissions', 'local_jobform'), 'alert alert-info');
        }

        $table = new html_table();
        $table->head = [
            get_string('student', 'local_jobform'),
            get_string('course'),
            get_string('jobform', 'local_jobform'),
            get_string('submittedon', 'local_jobform'),
            get_string('actions', 'local_jobform'),
        ];
        $table->attributes['class'] = 'generaltable local-jobform-submissions-table';

        foreach ($rows as $row) {
            $view = new moodle_url($viewurl, ['submissionid' => $row->id]);
            $actions = html_writer::link($view, get_string('view'),
                ['class' => 'btn btn-sm btn-secondary']);
            if ($actionurl) {
                $delete = new moodle_url($actionurl,
                    ['submissionaction' => 'delete', 'submissionid' => $row->id, 'sesskey' => sesskey()]);
                $actions .= ' ' . html_writer::link($delete, get_string('delete'),
                    ['class' => 'btn btn-sm btn-outline-danger']);
            }
            $table->data[] = [
                fullname($row),
                format_string($row->coursename),
                format_string($row->formname),
                userdate($row->timemodified),
                $actions,
            ];
        }

        return html_writer::table($table);
    }

    /**
     * Permanently delete a submitted form and its answers.
     *
     * @param int $submissionid
     * @return void
     */
    public static function delete_submission(int $submissionid): void {
        global $DB;
        if (!self::available()) {
            return;
        }
        $DB->delete_records('jobform_submission_data', ['submissionid' => $submissionid]);
        $DB->delete_records('jobform_submission', ['id' => $submissionid]);
    }

    /**
     * Load a single submission with its answers, for the detail view.
     *
     * @param int $submissionid
     * @return array{submission: object, answers: object[]}|null
     */
    public static function get_submission(int $submissionid): ?array {
        global $DB;
        if (!self::available()) {
            return null;
        }
        $submission = $DB->get_record('jobform_submission', ['id' => $submissionid]);
        if (!$submission) {
            return null;
        }
        // Answers joined to the activity's field definitions (for type-aware formatting).
        $sql = "SELECT d.id, d.fieldid, d.value, f.name AS fieldname, f.type, f.configdata, f.sortorder
                  FROM {jobform_submission_data} d
             LEFT JOIN {jobform_field} f ON f.id = d.fieldid
                 WHERE d.submissionid = :sid
              ORDER BY f.sortorder ASC, d.id ASC";
        $answers = $DB->get_records_sql($sql, ['sid' => $submissionid]);
        return ['submission' => $submission, 'answers' => $answers];
    }
}
