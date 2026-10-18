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
 * Teacher applications → Badges tab: the badges teachers can wear (added, edited, ordered, deleted
 * by the admin), each earned by rules or given by a reviewer.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_nit_tutors\local\admin_tabs;
use local_nit_tutors\local\badge;

$edit = optional_param('edit', -1, PARAM_INT);   // -1 list, 0 new, n edit.
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

admin_tabs::setup('badges', $edit >= 0 ? ['edit' => $edit] : []);
$baseurl = new moodle_url('/local/nit_tutors/admin/badges.php');
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

if ($action !== '' && $id && confirm_sesskey()) {
    if ($action === 'delete' && optional_param('confirm', 0, PARAM_BOOL)) {
        badge::delete($id);
        redirect($baseurl, $str('done'));
    } else if ($action === 'up' || $action === 'down') {
        badge::move($id, $action === 'up' ? -1 : 1);
        redirect($baseurl);
    }
}

if ($edit >= 0) {
    $form = new \local_nit_tutors\form\badge_form(new moodle_url($baseurl, ['edit' => $edit]));
    if ($edit > 0) {
        $form->set_data(badge::get($edit) ?? throw new moodle_exception('invalidrecord', 'error'));
    }
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $data->id = $edit;
        badge::save($data);
        redirect($baseurl, $str('done'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo admin_tabs::render('badges');
    echo $OUTPUT->heading($str($edit ? 'editbadge' : 'addbadge'), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo admin_tabs::render('badges');

if ($action === 'delete' && $id && !optional_param('confirm', 0, PARAM_BOOL) && ($b = badge::get($id))) {
    echo $OUTPUT->confirm($str('confirmdeletebadge', format_string($b->name)),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]), $baseurl);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', $str('badges_intro'), ['class' => 'text-muted']);
echo $OUTPUT->single_button(new moodle_url($baseurl, ['edit' => 0]), $str('addbadge'), 'get');

$all = array_values(badge::all(false));
$holders = badge::holder_counts();
if (!$all) {
    echo $OUTPUT->notification($str('nobadges'), 'info');
} else {
    $table = new html_table();
    $table->head = [$str('badge_name'), $str('badge_method'), $str('badge_rules'), $str('badge_holders'),
        $str('field_active'), ''];
    foreach ($all as $i => $b) {
        $links = [];
        if ($i > 0) {
            $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['action' => 'up', 'id' => $b->id, 'sesskey' => sesskey()]),
                new pix_icon('t/up', get_string('moveup')));
        }
        if ($i < count($all) - 1) {
            $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['action' => 'down', 'id' => $b->id, 'sesskey' => sesskey()]),
                new pix_icon('t/down', get_string('movedown')));
        }
        $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['edit' => $b->id]), new pix_icon('t/edit', get_string('edit')));
        $links[] = $OUTPUT->action_icon(new moodle_url($baseurl, ['action' => 'delete', 'id' => $b->id, 'sesskey' => sesskey()]),
            new pix_icon('t/delete', get_string('delete')));
        $chip = html_writer::span(format_string($b->name), 'nittu-badge nittu-badge--' . $b->color);
        $desc = trim((string) $b->description) !== '' ? html_writer::div(format_string($b->description), 'small text-muted') : '';
        $table->data[] = [$chip . $desc, $str('badgemethod_' . $b->method), s(badge::rules_text($b)),
            (int) ($holders[$b->id] ?? 0), $b->active ? get_string('yes') : '', implode(' ', $links)];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
