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
 * Refund requests: approve (the amount less the fee goes to the student's wallet) or
 * reject with a reason (task 8). Site administration → Plugins → Local plugins → Finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\local\money;
use local_nit_finance\local\refunds;
use local_nit_finance\local\refunds_ui;

require_login();
$context = context_system::instance();
require_capability('local/nit_finance:manage', $context);

$status = optional_param('status', refunds::PENDING, PARAM_ALPHA);
$status = in_array($status, refunds::STATUSES, true) ? $status : '';
$url = new moodle_url('/local/nit_finance/manage_refunds.php', ['status' => $status]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('refundrequests', 'local_nit_finance'));
$PAGE->set_heading(get_string('refundrequests', 'local_nit_finance'));

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($action !== '' && $id > 0 && confirm_sesskey()) {
    try {
        refunds::process((int) $USER->id, $id, $action, optional_param('reason', '', PARAM_TEXT));
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

refunds_ui::require_js();
echo $OUTPUT->header();

// Status filter.
$tabs = [];
foreach (array_merge(refunds::STATUSES, ['']) as $s) {
    $label = $s === '' ? get_string('all') : get_string('status_' . $s, 'local_nit_finance');
    $tabs[] = html_writer::link(new moodle_url('/local/nit_finance/manage_refunds.php', ['status' => $s === '' ? 'all' : $s]),
        $label, ['class' => 'btn btn-sm ' . ($s === $status ? 'btn-primary' : 'btn-outline-secondary') . ' mr-1 me-1']);
}
echo html_writer::div(implode('', $tabs), 'mb-3');
echo html_writer::tag('p', html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'local_nit_finance_settings']),
    get_string('refundsettings', 'local_nit_finance') . ': ' . get_string('refunddays', 'local_nit_finance') . ' '
    . \local_nit_finance\config::refund_days() . ' · ' . get_string('refundfee', 'local_nit_finance') . ' '
    . format_float(\local_nit_finance\config::refund_fee_percent(), -1)));

$requests = refunds::list($status);
if (!$requests) {
    echo $OUTPUT->notification(get_string('norefundrequests', 'local_nit_finance'), 'info');
} else {
    $students = $DB->get_records_list('user', 'id', array_unique(array_column($requests, 'userid')), '',
        implode(',', \core_user\fields::for_name()->including('id', 'email')->get_required_fields()));
    $date = static fn(int $time): string => $time ? userdate($time, get_string('strftimedatetimeshort', 'langconfig')) : '';
    $table = new html_table();
    $table->head = [get_string('date', 'local_nit_finance'), get_string('refundstudent', 'local_nit_finance'),
        get_string('item', 'local_nit_finance'), get_string('refundsource', 'local_nit_finance'),
        get_string('refundpaid', 'local_nit_finance'), get_string('refundfeecol', 'local_nit_finance'),
        get_string('refundback', 'local_nit_finance'), get_string('refundreason', 'local_nit_finance'),
        get_string('status', 'local_nit_finance'), get_string('actions', 'local_nit_finance')];
    foreach ($requests as $r) {
        $student = $students[$r->userid] ?? null;
        $actions = '';
        if ($r->status === refunds::PENDING) {
            $a = ['student' => $student ? fullname($student) : '', 'item' => $r->itemname,
                'amount' => money::format((int) $r->refund_minor)];
            $actions = local_nit_finance_refund_button($url, (int) $r->id, 'approve',
                    get_string('approve', 'local_nit_finance'), 'btn-primary',
                    refunds_ui::confirm_attributes(get_string('refundapprovetitle', 'local_nit_finance'),
                        get_string('refundapproveconfirm', 'local_nit_finance', $a), get_string('approve', 'local_nit_finance')))
                . local_nit_finance_refund_button($url, (int) $r->id, 'reject',
                    get_string('reject', 'local_nit_finance'), 'btn-outline-danger',
                    refunds_ui::reason_attributes(get_string('refundrejecttitle', 'local_nit_finance', $a),
                        get_string('refundrejectprompt', 'local_nit_finance'), get_string('reject', 'local_nit_finance')));
        } else {
            $actions = s((string) $r->adminreason) . ($r->timeprocessed ? html_writer::div($date((int) $r->timeprocessed), 'small text-muted') : '');
        }
        $table->data[] = [
            $date((int) $r->timecreated),
            $student ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $student->id]), fullname($student))
                . html_writer::div(s($student->email), 'small text-muted') : '',
            get_string('itemtype_' . $r->itemtype, 'local_nit_finance') . ': ' . s($r->itemname)
                . html_writer::div(get_string('date', 'local_nit_finance') . ': ' . $date((int) $r->paidtime), 'small text-muted'),
            get_string('refundsource_' . $r->source, 'local_nit_finance'),
            money::format((int) $r->amount_minor),
            money::format((int) $r->fee_minor),
            html_writer::tag('strong', money::format((int) $r->refund_minor)),
            s((string) $r->reason),
            get_string('status_' . $r->status, 'local_nit_finance'),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

/**
 * An approve / reject button; refunds_ui shows the confirmation (or asks the reason) in a modal.
 *
 * @param moodle_url $url
 * @param int $id
 * @param string $action
 * @param string $label
 * @param string $class
 * @param array $attributes from refunds_ui::confirm_attributes() / reason_attributes()
 * @return string
 */
function local_nit_finance_refund_button(moodle_url $url, int $id, string $action, string $label, string $class,
        array $attributes): string {
    $out = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'style' => 'display:inline'] + $attributes);
    foreach (['action' => $action, 'id' => $id, 'reason' => '', 'sesskey' => sesskey()] as $name => $value) {
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $out .= html_writer::tag('button', $label, ['type' => 'submit', 'class' => 'btn btn-sm ' . $class . ' mr-1 me-1']);
    return $out . html_writer::end_tag('form');
}
