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
 * Admin: the three wallets — platform, teachers, students — with their
 * history, and manual credit for a student wallet.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_finance\local\money;
use local_nit_finance\local\output;
use local_nit_finance\local\teacher_share;
use local_nit_finance\local\wallets;

admin_externalpage_setup('local_nit_finance_wallets');
require_capability('local/nit_finance:manage', context_system::instance());

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_finance', $a);
$type = optional_param('type', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$pageurl = new moodle_url('/local/nit_finance/wallets.php');

$form = new \local_nit_finance\form\topup_form($pageurl);
if ($data = $form->get_data()) {
    $minor = (int) money::to_minor($data->amount);
    $signed = $data->direction === 'take' ? -$minor : $minor;
    $target = (int) $data->userid;
    try {
        wallets::locked('student_' . $target, fn() => wallets::move(wallets::STUDENT, $target, $signed,
            $signed > 0 ? wallets::KIND_TOPUP : wallets::KIND_ADJUSTMENT, ['note' => (string) $data->note]));
    } catch (\local_nit_finance\exception\finance_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url($pageurl, ['type' => wallets::STUDENT, 'userid' => $target]),
        $s('wallets_done', money::format(wallets::balance(wallets::STUDENT, $target))),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

/**
 * Sum of all balances of one wallet type.
 *
 * @param string $type
 * @return int
 */
$total = fn(string $type): int => (int) $DB->get_field_sql(
    'SELECT COALESCE(SUM(balance_minor), 0) FROM {nit_wallet} WHERE ownertype = ?', [$type]);

/**
 * Ledger table.
 *
 * @param array $lines
 * @return string
 */
$ledger = function(array $lines) use ($s): string {
    if (!$lines) {
        return html_writer::tag('p', $s('notransactions'), ['class' => 'text-muted']);
    }
    $table = new html_table();
    $table->head = [$s('date'), $s('details'), $s('amount'), $s('balanceafter')];
    foreach (output::history($lines) as $line) {
        $table->data[] = [$line['date'], s($line['kind']) . ($line['note'] !== '' ? ' · ' . $line['note'] : ''),
            html_writer::span($line['amount'], $line['positive'] ? 'text-success' : 'text-danger', ['dir' => 'ltr']),
            html_writer::span($line['balance'], '', ['dir' => 'ltr'])];
    }
    return html_writer::table($table);
};

echo $OUTPUT->header();
echo $OUTPUT->heading($s('walletsadmin'));

// One wallet's history.
if (in_array($type, [wallets::PLATFORM, wallets::TEACHER, wallets::STUDENT], true)) {
    $title = $s('wallet_' . $type);
    if ($type !== wallets::PLATFORM && ($user = core_user::get_user($userid))) {
        $title .= ': ' . fullname($user);
    }
    echo html_writer::link($pageurl, '← ' . $s('walletsadmin'));
    echo $OUTPUT->heading($title . ' — ' . money::format(wallets::balance($type, $userid)), 3);
    echo $ledger(wallets::history($type, $userid, 200));
    echo $OUTPUT->footer();
    exit;
}

// Totals.
$cards = [
    [$s('wallet_platform'), wallets::balance(wallets::PLATFORM), new moodle_url($pageurl, ['type' => wallets::PLATFORM])],
    [$s('wallets_teacherstotal'), $total(wallets::TEACHER), null],
    [$s('wallets_studentstotal'), $total(wallets::STUDENT), null],
];
echo html_writer::start_div('row g-3 mb-4');
foreach ($cards as [$label, $amount, $link]) {
    $body = html_writer::div($label, 'text-muted small') . html_writer::div(money::format($amount), 'fs-3 fw-bold');
    if ($link) {
        $body .= html_writer::link($link, $s('wallets_ledger'), ['class' => 'small']);
    }
    echo html_writer::div(html_writer::div(html_writer::div($body, 'card-body'), 'card h-100'), 'col-md-4');
}
echo html_writer::end_div();

$form->display();

// Teachers: their share and earnings.
echo $OUTPUT->heading($s('wallets_teachers'), 3);
$default = teacher_share::default_percent();
$teachers = $DB->get_records_sql(
    "SELECT w.userid, w.balance_minor, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
            u.middlename, u.alternatename, u.email
       FROM {nit_wallet} w
       JOIN {user} u ON u.id = w.userid
      WHERE w.ownertype = :type
   ORDER BY w.balance_minor DESC", ['type' => wallets::TEACHER]);
if (!$teachers) {
    echo html_writer::tag('p', $s('wallets_noteachers'), ['class' => 'text-muted']);
} else {
    $table = new html_table();
    $table->head = [$s('name'), $s('wallets_ownpercent'), $s('balance'), ''];
    foreach ($teachers as $t) {
        $own = trim((string) $DB->get_field_sql(
            "SELECT d.data FROM {user_info_data} d JOIN {user_info_field} f ON f.id = d.fieldid
              WHERE f.shortname = ? AND d.userid = ?", [teacher_share::FIELD, $t->userid]));
        $hasown = preg_match('/^\d{1,3}$/', $own) && (int) $own <= 100;
        $table->data[] = [
            html_writer::link(new moodle_url('/user/editadvanced.php', ['id' => $t->userid]), fullname($t)),
            $hasown ? (int) $own . '%' : $s('wallets_default', $default),
            money::format((int) $t->balance_minor),
            html_writer::link(new moodle_url($pageurl, ['type' => wallets::TEACHER, 'userid' => $t->userid]),
                $s('wallets_ledger')),
        ];
    }
    echo html_writer::table($table);
}

// Students with a wallet.
echo $OUTPUT->heading($s('wallets_students'), 3);
$students = $DB->get_records_sql(
    "SELECT w.userid, w.balance_minor, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
            u.middlename, u.alternatename, u.email
       FROM {nit_wallet} w
       JOIN {user} u ON u.id = w.userid
      WHERE w.ownertype = :type
   ORDER BY w.balance_minor DESC, w.timemodified DESC", ['type' => wallets::STUDENT], 0, 200);
if (!$students) {
    echo html_writer::tag('p', $s('wallets_nostudents'), ['class' => 'text-muted']);
} else {
    $table = new html_table();
    $table->head = [$s('name'), get_string('email'), $s('balance'), ''];
    foreach ($students as $st) {
        $table->data[] = [
            fullname($st),
            s($st->email),
            money::format((int) $st->balance_minor),
            html_writer::link(new moodle_url($pageurl, ['type' => wallets::STUDENT, 'userid' => $st->userid]),
                $s('wallets_ledger')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
