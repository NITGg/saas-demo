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

namespace mod_jobform;

/**
 * Reads and writes student submissions, and enforces the certificate gate.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_manager {

    /** @var string Draft, not yet sent. */
    const STATUS_DRAFT = 'draft';
    /** @var string Sent to the admin. */
    const STATUS_SUBMITTED = 'submitted';

    /**
     * Whether the student is allowed past the certificate gate.
     *
     * True when the activity has no linked certificate, or when the student has
     * been issued the linked customcert (i.e. they finished the course).
     *
     * @param object $jobform the activity record (needs ->certid)
     * @param int $userid
     * @return bool
     */
    public static function has_certificate(object $jobform, int $userid): bool {
        global $DB;
        if (empty($jobform->certid)) {
            return true;
        }
        // Guard in case the customcert module is not present.
        if (!$DB->get_manager()->table_exists('customcert_issues')) {
            return false;
        }
        return $DB->record_exists('customcert_issues',
            ['customcertid' => $jobform->certid, 'userid' => $userid]);
    }

    /**
     * Fetch a student's submission, or false.
     *
     * @param int $jobformid
     * @param int $userid
     * @return object|false
     */
    public static function get_submission(int $jobformid, int $userid) {
        global $DB;
        return $DB->get_record('jobform_submission',
            ['jobformid' => $jobformid, 'userid' => $userid]);
    }

    /**
     * Permanently delete a submission (and its answers) belonging to this activity.
     *
     * @param int $submissionid
     * @param int $jobformid guard so only this activity's submissions can be deleted
     * @return void
     */
    public static function delete_submission(int $submissionid, int $jobformid): void {
        global $DB;
        if (!$DB->record_exists('jobform_submission',
                ['id' => $submissionid, 'jobformid' => $jobformid])) {
            return;
        }
        $DB->delete_records('jobform_submission_data', ['submissionid' => $submissionid]);
        $DB->delete_records('jobform_submission', ['id' => $submissionid]);
    }

    /**
     * One page of an activity's submissions, newest first, with the student's name fields.
     *
     * @param int $jobformid
     * @param string $status STATUS_SUBMITTED (what the teacher's Submissions tab lists),
     *                       STATUS_DRAFT, or 'all'
     * @param int $page 0-based
     * @param int $perpage 0 = no limit
     * @return array{total: int, rows: object[]} rows: id, userid, status, timecreated, timemodified + user name fields
     */
    public static function list_submissions(int $jobformid, string $status = self::STATUS_SUBMITTED,
            int $page = 0, int $perpage = 0): array {
        global $DB;
        $where = 's.jobformid = :jobformid';
        $params = ['jobformid' => $jobformid];
        if ($status !== 'all') {
            $where .= ' AND s.status = :status';
            $params['status'] = $status;
        }
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {jobform_submission} s JOIN {user} u ON u.id = s.userid WHERE $where", $params);
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT s.id, s.userid, s.status, s.timecreated, s.timemodified, u.picture, u.imagealt, u.email,
                    $userfields
               FROM {jobform_submission} s
               JOIN {user} u ON u.id = s.userid
              WHERE $where
           ORDER BY s.timemodified DESC, s.id DESC",
            $params, $perpage > 0 ? $page * $perpage : 0, $perpage > 0 ? $perpage : 0);
        return ['total' => $total, 'rows' => array_values($rows)];
    }

    /**
     * A submission's answers against the activity's current fields (the same view
     * as view_submission.php): one row per field, in field order, with the label
     * and the display value in the current language.
     *
     * @param int $jobformid
     * @param int $submissionid
     * @return array[] [{fieldid, name, type, groupid, value, display}]
     */
    public static function get_answer_rows(int $jobformid, int $submissionid): array {
        $answers = self::get_answers($submissionid);
        $rows = [];
        foreach (instance_manager::get_fields($jobformid) as $field) {
            $value = $answers[$field->id] ?? '';
            $rows[] = [
                'fieldid' => (int) $field->id,
                'name'    => \local_jobform\mlang::resolve($field->name),
                'type'    => (string) $field->type,
                'groupid' => (int) $field->groupid,
                'value'   => (string) $value,
                'display' => \local_jobform\field_types::format_value($field, (string) $value),
            ];
        }
        return $rows;
    }

    /**
     * Get the stored answers for a submission as fieldid => value.
     *
     * @param int $submissionid
     * @return array
     */
    public static function get_answers(int $submissionid): array {
        global $DB;
        $records = $DB->get_records('jobform_submission_data', ['submissionid' => $submissionid]);
        $answers = [];
        foreach ($records as $r) {
            $answers[$r->fieldid] = $r->value;
        }
        return $answers;
    }

    /**
     * Create or update a submission and replace its answers.
     *
     * @param int $jobformid
     * @param int $userid
     * @param array $values fieldid => value (already normalised by the caller)
     * @param string $status STATUS_DRAFT or STATUS_SUBMITTED
     * @return int submission id
     */
    public static function save(int $jobformid, int $userid, array $values, string $status): int {
        global $DB;

        $now = time();
        $submission = self::get_submission($jobformid, $userid);
        if ($submission) {
            $submission->status = $status;
            $submission->timemodified = $now;
            $DB->update_record('jobform_submission', $submission);
        } else {
            $submission = new \stdClass();
            $submission->jobformid = $jobformid;
            $submission->userid = $userid;
            $submission->status = $status;
            $submission->timecreated = $now;
            $submission->timemodified = $now;
            $submission->id = $DB->insert_record('jobform_submission', $submission);
        }

        // Replace answers wholesale — simplest and safe for this volume.
        $DB->delete_records('jobform_submission_data', ['submissionid' => $submission->id]);
        foreach ($values as $fieldid => $value) {
            $record = new \stdClass();
            $record->submissionid = $submission->id;
            $record->fieldid = (int) $fieldid;
            $record->value = $value;
            $DB->insert_record('jobform_submission_data', $record);
        }

        return (int) $submission->id;
    }
}
