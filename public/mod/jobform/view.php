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
 * Displays a Job Form activity.
 *
 *  - Managers / teachers see the field editor (default) and a submissions tab.
 *  - Students see the form once they have earned the linked certificate.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\fields_ui;
use mod_jobform\instance_manager;
use mod_jobform\group_manager;
use mod_jobform\submission_manager;
use mod_jobform\form\entry_form;
use mod_jobform\output\activity_submissions;

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'jobform');
$jobform = $DB->get_record('jobform', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jobform:view', $context);

$tab = optional_param('tab', 'fields', PARAM_ALPHA);
$cansmanage = has_capability('mod/jobform:managefields', $context);

$PAGE->set_url(new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => $tab]));
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($jobform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($jobform);
$PAGE->add_body_class('mod-jobform');

$viewurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id]);
$fieldsurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'fields']);

// Log the view and update completion.
$event = \mod_jobform\event\course_module_viewed::create([
    'objectid' => $jobform->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('jobform', $jobform);
$event->trigger();
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// ---------------------------------------------------------------------------
// Manager / teacher field actions (delete / reorder), before any output.
// ---------------------------------------------------------------------------
if ($cansmanage) {
    $fieldaction = optional_param('fieldaction', '', PARAM_ALPHA);
    $fieldid = optional_param('fieldid', 0, PARAM_INT);
    $confirm = optional_param('confirm', 0, PARAM_BOOL);

    $groupaction = optional_param('groupaction', '', PARAM_ALPHA);
    $groupid = optional_param('groupid', 0, PARAM_INT);
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($fieldaction === 'moveup' && $fieldid > 0 && confirm_sesskey()) {
        instance_manager::reorder($fieldid, $jobform->id, -1);
        redirect($fieldsurl);
    } else if ($fieldaction === 'movedown' && $fieldid > 0 && confirm_sesskey()) {
        instance_manager::reorder($fieldid, $jobform->id, 1);
        redirect($fieldsurl);
    } else if ($fieldaction === 'delete' && $fieldid > 0 && confirm_sesskey() && $confirm) {
        instance_manager::delete_field($fieldid, $jobform->id);
        redirect($fieldsurl, get_string('changessaved'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else if ($groupaction === 'delete' && $groupid > 0 && confirm_sesskey() && $confirm) {
        group_manager::delete_group($groupid, $jobform->id);
        redirect($fieldsurl, get_string('changessaved'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'usedefault' && confirm_sesskey() && $confirm) {
        instance_manager::reset_to_template($jobform->id);
        redirect($fieldsurl, get_string('defaultfieldsapplied', 'mod_jobform'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ---------------------------------------------------------------------------
// Delete a submitted form from the Submissions tab (needs the submissions cap,
// which a non-editing teacher may hold without managefields).
// ---------------------------------------------------------------------------
$cansubmissions = has_capability('mod/jobform:viewsubmissions', $context);
$submissionsurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'submissions']);
if ($cansubmissions) {
    $submissionaction = optional_param('submissionaction', '', PARAM_ALPHA);
    $submissionid = optional_param('submissionid', 0, PARAM_INT);
    if ($submissionaction === 'delete' && $submissionid > 0 && confirm_sesskey()
            && optional_param('confirm', 0, PARAM_BOOL)) {
        submission_manager::delete_submission($submissionid, $jobform->id);
        redirect($submissionsurl, get_string('submissiondeleted', 'local_jobform'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ---------------------------------------------------------------------------
// Student form submission handling, before output.
// ---------------------------------------------------------------------------
$studentmode = !$cansmanage && has_capability('mod/jobform:submit', $context);
$fields = instance_manager::get_fields($jobform->id);
$groups = group_manager::get_groups($jobform->id);
$entryform = null;

if ($studentmode) {
    $gated = !submission_manager::has_certificate($jobform, $USER->id);
    $existing = submission_manager::get_submission($jobform->id, $USER->id);
    $locked = $existing && $existing->status === submission_manager::STATUS_SUBMITTED
        && empty($jobform->allowresubmit);

    if (!$gated && !$locked) {
        $entryform = new entry_form($viewurl,
            ['fields' => array_values($fields), 'groups' => $groups]);

        if ($entryform->is_cancelled()) {
            redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
        } else if ($data = $entryform->get_data()) {
            $status = !empty($data->savedraft)
                ? submission_manager::STATUS_DRAFT
                : submission_manager::STATUS_SUBMITTED;
            $values = entry_form::normalize_values($data, array_values($fields));
            submission_manager::save($jobform->id, $USER->id, $values, $status);
            $msg = $status === submission_manager::STATUS_SUBMITTED
                ? get_string('formsent', 'mod_jobform')
                : get_string('draftsaved', 'mod_jobform');
            redirect($viewurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($existing) {
            // Prime with any saved values.
            $answers = submission_manager::get_answers($existing->id);
            $entryform->set_data(
                ['id' => $cm->id] + entry_form::values_to_formdata($answers, array_values($fields)));
        } else {
            $entryform->set_data(['id' => $cm->id]);
        }
    }
}

// ---------------------------------------------------------------------------
// Output. The activity header (rendered by header()) already shows the activity
// name and description, so we must not print the name again here.
// ---------------------------------------------------------------------------
if (!empty($jobform->intro)) {
    $PAGE->activityheader->set_description(format_module_intro('jobform', $jobform, $cm->id));
}
echo $OUTPUT->header();

// Confirm before deleting a submission (Submissions tab).
if ($cansubmissions) {
    $submissionaction = optional_param('submissionaction', '', PARAM_ALPHA);
    $submissionid = optional_param('submissionid', 0, PARAM_INT);
    if ($submissionaction === 'delete' && $submissionid > 0 && confirm_sesskey()
            && !optional_param('confirm', 0, PARAM_BOOL)) {
        $yesurl = new moodle_url($submissionsurl,
            ['submissionaction' => 'delete', 'submissionid' => $submissionid, 'sesskey' => sesskey(), 'confirm' => 1]);
        echo $OUTPUT->confirm(get_string('confirmdeletesubmission', 'local_jobform'),
            $yesurl, $submissionsurl);
        echo $OUTPUT->footer();
        exit;
    }
}

if ($cansmanage) {
    // Confirm dialogs (field delete / group delete / use default fields).
    $fieldaction = optional_param('fieldaction', '', PARAM_ALPHA);
    $fieldid = optional_param('fieldid', 0, PARAM_INT);
    $groupaction = optional_param('groupaction', '', PARAM_ALPHA);
    $groupid = optional_param('groupid', 0, PARAM_INT);
    $action = optional_param('action', '', PARAM_ALPHA);
    $unconfirmed = !optional_param('confirm', 0, PARAM_BOOL);

    if ($fieldaction === 'delete' && $fieldid > 0 && confirm_sesskey() && $unconfirmed) {
        $field = instance_manager::get_field($fieldid, $jobform->id);
        if ($field) {
            $yesurl = new moodle_url($fieldsurl,
                ['fieldaction' => 'delete', 'fieldid' => $fieldid, 'sesskey' => sesskey(), 'confirm' => 1]);
            echo $OUTPUT->confirm(
                get_string('confirmdeletefield', 'local_jobform') . ' (' . \local_jobform\mlang::display($field->name) . ')',
                $yesurl, $fieldsurl);
            echo $OUTPUT->footer();
            exit;
        }
    }
    if ($groupaction === 'delete' && $groupid > 0 && confirm_sesskey() && $unconfirmed) {
        $group = group_manager::get_group($groupid, $jobform->id);
        if ($group) {
            $yesurl = new moodle_url($fieldsurl,
                ['groupaction' => 'delete', 'groupid' => $groupid, 'sesskey' => sesskey(), 'confirm' => 1]);
            echo $OUTPUT->confirm(
                get_string('confirmdeletegroup', 'local_jobform') . ' (' . \local_jobform\mlang::display($group->name) . ')',
                $yesurl, $fieldsurl);
            echo $OUTPUT->footer();
            exit;
        }
    }
    if ($action === 'usedefault' && confirm_sesskey() && $unconfirmed) {
        $yesurl = new moodle_url($fieldsurl,
            ['action' => 'usedefault', 'sesskey' => sesskey(), 'confirm' => 1]);
        echo $OUTPUT->confirm(
            get_string('confirmusedefaultfields', 'mod_jobform'), $yesurl, $fieldsurl);
        echo $OUTPUT->footer();
        exit;
    }

    // Tabs: Fields (default) + Submissions.
    $tabs = [
        new tabobject('fields', $fieldsurl, get_string('tabfields', 'local_jobform')),
        new tabobject('submissions',
            new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'submissions']),
            get_string('tabsubmissions', 'local_jobform')),
    ];
    echo $OUTPUT->tabtree($tabs, $tab);

    if ($tab === 'submissions') {
        require_capability('mod/jobform:viewsubmissions', $context);
        echo activity_submissions::render($cm, $jobform, $cansubmissions);
    } else {
        echo html_writer::div(get_string('activityfieldsintro', 'mod_jobform'), 'text-muted mb-3');
        $editurl = new moodle_url('/mod/jobform/edit_field.php', ['id' => $cm->id]);
        $groupediturl = new moodle_url('/mod/jobform/group_edit.php', ['id' => $cm->id]);
        $usedefaulturl = new moodle_url($fieldsurl,
            ['action' => 'usedefault', 'sesskey' => sesskey()]);
        echo fields_ui::render($fields, $editurl, $fieldsurl, [
            'groups'        => $groups,
            'groupediturl'  => $groupediturl,
            'usedefaulturl' => $usedefaulturl,
        ]);
    }
} else if ($studentmode) {
    if (submission_manager::has_certificate($jobform, $USER->id) === false) {
        echo $OUTPUT->notification(get_string('certificaterequired', 'mod_jobform'),
            \core\output\notification::NOTIFY_WARNING);
    } else if ($entryform === null) {
        // Locked: already submitted and resubmission disabled — show read-only answers
        // as a clean brand-styled display (not a frozen form).
        echo $OUTPUT->notification(get_string('alreadysubmitted', 'mod_jobform'),
            \core\output\notification::NOTIFY_INFO);
        $existing = submission_manager::get_submission($jobform->id, $USER->id);
        $answers = submission_manager::get_answers($existing->id);
        echo \mod_jobform\output\submission_display::render($fields, $groups, $answers);
    } else {
        if (!$fields) {
            echo $OUTPUT->notification(get_string('noformfields', 'mod_jobform'),
                \core\output\notification::NOTIFY_INFO);
        } else {
            $entryform->display();
        }
    }
} else {
    echo $OUTPUT->notification(get_string('nothingtodisplay', 'mod_jobform'),
        \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();
