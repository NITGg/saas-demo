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
 * Follow-ups: the messages drafted today for students (an unpaid checkout, a
 * plan that ended, a buying link from the chat, unused lesson credits). Staff
 * read each one, may edit it, then send or dismiss it.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_assistant\followup\manager;
use local_nit_assistant\inbox\ui;

$status = optional_param('status', 'draft', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
if (!in_array($status, manager::STATUSES, true)) {
    $status = 'draft';
}

admin_externalpage_setup('local_nit_assistant_followups', '', ['status' => $status]);
$context = context_system::instance();
$url = new moodle_url('/local/nit_assistant/followups.php', ['status' => $status, 'page' => $page]);
$PAGE->set_url($url);

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    require_capability('local/nit_assistant:reply', $context);
    $id = required_param('id', PARAM_INT);
    if ($action === 'send') {
        $title = optional_param('title', '', PARAM_TEXT);
        $ok = manager::approve($id, (int) $USER->id, $title, optional_param('body', '', PARAM_TEXT));
        $msg = get_string($ok ? 'fu_sent' : 'fu_notsent', 'local_nit_assistant');
    } else {
        $ok = manager::dismiss($id, (int) $USER->id);
        $msg = get_string($ok ? 'fu_dismissed' : 'error_changed', 'local_nit_assistant');
    }
    redirect($url, $msg, null, $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING);
}

[$rows, $total] = manager::list($status, $page, $perpage);
$canreply = has_capability('local/nit_assistant:reply', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('followups', 'local_nit_assistant'));
echo ui::tabs('followups');

if (!manager::enabled()) {
    $settings = new moodle_url('/admin/settings.php', ['section' => 'local_nit_assistant']);
    echo $OUTPUT->notification(get_string('fu_off', 'local_nit_assistant', $settings->out(false)), 'info', false);
}
echo html_writer::tag('p', get_string('fu_intro', 'local_nit_assistant'), ['class' => 'text-muted']);

$links = [];
foreach (manager::STATUSES as $s) {
    $links[] = html_writer::link(
        new moodle_url('/local/nit_assistant/followups.php', ['status' => $s]),
        get_string('fustatus_' . $s, 'local_nit_assistant'),
        ['class' => 'btn btn-sm ' . ($status === $s ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $links), 'mb-3 d-flex flex-wrap gap-2');

if (!$rows) {
    echo $OUTPUT->notification(get_string('fu_empty', 'local_nit_assistant'), 'info', false);
}
foreach ($rows as $f) {
    $user = core_user::get_user((int) $f->userid);
    $who = $user ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $user->id]), s(fullname($user))) : '';
    $head = html_writer::span(s(get_string('fukind_' . $f->kind, 'local_nit_assistant')), 'badge bg-info text-dark')
        . ' ' . $who . ' · ' . userdate($f->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    if ($f->convid) {
        $head .= ' · ' . html_writer::link(
            new moodle_url('/local/nit_assistant/view.php', ['id' => $f->convid]),
            get_string('openconversation', 'local_nit_assistant')
        );
    }
    $link = $f->url ? html_writer::div(
        html_writer::link($f->url, s($f->url), ['target' => '_blank', 'rel' => 'noopener']),
        'small text-break mb-2'
    ) : '';
    if ($status === 'draft' && $canreply) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $f->id])
            // The message is already in the student's own language: one box, not local_nit_mlang's per-language boxes.
            . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'title', 'value' => $f->title, 'maxlength' => 255,
                'dir' => 'auto', 'class' => 'form-control mb-2', 'data-nitml' => 'off',
                'aria-label' => get_string('fu_title', 'local_nit_assistant')])
            . html_writer::tag('textarea', s($f->body), ['name' => 'body', 'rows' => 3, 'maxlength' => manager::MAXBODY,
                'dir' => 'auto', 'class' => 'form-control mb-2', 'aria-label' => get_string('fu_body', 'local_nit_assistant')])
            . $link
            . html_writer::tag(
                'button',
                get_string('fu_send', 'local_nit_assistant'),
                ['type' => 'submit', 'name' => 'action', 'value' => 'send', 'class' => 'btn btn-primary btn-sm me-2']
            )
            . html_writer::tag(
                'button',
                get_string('fu_dismiss', 'local_nit_assistant'),
                ['type' => 'submit', 'name' => 'action', 'value' => 'dismiss', 'class' => 'btn btn-outline-secondary btn-sm']
            )
            . html_writer::end_tag('form');
    } else {
        $form = html_writer::tag('strong', s($f->title), ['dir' => 'auto'])
            . html_writer::div(format_text($f->body, FORMAT_PLAIN), '', ['dir' => 'auto']) . $link;
    }
    echo html_writer::div(html_writer::div($head, 'small text-muted mb-2') . $form, 'border rounded p-3 mb-3');
}
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);

echo $OUTPUT->footer();
