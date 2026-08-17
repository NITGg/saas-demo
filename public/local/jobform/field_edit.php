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
 * Add / edit a single field in the global Job Form template.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\template_manager;
use local_jobform\group_manager;
use local_jobform\form\field_form;

require_login();
$context = context_system::instance();
require_capability('local/jobform:manage', $context);

$fieldid = optional_param('fieldid', 0, PARAM_INT);
$defaultgroupid = optional_param('groupid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobform/field_edit.php', ['fieldid' => $fieldid]));
$PAGE->set_pagelayout('admin');

$manageurl = new moodle_url('/local/jobform/manage.php', ['tab' => 'fields']);

$heading = $fieldid ? get_string('editfield', 'local_jobform') : get_string('addfield', 'local_jobform');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->navbar->add(get_string('managejobform', 'local_jobform'), $manageurl);
$PAGE->navbar->add($heading);

// Load the field first so the form can size its repeated option rows.
$field = null;
if ($fieldid) {
    $field = template_manager::get_field($fieldid);
    if (!$field) {
        throw new moodle_exception('invalidfield', 'local_jobform');
    }
}
$optioncount = $field
    ? count(\local_jobform\field_types::decode_config($field->configdata)['options'])
    : 0;

$form = new field_form($PAGE->url, [
    'groups'         => group_manager::menu(),
    'defaultgroupid' => $defaultgroupid,
    'optioncount'    => $optioncount,
]);

// Prime for editing.
if ($field) {
    $form->set_field_data($field);
}

if ($form->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $form->get_data()) {
    template_manager::save_field($data);
    redirect($manageurl, get_string('changessaved'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$form->display();
echo $OUTPUT->footer();
