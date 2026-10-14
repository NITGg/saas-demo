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
 * One conversation in the AI Inbox: read it, take it over, reply, hand it back
 * to the assistant, or close it. The page refreshes itself when something new
 * arrives (unless a reply is being typed).
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

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

admin_externalpage_setup('local_nit_assistant_inbox', '', ['id' => $id], new moodle_url('/local/nit_assistant/view.php'));
$context = context_system::instance();
$url = new moodle_url('/local/nit_assistant/view.php', ['id' => $id]);
$PAGE->set_url($url);
$conv = $DB->get_record('local_nit_asst_conv', ['id' => $id], '*', MUST_EXIST);

// Polling: has anything changed since the page was drawn?
if ($action === 'poll') {
    \core\session\manager::write_close();
    header('Content-Type: application/json');
    $last = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_nit_asst_msg} WHERE convid = ?', [$id]);
    echo json_encode(['last' => $last, 'version' => (int) $conv->stateversion]);
    exit;
}

if ($action !== '') {
    require_sesskey();
    require_capability('local/nit_assistant:reply', $context);
    try {
        $done = match ($action) {
            'takeover' => manager::take_over($id, (int) $USER->id),
            'handback' => manager::hand_back($id),
            'close' => manager::close($id),
            'reply' => (bool) manager::reply($id, (int) $USER->id, required_param('text', PARAM_TEXT)),
            default => false,
        };
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (!$done) {
        redirect($url, get_string('error_changed', 'local_nit_assistant'), null, \core\output\notification::NOTIFY_WARNING);
    }
    redirect($url);
}

$messages = manager::messages($id);
$lead = $DB->get_record('local_nit_asst_lead', ['convid' => $id], '*', IGNORE_MULTIPLE);
$conv->leadname = $lead->name ?? null;
$canreply = has_capability('local/nit_assistant:reply', $context);
$last = $messages ? (int) end($messages)->id : 0;

$PAGE->navbar->add(ui::who($conv));
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::who($conv) . ' ' . ui::status_badge($conv->status, (int) $conv->assignedto));
echo ui::tabs('inbox');

// Facts about the conversation.
$facts = [
    get_string('col_started', 'local_nit_assistant') => userdate($conv->timecreated),
    get_string('col_lang', 'local_nit_assistant') => $conv->lang,
    get_string('col_page', 'local_nit_assistant') => s((string) $conv->sourcepage),
];
if ($conv->channel === 'whatsapp') {
    $open = manager::whatsapp_window_open($conv);
    $facts['WhatsApp'] = s((string) $conv->phone) . ' · '
        . get_string($open ? 'wa_windowopen' : 'wa_windowclosed', 'local_nit_assistant');
}
if ((int) $conv->userid > 0) {
    $facts[get_string('col_account', 'local_nit_assistant')] = html_writer::link(
        new moodle_url('/user/profile.php', ['id' => $conv->userid]),
        get_string('viewprofile')
    );
}
if ($lead) {
    $facts[get_string('lead', 'local_nit_assistant')] = s(trim($lead->name . ' · ' . $lead->phone . ' ' . $lead->email))
        . '<br>' . s((string) $lead->interest);
}
if ($conv->summary) {
    $facts[get_string('col_note', 'local_nit_assistant')] = format_text($conv->summary, FORMAT_PLAIN);
}
$dl = '';
foreach ($facts as $k => $v) {
    $dl .= html_writer::tag('dt', $k, ['class' => 'col-sm-3']) . html_writer::tag('dd', $v, ['class' => 'col-sm-9']);
}
echo html_writer::tag('dl', $dl, ['class' => 'row mb-4']);

// The messages.
echo html_writer::start_div('nit-asst-staffview mb-4');
foreach ($messages as $m) {
    $time = userdate($m->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    if ($m->role === 'tool') {
        $data = json_decode($m->content, true) ?: [];
        $text = '🔧 ' . s($m->toolname) . ' ' . s(json_encode($data['input'] ?? [], JSON_UNESCAPED_UNICODE))
            . (!empty($data['ok']) ? ' ✓' : ' ✗ ' . s($data['code'] ?? ''));
        echo html_writer::div($text, 'small text-muted my-1');
        continue;
    }
    $label = match ($m->role) {
        'visitor' => ui::who($conv),
        'staff' => s(fullname(core_user::get_user((int) $m->staffid) ?: core_user::get_noreply_user())),
        default => get_string('assistant', 'local_nit_assistant'),
    };
    $class = $m->role === 'visitor' ? 'bg-light' : ($m->role === 'staff' ? 'border border-success' : 'border');
    $failed = $m->status === 'failed'
        ? ' ' . html_writer::span(get_string('notdelivered', 'local_nit_assistant'), 'badge bg-danger') : '';
    echo html_writer::div(
        html_writer::div($label . ' · ' . $time . $failed, 'small text-muted mb-1')
            . html_writer::div(format_text($m->content, FORMAT_PLAIN, ['context' => $context]), '', ['dir' => 'auto']),
        'p-2 mb-2 rounded ' . $class
    );
}
echo html_writer::end_div();

// Actions.
if ($canreply && $conv->status !== state::CLOSED) {
    $button = static fn(string $act, string $label, string $class): string => $OUTPUT->single_button(
        new moodle_url($url, ['action' => $act, 'sesskey' => sesskey()]),
        get_string($label, 'local_nit_assistant'),
        'post',
        ['type' => $class]
    );
    $mine = $conv->status === state::HUMAN && (int) $conv->assignedto === (int) $USER->id;
    if ($conv->status !== state::HUMAN || !$mine) {
        echo html_writer::div(get_string('reply_takes_over', 'local_nit_assistant'), 'small text-muted mb-2');
    }
    if ($conv->channel === 'whatsapp') {
        echo html_writer::div(get_string('wa_replyhint', 'local_nit_assistant'), 'small text-muted mb-2');
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reply']);
    echo html_writer::tag('textarea', '', ['name' => 'text', 'id' => 'nit-asst-reply', 'rows' => 3, 'required' => 'required',
        'maxlength' => manager::MAXREPLY, 'class' => 'form-control mb-2',
        'aria-label' => get_string('reply', 'local_nit_assistant')]);
    echo html_writer::tag('button', get_string('reply', 'local_nit_assistant'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');

    $buttons = '';
    if ($conv->status !== state::HUMAN) {
        $buttons .= $button('takeover', 'takeover', 'secondary');
    }
    if ($conv->status !== state::OPEN) {
        $buttons .= $button('handback', 'handback', 'secondary');
    }
    $buttons .= $button('close', 'closeconv', 'danger');
    echo html_writer::div($buttons, 'd-flex flex-wrap gap-2');
}

// Refresh when a message arrives or the status changes (not while typing).
$poll = json_encode((new moodle_url($url, ['action' => 'poll']))->out(false));
$js = "(function(){var last={$last},version=" . (int) $conv->stateversion . ";setInterval(function(){"
    . "var box=document.getElementById('nit-asst-reply');if(box&&box.value.trim()!==''){return;}"
    . "fetch({$poll},{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){"
    . "if(d.last!==last||d.version!==version){window.location.reload();}}).catch(function(){});},8000);})();";
echo html_writer::script($js);

echo $OUTPUT->footer();
