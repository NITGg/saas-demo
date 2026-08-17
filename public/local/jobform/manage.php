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
 * "Manage Job Form": the admin screen with two tabs — the global field
 * template, and every submitted form across the site.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\template_manager;
use local_jobform\group_manager;
use local_jobform\fields_ui;
use local_jobform\submissions_ui;

require_login();
$context = context_system::instance();
require_capability('local/jobform:manage', $context);

$tab = optional_param('tab', 'fields', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobform/manage.php', ['tab' => $tab]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managejobform', 'local_jobform'));
$PAGE->set_heading(get_string('managejobform', 'local_jobform'));

$manageurl = new moodle_url('/local/jobform/manage.php', ['tab' => 'fields']);
$submissionsurl = new moodle_url('/local/jobform/manage.php', ['tab' => 'submissions']);

// Handle a template field action (delete / reorder) coming from the fields tab.
$fieldaction = optional_param('fieldaction', '', PARAM_ALPHA);
$fieldid = optional_param('fieldid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Handle a submission action (delete) coming from the submissions tab.
$submissionaction = optional_param('submissionaction', '', PARAM_ALPHA);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

// Handle a group action (delete) coming from the fields tab.
$groupaction = optional_param('groupaction', '', PARAM_ALPHA);
$groupid = optional_param('groupid', 0, PARAM_INT);

if ($fieldaction === 'moveup' && $fieldid > 0 && confirm_sesskey()) {
    template_manager::reorder($fieldid, -1);
    redirect($manageurl);
} else if ($fieldaction === 'movedown' && $fieldid > 0 && confirm_sesskey()) {
    template_manager::reorder($fieldid, 1);
    redirect($manageurl);
} else if ($fieldaction === 'delete' && $fieldid > 0 && confirm_sesskey() && $confirm) {
    template_manager::delete_field($fieldid);
    redirect($manageurl, get_string('changessaved'), null,
        \core\output\notification::NOTIFY_SUCCESS);
} else if ($submissionaction === 'delete' && $submissionid > 0 && confirm_sesskey() && $confirm) {
    submissions_ui::delete_submission($submissionid);
    redirect($submissionsurl, get_string('submissiondeleted', 'local_jobform'), null,
        \core\output\notification::NOTIFY_SUCCESS);
} else if ($groupaction === 'delete' && $groupid > 0 && confirm_sesskey() && $confirm) {
    group_manager::delete_group($groupid);
    redirect($manageurl, get_string('changessaved'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Ask before deleting a group.
if ($groupaction === 'delete' && $groupid > 0 && confirm_sesskey() && !$confirm) {
    $group = group_manager::get_group($groupid);
    if ($group) {
        $yesurl = new moodle_url($manageurl,
            ['groupaction' => 'delete', 'groupid' => $groupid, 'sesskey' => sesskey(), 'confirm' => 1]);
        echo $OUTPUT->confirm(
            get_string('confirmdeletegroup', 'local_jobform') . ' (' . \local_jobform\mlang::display($group->name) . ')',
            $yesurl,
            $manageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
}

// Ask before deleting a submission.
if ($submissionaction === 'delete' && $submissionid > 0 && confirm_sesskey() && !$confirm) {
    $yesurl = new moodle_url($submissionsurl,
        ['submissionaction' => 'delete', 'submissionid' => $submissionid, 'sesskey' => sesskey(), 'confirm' => 1]);
    echo $OUTPUT->confirm(
        get_string('confirmdeletesubmission', 'local_jobform'),
        $yesurl,
        $submissionsurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Ask before deleting a field.
if ($fieldaction === 'delete' && $fieldid > 0 && confirm_sesskey() && !$confirm) {
    $field = template_manager::get_field($fieldid);
    if ($field) {
        $yesurl = new moodle_url($manageurl,
            ['fieldaction' => 'delete', 'fieldid' => $fieldid, 'sesskey' => sesskey(), 'confirm' => 1]);
        echo $OUTPUT->confirm(
            get_string('confirmdeletefield', 'local_jobform') . ' (' . \local_jobform\mlang::display($field->name) . ')',
            $yesurl,
            $manageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
}

// Tab bar.
$tabs = [
    new tabobject('fields',
        new moodle_url('/local/jobform/manage.php', ['tab' => 'fields']),
        get_string('tabfields', 'local_jobform')),
    new tabobject('submissions',
        new moodle_url('/local/jobform/manage.php', ['tab' => 'submissions']),
        get_string('tabsubmissions', 'local_jobform')),
];
echo $OUTPUT->tabtree($tabs, $tab);

if ($tab === 'submissions') {
    echo $OUTPUT->heading(get_string('tabsubmissions', 'local_jobform'), 3);
    $viewurl = new moodle_url('/local/jobform/submission.php');
    echo submissions_ui::render($viewurl, $submissionsurl);
} else {
    echo $OUTPUT->heading(get_string('templatefieldsheading', 'local_jobform'), 3);
    echo html_writer::div(get_string('templatefieldsintro', 'local_jobform'), 'text-muted mb-3');
    $editurl = new moodle_url('/local/jobform/field_edit.php');
    $groupediturl = new moodle_url('/local/jobform/group_edit.php');
    echo fields_ui::render(template_manager::get_fields(), $editurl, $manageurl, [
        'groups'       => group_manager::get_groups(),
        'groupediturl' => $groupediturl,
    ]);
}

echo $OUTPUT->footer();
