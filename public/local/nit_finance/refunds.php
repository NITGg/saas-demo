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
 * Student: ask for a refund of a recent purchase, and see the answers (task 8).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\local\money;
use local_nit_finance\local\refunds;

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}
$context = context_system::instance();
$url = new moodle_url('/local/nit_finance/refunds.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myrefunds', 'local_nit_finance'));
$PAGE->set_heading(get_string('myrefunds', 'local_nit_finance'));

$userid = (int) $USER->id;
if (data_submitted() && confirm_sesskey()) {
    try {
        refunds::request($userid, required_param('key', PARAM_RAW_TRIMMED), optional_param('reason', '', PARAM_TEXT));
        redirect($url, get_string('refundrequested', 'local_nit_finance'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$date = static fn(int $time): string => userdate($time, get_string('strftimedatetimeshort', 'langconfig'));
$days = \local_nit_finance\config::refund_days();

echo $OUTPUT->header();

if ($days <= 0) {
    echo $OUTPUT->notification(get_string('refundsclosed', 'local_nit_finance'), 'info');
} else {
    echo html_writer::tag('p', get_string('refundsintro', 'local_nit_finance', $days));
    echo $OUTPUT->heading(get_string('refundable', 'local_nit_finance'), 3);
    $items = refunds::refundable($userid);
    if (!$items) {
        echo $OUTPUT->notification(get_string('norefundable', 'local_nit_finance'), 'info');
    } else {
        $table = new html_table();
        $table->head = [get_string('item', 'local_nit_finance'), get_string('refundsource', 'local_nit_finance'),
            get_string('refundpaid', 'local_nit_finance'), get_string('refundfeecol', 'local_nit_finance'),
            get_string('refundback', 'local_nit_finance'), get_string('refunddeadline', 'local_nit_finance'), ''];
        foreach ($items as $item) {
            $confirm = get_string('refundconfirm', 'local_nit_finance',
                ['item' => $item['itemname'], 'amount' => money::format($item['refund_minor'])]);
            $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
                'onsubmit' => 'return confirm(' . json_encode($confirm) . ')']);
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'key', 'value' => $item['key']]);
            $form .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'class' => 'form-control mb-1',
                'maxlength' => 500, 'placeholder' => get_string('refundreason', 'local_nit_finance')]);
            $form .= html_writer::tag('button', get_string('requestrefund', 'local_nit_finance'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']);
            $form .= html_writer::end_tag('form');
            $table->data[] = [
                get_string('itemtype_' . $item['itemtype'], 'local_nit_finance') . ': ' . s($item['itemname']),
                get_string('refundsource_' . $item['source'], 'local_nit_finance'),
                money::format($item['amount_minor']),
                money::format($item['fee_minor']),
                html_writer::tag('strong', money::format($item['refund_minor'])),
                $date($item['deadline']),
                $form,
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->heading(get_string('myrefundrequests', 'local_nit_finance'), 3);
$requests = refunds::list('', $userid);
if (!$requests) {
    echo $OUTPUT->notification(get_string('norefundrequests', 'local_nit_finance'), 'info');
} else {
    $table = new html_table();
    $table->head = [get_string('date', 'local_nit_finance'), get_string('item', 'local_nit_finance'),
        get_string('refundback', 'local_nit_finance'), get_string('status', 'local_nit_finance'),
        get_string('refundadminreason', 'local_nit_finance')];
    foreach ($requests as $r) {
        $table->data[] = [
            $date((int) $r->timecreated),
            get_string('itemtype_' . $r->itemtype, 'local_nit_finance') . ': ' . s($r->itemname),
            money::format((int) $r->refund_minor),
            get_string('status_' . $r->status, 'local_nit_finance'),
            s((string) $r->adminreason),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
