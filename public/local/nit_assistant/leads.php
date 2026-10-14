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
 * Leads the assistant captured (with consent): contact details, what they want,
 * and the team's follow-up status and notes.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_assistant\inbox\manager;
use local_nit_assistant\inbox\ui;

$status = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;
if ($status !== '' && !in_array($status, manager::LEAD_STATUSES, true)) {
    $status = '';
}

admin_externalpage_setup('local_nit_assistant_leads', '', ['status' => $status]);
$context = context_system::instance();
$url = new moodle_url('/local/nit_assistant/leads.php', ['status' => $status, 'page' => $page]);
$PAGE->set_url($url);

if (optional_param('save', 0, PARAM_INT)) {
    require_sesskey();
    require_capability('local/nit_assistant:reply', $context);
    manager::update_lead(
        required_param('leadid', PARAM_INT),
        required_param('leadstatus', PARAM_ALPHA),
        optional_param('notes', '', PARAM_TEXT),
        (int) $USER->id
    );
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

[$rows, $total] = manager::leads($status, $page, $perpage);
$canedit = has_capability('local/nit_assistant:reply', $context);
$labels = [];
foreach (manager::LEAD_STATUSES as $s) {
    $labels[$s] = get_string('leadstatus_' . $s, 'local_nit_assistant');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('leads', 'local_nit_assistant'));
echo ui::tabs('leads');

$links = [html_writer::link(
    new moodle_url('/local/nit_assistant/leads.php'),
    get_string('all'),
    ['class' => 'btn btn-sm ' . ($status === '' ? 'btn-primary' : 'btn-outline-secondary')]
)];
foreach ($labels as $s => $label) {
    $links[] = html_writer::link(
        new moodle_url('/local/nit_assistant/leads.php', ['status' => $s]),
        $label,
        ['class' => 'btn btn-sm ' . ($status === $s ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $links), 'mb-3 d-flex flex-wrap gap-2');

if (!$rows) {
    echo $OUTPUT->notification(get_string('leads_empty', 'local_nit_assistant'), 'info', false);
} else {
    $table = new html_table();
    $table->head = [
        get_string('col_when', 'local_nit_assistant'),
        get_string('col_who', 'local_nit_assistant'),
        get_string('col_contact', 'local_nit_assistant'),
        get_string('col_interest', 'local_nit_assistant'),
        get_string('col_status', 'local_nit_assistant'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($rows as $l) {
        $contact = [];
        if ($l->phone) {
            $contact[] = html_writer::link('tel:' . $l->phone, s($l->phone));
        }
        if ($l->email) {
            $contact[] = html_writer::link('mailto:' . $l->email, s($l->email));
        }
        $statuscell = s($labels[$l->status] ?? $l->status) . ($l->notes ? html_writer::div(s($l->notes), 'small text-muted') : '');
        if ($canedit) {
            $statuscell = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'leadid', 'value' => $l->id])
                . html_writer::select(
                    $labels,
                    'leadstatus',
                    $l->status,
                    false,
                    ['class' => 'form-select form-select-sm mb-1', 'aria-label' => get_string('col_status', 'local_nit_assistant')]
                )
                . html_writer::tag('textarea', s((string) $l->notes), ['name' => 'notes', 'rows' => 2,
                    'class' => 'form-control form-control-sm mb-1', 'aria-label' => get_string('notes', 'local_nit_assistant')])
                . html_writer::tag('button', get_string('save'), ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary'])
                . html_writer::end_tag('form');
        }
        $table->data[] = [
            userdate($l->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            html_writer::link(new moodle_url('/local/nit_assistant/view.php', ['id' => $l->convid]), s($l->name)),
            implode('<br>', $contact),
            s((string) $l->interest),
            $statuscell,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
}

echo $OUTPUT->footer();
