<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AI Inbox: the assistant's conversations, those waiting for a person first.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_assistant\inbox\manager;
use local_nit_assistant\inbox\ui;
use local_nit_assistant\runtime\state;

$status = optional_param('status', '', PARAM_ALPHAEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;
if ($status !== '' && !in_array($status, [state::OPEN, state::WAITING, state::HUMAN, state::CLOSED], true)) {
    $status = '';
}

admin_externalpage_setup('local_nit_assistant_inbox', '', ['status' => $status]);
$url = new moodle_url('/local/nit_assistant/index.php', ['status' => $status]);
$PAGE->set_url($url);

[$rows, $total] = manager::conversations($status, $page, $perpage);
$counts = manager::counts();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('inbox', 'local_nit_assistant'));
echo ui::tabs('inbox');

$filters = ['' => get_string('filter_active', 'local_nit_assistant')];
foreach ([state::WAITING, state::HUMAN, state::OPEN, state::CLOSED] as $s) {
    $label = get_string('status_' . $s, 'local_nit_assistant');
    $filters[$s] = isset($counts[$s]) && $s !== state::CLOSED ? "{$label} ({$counts[$s]})" : $label;
}
$links = [];
foreach ($filters as $value => $label) {
    $links[] = html_writer::link(
        new moodle_url('/local/nit_assistant/index.php', ['status' => $value]),
        $label,
        ['class' => 'btn btn-sm ' . ($value === $status ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $links), 'mb-3 d-flex flex-wrap gap-2');

if (!$rows) {
    echo $OUTPUT->notification(get_string('inbox_empty', 'local_nit_assistant'), 'info', false);
} else {
    $table = new html_table();
    $table->head = [
        get_string('col_who', 'local_nit_assistant'),
        get_string('col_status', 'local_nit_assistant'),
        get_string('col_last', 'local_nit_assistant'),
        get_string('col_messages', 'local_nit_assistant'),
        get_string('col_note', 'local_nit_assistant'),
    ];
    $table->attributes['class'] = 'generaltable table-hover';
    foreach ($rows as $c) {
        $view = new moodle_url('/local/nit_assistant/view.php', ['id' => $c->id]);
        $who = ui::who($c);
        if (!empty($c->leadname)) {
            $who .= ' ' . html_writer::span(get_string('lead', 'local_nit_assistant'), 'badge bg-info text-dark');
        }
        $table->data[] = [
            html_writer::link($view, $who),
            ui::status_badge($c->status, (int) $c->assignedto),
            userdate($c->lastmessageat, get_string('strftimedatetimeshort', 'langconfig')),
            (int) $c->messagecount,
            s(shorten_text((string) $c->summary, 90)),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
}

echo $OUTPUT->footer();
