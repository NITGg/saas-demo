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
 * Add / edit a group of a Job Form activity.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\form\group_form;
use mod_jobform\group_manager;

$cmid = required_param('id', PARAM_INT);       // Course module id.
$groupid = optional_param('groupid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jobform');
$jobform = $DB->get_record('jobform', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jobform:managefields', $context);

$fieldsurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'fields']);

$PAGE->set_url(new moodle_url('/mod/jobform/group_edit.php', ['id' => $cm->id, 'groupid' => $groupid]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($jobform->name));
$PAGE->set_heading(format_string($course->fullname));

$heading = $groupid ? get_string('editgroup', 'local_jobform') : get_string('addgroup', 'local_jobform');

$form = new group_form($PAGE->url);

if ($groupid) {
    $group = group_manager::get_group($groupid, $jobform->id);
    if (!$group) {
        throw new moodle_exception('invalidgroup', 'local_jobform');
    }
    $form->set_group_data($group);
}

if ($form->is_cancelled()) {
    redirect($fieldsurl);
} else if ($data = $form->get_data()) {
    group_manager::save_group($jobform->id, $data);
    redirect($fieldsurl, get_string('changessaved'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$form->display();
echo $OUTPUT->footer();
