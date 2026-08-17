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
 * Add / edit a group in the global Job Form template.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\group_manager;
use local_jobform\form\group_form;

require_login();
$context = context_system::instance();
require_capability('local/jobform:manage', $context);

$groupid = optional_param('groupid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobform/group_edit.php', ['groupid' => $groupid]));
$PAGE->set_pagelayout('admin');

$manageurl = new moodle_url('/local/jobform/manage.php', ['tab' => 'fields']);

$heading = $groupid ? get_string('editgroup', 'local_jobform') : get_string('addgroup', 'local_jobform');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->navbar->add(get_string('managejobform', 'local_jobform'), $manageurl);
$PAGE->navbar->add($heading);

$form = new group_form($PAGE->url);

if ($groupid) {
    $group = group_manager::get_group($groupid);
    if (!$group) {
        throw new moodle_exception('invalidgroup', 'local_jobform');
    }
    $form->set_group_data($group);
}

if ($form->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $form->get_data()) {
    group_manager::save_group($data);
    redirect($manageurl, get_string('changessaved'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$form->display();
echo $OUTPUT->footer();
