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
 * Support tickets students opened through the assistant: the problem as the
 * assistant wrote it up, the student, the conversation, and the team's status
 * and notes.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_assistant\inbox\manager;
use local_nit_assistant\inbox\ui;

$status = optional_param('status', '', PARAM_ALPHAEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;
if ($status !== '' && !in_array($status, manager::TICKET_STATUSES, true)) {
    $status = '';
}

admin_externalpage_setup('local_nit_assistant_tickets', '', ['status' => $status]);
$context = context_system::instance();
$url = new moodle_url('/local/nit_assistant/tickets.php', ['status' => $status, 'page' => $page]);
$PAGE->set_url($url);

if (optional_param('save', 0, PARAM_INT)) {
    require_sesskey();
    require_capability('local/nit_assistant:reply', $context);
    manager::update_ticket(
        required_param('ticketid', PARAM_INT),
        required_param('ticketstatus', PARAM_ALPHAEXT),
        optional_param('notes', '', PARAM_TEXT),
        (int) $USER->id
    );
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

[$rows, $total] = manager::tickets($status, $page, $perpage);
$canedit = has_capability('local/nit_assistant:reply', $context);
$labels = [];
foreach (manager::TICKET_STATUSES as $s) {
    $labels[$s] = get_string('ticketstatus_' . $s, 'local_nit_assistant');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tickets', 'local_nit_assistant'));
echo ui::tabs('tickets');

$links = [html_writer::link(
    new moodle_url('/local/nit_assistant/tickets.php'),
    get_string('filter_active', 'local_nit_assistant'),
    ['class' => 'btn btn-sm ' . ($status === '' ? 'btn-primary' : 'btn-outline-secondary')]
)];
foreach ($labels as $s => $label) {
    $links[] = html_writer::link(
        new moodle_url('/local/nit_assistant/tickets.php', ['status' => $s]),
        $label,
        ['class' => 'btn btn-sm ' . ($status === $s ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $links), 'mb-3 d-flex flex-wrap gap-2');

if (!$rows) {
    echo $OUTPUT->notification(get_string('tickets_empty', 'local_nit_assistant'), 'info', false);
} else {
    $table = new html_table();
    $table->head = [
        '#',
        get_string('col_when', 'local_nit_assistant'),
        get_string('col_who', 'local_nit_assistant'),
        get_string('col_problem', 'local_nit_assistant'),
        get_string('col_status', 'local_nit_assistant'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($rows as $t) {
        $user = core_user::get_user((int) $t->userid);
        $who = $user ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $user->id]), s(fullname($user))) : '';
        $problem = html_writer::span(s(get_string('ticketcat_' . $t->category, 'local_nit_assistant')), 'badge bg-info text-dark')
            . ' ' . html_writer::tag('strong', s($t->subject), ['dir' => 'auto'])
            . html_writer::div(format_text($t->description, FORMAT_PLAIN), 'small', ['dir' => 'auto'])
            . html_writer::link(
                new moodle_url('/local/nit_assistant/view.php', ['id' => $t->convid]),
                get_string('openconversation', 'local_nit_assistant'),
                ['class' => 'small']
            );
        $statuscell = s($labels[$t->status] ?? $t->status) . ($t->notes ? html_writer::div(s($t->notes), 'small text-muted') : '');
        if ($canedit) {
            $statuscell = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ticketid', 'value' => $t->id])
                . html_writer::select(
                    $labels,
                    'ticketstatus',
                    $t->status,
                    false,
                    ['class' => 'form-select form-select-sm mb-1', 'aria-label' => get_string('col_status', 'local_nit_assistant')]
                )
                . html_writer::tag('textarea', s((string) $t->notes), ['name' => 'notes', 'rows' => 2,
                    'class' => 'form-control form-control-sm mb-1',
                    'aria-label' => get_string('staffnotes', 'local_nit_assistant')])
                . html_writer::tag('button', get_string('save'), ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary'])
                . html_writer::end_tag('form');
        }
        $table->data[] = [
            (int) $t->id,
            userdate($t->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $who,
            $problem,
            $statuscell,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
}

echo $OUTPUT->footer();
