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
 * Site administration → Users → Teacher applications → Application fields: what a teacher must
 * answer to apply (beyond the fixed bio, subjects, intro video and qualifications).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_tutors\local\fields;

$edit = optional_param('edit', -1, PARAM_INT);   // -1 list, 0 new, n edit.
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_nit_tutors_fields');
require_capability('local/nit_tutors:manage', context_system::instance());
$baseurl = new moodle_url('/local/nit_tutors/admin/fields.php');
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

if ($action !== '' && $id && confirm_sesskey()) {
    if ($action === 'delete' && optional_param('confirm', 0, PARAM_BOOL)) {
        fields::delete($id);
        redirect($baseurl, $str('done'));
    } else if ($action === 'up' || $action === 'down') {
        fields::move($id, $action === 'up' ? -1 : 1);
        redirect($baseurl);
    }
}

if ($edit >= 0) {
    $form = new \local_nit_tutors\form\field_form(new moodle_url($baseurl, ['edit' => $edit]));
    if ($edit > 0) {
        $field = fields::get($edit) ?? throw new moodle_exception('invalidrecord', 'error');
        $form->set_data($field);
    }
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $data->id = $edit;
        fields::save($data);
        redirect($baseurl, $str('done'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading($str($edit ? 'editfield' : 'addfield'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($str('applicationfields'));

if ($action === 'delete' && $id && !optional_param('confirm', 0, PARAM_BOOL) && ($field = fields::get($id))) {
    echo $OUTPUT->confirm($str('confirmdeletefield', format_string($field->name)),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]), $baseurl);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', $str('fields_intro'), ['class' => 'text-muted']);
echo $OUTPUT->single_button(new moodle_url($baseurl, ['edit' => 0]), $str('addfield'), 'get');

$all = array_values(fields::all(false));
if (!$all) {
    echo $OUTPUT->notification($str('nofields'), 'info');
} else {
    $table = new html_table();
    $table->head = [$str('field_name'), $str('field_shortname'), $str('field_type'), $str('field_required'),
        $str('field_public'), $str('field_filter'), $str('field_active'), ''];
    $yes = get_string('yes');
    foreach ($all as $i => $f) {
        $links = [];
        if ($i > 0) {
            $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['action' => 'up', 'id' => $f->id, 'sesskey' => sesskey()]),
                new pix_icon('t/up', get_string('moveup')));
        }
        if ($i < count($all) - 1) {
            $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['action' => 'down', 'id' => $f->id, 'sesskey' => sesskey()]),
                new pix_icon('t/down', get_string('movedown')));
        }
        $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['edit' => $f->id]), new pix_icon('t/edit', get_string('edit')));
        $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['action' => 'delete', 'id' => $f->id, 'sesskey' => sesskey()]),
            new pix_icon('t/delete', get_string('delete')));
        $table->data[] = [format_string($f->name), s($f->shortname), $str('type_' . $f->datatype),
            $f->required ? $yes : '', $f->public ? $yes : '', $f->filter ? $yes : '', $f->active ? $yes : '', implode(' ', $links)];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
