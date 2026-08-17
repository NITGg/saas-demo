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
 * Admin detail view of a single submitted Job Form.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\submissions_ui;
use local_jobform\field_types;
use local_jobform\mlang;

require_login();
$context = context_system::instance();
require_capability('local/jobform:manage', $context);

$submissionid = required_param('submissionid', PARAM_INT);

$submissionsurl = new moodle_url('/local/jobform/manage.php', ['tab' => 'submissions']);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobform/submission.php', ['submissionid' => $submissionid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('viewsubmission', 'local_jobform'));
$PAGE->set_heading(get_string('viewsubmission', 'local_jobform'));
$PAGE->navbar->add(get_string('managejobform', 'local_jobform'), $submissionsurl);
$PAGE->navbar->add(get_string('viewsubmission', 'local_jobform'));

$data = submissions_ui::get_submission($submissionid);
if ($data === null) {
    throw new moodle_exception('invalidsubmission', 'local_jobform');
}

$submission = $data['submission'];
$user = core_user::get_user($submission->userid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewsubmission', 'local_jobform'));

// Submission meta.
$meta = new html_table();
$meta->attributes['class'] = 'generaltable';
$meta->data[] = [get_string('student', 'local_jobform'), $user ? fullname($user) : $submission->userid];
$meta->data[] = [get_string('submittedon', 'local_jobform'), userdate($submission->timemodified)];
echo html_writer::table($meta);

// Answers.
echo $OUTPUT->heading(get_string('answers', 'local_jobform'), 4);
if (!$data['answers']) {
    echo html_writer::div(get_string('noanswers', 'local_jobform'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [get_string('fieldname', 'local_jobform'), get_string('answer', 'local_jobform')];
    $table->attributes['class'] = 'generaltable';
    foreach ($data['answers'] as $answer) {
        // The joined field record may be gone if it was deleted after submitting.
        $fieldrec = (object) [
            'type'       => $answer->type ?? field_types::TYPE_TEXT,
            'configdata' => $answer->configdata ?? null,
        ];
        $label = $answer->fieldname !== null ? mlang::display($answer->fieldname)
            : get_string('deletedfield', 'local_jobform');
        $table->data[] = [$label, s(field_types::format_value($fieldrec, $answer->value))];
    }
    echo html_writer::table($table);
}

echo html_writer::div(
    html_writer::link($submissionsurl, get_string('back'), ['class' => 'btn btn-secondary']),
    'mt-3'
);

echo $OUTPUT->footer();
