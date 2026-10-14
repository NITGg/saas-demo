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
 * WhatsApp set-up and status for this academy: what is still missing, the
 * webhook to paste into Meta, whether the token works, whether the WhatsApp
 * account is linked to the app (with a button to link it), and a test message.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_assistant\inbox\ui;
use local_nit_assistant\whatsapp\cloud;
use local_nit_core\helper\crypto;

admin_externalpage_setup('local_nit_assistant_whatsapp');
$url = new moodle_url('/local/nit_assistant/whatsapp.php');
$PAGE->set_url($url);
$cloud = cloud::instance();

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && $cloud) {
    require_sesskey();
    if ($action === 'subscribe') {
        $r = $cloud->subscribe();
        redirect(
            $url,
            $r['ok'] ? get_string('wa_linked', 'local_nit_assistant') : s($r['error']),
            null,
            $r['ok'] ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($action === 'test') {
        $to = '+' . preg_replace('/\D+/', '', required_param('to', PARAM_RAW));
        try {
            $cloud->send_template($to, 'hello_world', 'en_US');
            redirect(
                $url,
                get_string('wa_testsent', 'local_nit_assistant', s($to)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (moodle_exception $e) {
            redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('wa_heading', 'local_nit_assistant'));
echo ui::tabs('settings');

$ok = static fn(bool $yes): string => $yes ? html_writer::span('✓', 'text-success fw-bold me-2')
    : html_writer::span('✗', 'text-danger fw-bold me-2');
$setting = static fn(string $name): bool => trim((string) get_config('local_nit_assistant', $name)) !== '';
$secret = static fn(string $name): bool => trim((string) crypto::config_secret('local_nit_assistant', $name)) !== '';
$checks = [
    get_string('wa_phonenumberid', 'local_nit_assistant') => $setting('wa_phonenumberid'),
    get_string('wa_wabaid', 'local_nit_assistant') => $setting('wa_wabaid'),
    get_string('wa_token', 'local_nit_assistant') => $secret('wa_token'),
    get_string('wa_appsecret', 'local_nit_assistant') => $secret('wa_appsecret'),
    get_string('wa_verifytoken', 'local_nit_assistant') => $setting('wa_verifytoken'),
    get_string('wa_ai', 'local_nit_assistant') => (bool) get_config('local_nit_assistant', 'wa_ai'),
];
$list = '';
foreach ($checks as $label => $yes) {
    $list .= html_writer::tag('li', $ok($yes) . s($label));
}
echo html_writer::tag('h3', get_string('wa_checklist', 'local_nit_assistant'), ['class' => 'h5']);
echo html_writer::tag('ul', $list, ['class' => 'list-unstyled']);
echo html_writer::link(
    new moodle_url('/admin/settings.php', ['section' => 'local_nit_assistant']),
    get_string('wa_editsettings', 'local_nit_assistant'),
    ['class' => 'btn btn-secondary btn-sm mb-4']
);

// The webhook Meta must call.
echo html_writer::tag('h3', get_string('wa_webhook', 'local_nit_assistant'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('wa_webhook_desc', 'local_nit_assistant'));
echo html_writer::tag('pre', s((new moodle_url('/local/nit_assistant/webhook.php'))->out(false)), ['class' => 'bg-light p-2']);
if ($setting('wa_verifytoken')) {
    echo html_writer::tag('p', get_string('wa_verifytoken', 'local_nit_assistant') . ': '
        . html_writer::tag('code', s((string) get_config('local_nit_assistant', 'wa_verifytoken'))));
}

if ($cloud) {
    // Token.
    $token = $cloud->check_token();
    echo html_writer::tag('h3', get_string('wa_tokencheck', 'local_nit_assistant'), ['class' => 'h5 mt-4']);
    echo html_writer::tag('p', $ok($token['ok']) . s($token['ok'] ? trim($token['name'] . ' · ' . $token['number'])
        : get_string('wa_tokenbad', 'local_nit_assistant', $token['error'])));

    // The WhatsApp account must be linked to the app, or Meta delivers nothing.
    $sub = $cloud->subscription();
    echo html_writer::tag('h3', get_string('wa_subscription', 'local_nit_assistant'), ['class' => 'h5 mt-4']);
    $linked = $sub['ok'] && !empty($sub['apps']);
    if (!$sub['ok']) {
        $substatus = $sub['error'];
    } else if ($linked) {
        $substatus = implode(', ', array_filter(array_column($sub['apps'], 'name')));
        $substatus = $substatus !== '' ? $substatus : get_string('wa_linked', 'local_nit_assistant');
    } else {
        $substatus = get_string('wa_notlinked', 'local_nit_assistant');
    }
    echo html_writer::tag('p', $ok($linked) . s($substatus));
    if (!$linked) {
        echo $OUTPUT->single_button(
            new moodle_url($url, ['action' => 'subscribe', 'sesskey' => sesskey()]),
            get_string('wa_link', 'local_nit_assistant'),
            'post',
            ['type' => 'primary']
        );
    }

    // Test message (Meta's hello_world template works without an open conversation).
    echo html_writer::tag('h3', get_string('wa_test', 'local_nit_assistant'), ['class' => 'h5 mt-4']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex gap-2 mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'test']);
    echo html_writer::empty_tag('input', ['type' => 'tel', 'name' => 'to', 'required' => 'required', 'data-nitml' => 'off',
        'placeholder' => '+2010…', 'class' => 'form-control', 'style' => 'max-width: 16rem',
        'aria-label' => get_string('wa_testto', 'local_nit_assistant')]);
    echo html_writer::tag('button', get_string('wa_testsend', 'local_nit_assistant'), ['type' => 'submit',
        'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
} else {
    echo $OUTPUT->notification(get_string('wa_notset', 'local_nit_assistant'), 'info', false);
}

echo $OUTPUT->footer();
