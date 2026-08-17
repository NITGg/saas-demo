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

/**
 * Teacher view of a single student's submitted Job Form.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\field_types;
use local_jobform\mlang;
use mod_jobform\instance_manager;
use mod_jobform\submission_manager;

$cmid = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jobform');
$jobform = $DB->get_record('jobform', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jobform:viewsubmissions', $context);

$submission = $DB->get_record('jobform_submission',
    ['id' => $submissionid, 'jobformid' => $jobform->id], '*', MUST_EXIST);

$submissionsurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'submissions']);

$PAGE->set_url(new moodle_url('/mod/jobform/view_submission.php',
    ['id' => $cm->id, 'submissionid' => $submissionid]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($jobform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('tabsubmissions', 'local_jobform'), $submissionsurl);
$PAGE->navbar->add(get_string('viewsubmission', 'local_jobform'));

$user = core_user::get_user($submission->userid);
$fields = instance_manager::get_fields($jobform->id);
$answers = submission_manager::get_answers($submissionid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewsubmission', 'local_jobform'));

$meta = new html_table();
$meta->attributes['class'] = 'generaltable';
$meta->data[] = [get_string('student', 'local_jobform'), $user ? fullname($user) : $submission->userid];
$meta->data[] = [get_string('submittedon', 'local_jobform'), userdate($submission->timemodified)];
echo html_writer::table($meta);

echo $OUTPUT->heading(get_string('answers', 'local_jobform'), 4);
if (!$fields) {
    echo html_writer::div(get_string('noanswers', 'local_jobform'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [get_string('fieldname', 'local_jobform'), get_string('answer', 'local_jobform')];
    $table->attributes['class'] = 'generaltable';
    foreach ($fields as $field) {
        $value = $answers[$field->id] ?? '';
        $table->data[] = [
            mlang::display($field->name),
            s(field_types::format_value($field, $value)),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::div(
    html_writer::link($submissionsurl, get_string('back'), ['class' => 'btn btn-secondary']),
    'mt-3'
);

echo $OUTPUT->footer();
