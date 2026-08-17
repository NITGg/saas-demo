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
 * Financial Reports: platform wallet + teacher withdrawal queue (US-FN-2-2).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\api\wallet;

require_login();
$context = context_system::instance();
require_capability('local/nit_finance:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/nit_finance/manage_withdrawals.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('financialreports', 'local_nit_finance'));
$PAGE->set_heading(get_string('financialreports', 'local_nit_finance'));

// Handle an action on a withdrawal.
$action = optional_param('action', '', PARAM_ALPHA);
$wid = optional_param('wid', 0, PARAM_INT);
if ($action !== '' && $wid > 0 && confirm_sesskey()) {
    $opts = [];
    if ($action === 'reject') {
        $opts['reason'] = required_param('reason', PARAM_TEXT);
    } else if ($action === 'pay') {
        $opts['reference'] = optional_param('reference', '', PARAM_TEXT);
    }
    wallet::process_withdrawal($USER->id, $wid, $action, $opts);
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

/**
 * Format a minor-unit amount as a decimal money string.
 *
 * @param int $minor
 * @return string
 */
function local_nit_finance_money(int $minor): string {
    return number_format($minor / 100, 2);
}

// Compose the platform wallet: Flex plugin (if installed) provides money-in + unconsumed value.
$payments = 0;
$undistributed = 0;
if (class_exists('\local_nit_flex\api\flex')) {
    $totals = \local_nit_flex\api\flex::money_totals();
    $payments = (int) ($totals['payments_minor'] ?? 0);
    $undistributed = (int) ($totals['undistributed_minor'] ?? 0);
}
$pw = wallet::platform($payments, $undistributed);
$withdrawals = wallet::list_withdrawals();

echo $OUTPUT->header();

// Platform wallet cards.
echo html_writer::start_div('', ['style' => 'display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px']);
$cards = [
    'currentmoney'        => $pw['current_money_minor'],
    'undistributedmoney'  => $pw['undistributed_money_minor'],
    'teachersmoney'       => $pw['teachers_money_minor'],
    'platformearnings'    => $pw['platform_earnings_minor'],
    'totalpaidout'        => $pw['total_paid_out_minor'],
];
foreach ($cards as $key => $minor) {
    echo html_writer::div(
        html_writer::div(get_string($key, 'local_nit_finance'), 'text-muted small') .
        html_writer::div(local_nit_finance_money($minor) . ' EGP', 'h4 mt-1'),
        'card p-3', ['style' => 'min-width:170px;flex:1']
    );
}
echo html_writer::end_div();

// Withdrawal queue.
echo $OUTPUT->heading(get_string('withdrawals', 'local_nit_finance'), 3);
if (!$withdrawals) {
    echo $OUTPUT->notification(get_string('nowithdrawals', 'local_nit_finance'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('teacher', 'local_nit_finance'),
        get_string('amount', 'local_nit_finance'),
        get_string('method', 'local_nit_finance'),
        get_string('status', 'local_nit_finance'),
        get_string('actions', 'local_nit_finance'),
    ];
    foreach ($withdrawals as $w) {
        $statuslabel = get_string('status_' . $w['status'], 'local_nit_finance');
        $buttons = '';
        if ($w['status'] === 'pending') {
            $buttons .= local_nit_finance_action_button($PAGE->url, 'approve', $w['id'], get_string('approve', 'local_nit_finance'));
        }
        if ($w['status'] === 'approved') {
            $buttons .= local_nit_finance_action_button($PAGE->url, 'pay', $w['id'], get_string('pay', 'local_nit_finance'));
        }
        if (in_array($w['status'], ['pending', 'approved'], true)) {
            $buttons .= local_nit_finance_reject_button($PAGE->url, $w['id']);
        }
        $table->data[] = [
            s($w['teacher_name']),
            local_nit_finance_money($w['amount_minor']) . ' EGP',
            s($w['method']),
            $statuslabel,
            $buttons,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

/**
 * A simple sesskey-protected action button (approve / pay).
 *
 * @param moodle_url $baseurl
 * @param string $action
 * @param int $wid
 * @param string $label
 * @return string
 */
function local_nit_finance_action_button(moodle_url $baseurl, string $action, int $wid, string $label): string {
    $url = new moodle_url($baseurl, ['action' => $action, 'wid' => $wid, 'sesskey' => sesskey()]);
    return html_writer::link($url, $label, ['class' => 'btn btn-sm btn-primary mr-1']) . ' ';
}

/**
 * A reject button with an inline reason prompt.
 *
 * @param moodle_url $baseurl
 * @param int $wid
 * @return string
 */
function local_nit_finance_reject_button(moodle_url $baseurl, int $wid): string {
    $formid = 'rej' . $wid;
    $out = html_writer::start_tag('form', [
        'method' => 'post', 'action' => $baseurl->out(false), 'style' => 'display:inline',
        'onsubmit' => "this.reason.value=prompt('Reason for rejection:')||'';return this.reason.value!==''",
    ]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reject']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'wid', 'value' => $wid]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reason', 'value' => '']);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $out .= html_writer::tag('button', get_string('reject', 'local_nit_finance'),
        ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']);
    $out .= html_writer::end_tag('form');
    return $out;
}
