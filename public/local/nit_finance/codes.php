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
 * Admin: make, list, download and disable codes.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_nit_finance\local\catalog;
use local_nit_finance\local\codes;
use local_nit_finance\local\money;

admin_externalpage_setup('local_nit_finance_codes');
$context = context_system::instance();
require_capability('local/nit_finance:manage', $context);

$status = optional_param('status', '', PARAM_ALPHA);
$batch = optional_param('batch', '', PARAM_ALPHANUMEXT);
$search = optional_param('q', '', PARAM_RAW_TRIMMED);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
$baseurl = new moodle_url('/local/nit_finance/codes.php', array_filter(['status' => $status, 'batch' => $batch, 'q' => $search]));
$s = fn(string $key, $a = null) => get_string($key, 'local_nit_finance', $a);

/**
 * Item label for a code row.
 *
 * @param stdClass $row
 * @return string
 */
$itemlabel = function(stdClass $row) use ($s): string {
    if ($row->itemtype === catalog::WALLET) {
        return $s('itemtype_wallet');
    }
    try {
        $item = catalog::item($row->itemtype, (int) $row->itemid);
    } catch (moodle_exception $e) {
        return $s('itemtype_' . $row->itemtype) . ' #' . $row->itemid;
    }
    return $s('itemtype_' . $row->itemtype) . ': ' . ($row->itemtype === catalog::CM ? $item->coursename . ' › ' : '') . $item->name;
};

// Disable an unused code.
if (($disable = optional_param('disable', 0, PARAM_INT)) && confirm_sesskey()) {
    codes::disable($disable);
    redirect($baseurl);
}

// Filters → SQL.
$where = [];
$params = [];
if (in_array($status, [codes::STATUS_ACTIVE, codes::STATUS_USED, codes::STATUS_DISABLED], true)) {
    $where[] = 'c.status = :status';
    $params['status'] = $status;
}
if ($batch !== '') {
    $where[] = 'c.batch = :batch';
    $params['batch'] = $batch;
}
if ($search !== '') {
    $where[] = $DB->sql_like('c.code', ':q', false);
    $params['q'] = '%' . $DB->sql_like_escape(strtoupper($search)) . '%';
}
$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// CSV download of the current filter.
if (optional_param('download', 0, PARAM_BOOL)) {
    $rows = $DB->get_records_sql("SELECT c.* FROM {nit_access_code} c $wheresql ORDER BY c.id", $params);
    // BOM so Excel reads the Arabic labels as UTF-8.
    $csv = new csv_export_writer('comma', '"', 'application/download', true);
    $csv->set_filename('codes-' . ($batch !== '' ? $batch : date('Ymd')));
    $csv->add_data([$s('code'), $s('item'), $s('amount'), get_string('status'), $s('codes_expires'), $s('note')]);
    foreach ($rows as $row) {
        $csv->add_data([$row->code, $itemlabel($row), money::to_major((int) $row->amount_minor),
            $s('codestatus_' . $row->status), $row->timeexpires ? userdate($row->timeexpires, '%Y-%m-%d') : '', $row->note]);
    }
    $csv->download_file();
    exit;
}

$form = new \local_nit_finance\form\codes_form($PAGE->url);
if ($data = $form->get_data()) {
    $type = $data->itemtype;
    $itemid = $type === catalog::CM ? (int) $data->cmid : ($type === catalog::COURSE ? (int) $data->courseid : 0);
    $amount = trim((string) $data->amount) === '' && $type === catalog::CM
        ? catalog::price($itemid) : (int) money::to_minor($data->amount);
    $made = codes::generate($type, $itemid, (int) $data->count, $amount,
        empty($data->timeexpires) ? 0 : (int) $data->timeexpires + DAYSECS - 1, (string) $data->note, (int) $USER->id);
    redirect(new moodle_url('/local/nit_finance/codes.php', ['batch' => $made[0]->batch]),
        $s('codes_created', count($made)), null, \core\output\notification::NOTIFY_SUCCESS);
}

$total = $DB->count_records_sql("SELECT COUNT(1) FROM {nit_access_code} c $wheresql", $params);
$rows = $DB->get_records_sql(
    "SELECT c.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
       FROM {nit_access_code} c
  LEFT JOIN {user} u ON u.id = c.usedby
       $wheresql
   ORDER BY c.id DESC", $params, $page * $perpage, $perpage);

echo $OUTPUT->header();
echo $OUTPUT->heading($s('codesadmin'));
$form->display();

echo $OUTPUT->heading($s('codes_list'), 3);

// Filter bar.
$statusoptions = ['' => $s('allstatuses')];
foreach ([codes::STATUS_ACTIVE, codes::STATUS_USED, codes::STATUS_DISABLED] as $st) {
    $statusoptions[$st] = $s('codestatus_' . $st);
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'd-flex flex-wrap gap-2 align-items-center mb-3']);
echo html_writer::select($statusoptions, 'status', $status, false, ['class' => 'form-select w-auto']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'q', 'value' => $search, 'dir' => 'ltr',
    'placeholder' => $s('code'), 'class' => 'form-control w-auto']);
if ($batch !== '') {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batch', 'value' => $batch]);
    echo html_writer::span(s($batch), 'badge bg-secondary');
}
echo html_writer::tag('button', $s('filter'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url($baseurl, ['download' => 1]), $s('codes_download'), ['class' => 'btn btn-outline-primary']);
echo html_writer::end_tag('form');

if (!$rows) {
    echo $OUTPUT->notification($s('codes_none'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [$s('code'), $s('item'), $s('amount'), get_string('status'), $s('usedby'), $s('codes_expires'),
        $s('created'), $s('note'), ''];
    foreach ($rows as $row) {
        $action = '';
        if ($row->status === codes::STATUS_ACTIVE) {
            $action = html_writer::link(new moodle_url($baseurl, ['disable' => $row->id, 'sesskey' => sesskey()]),
                $s('disable'), ['class' => 'btn btn-sm btn-outline-danger']);
        }
        $table->data[] = [
            html_writer::tag('code', s($row->code), ['dir' => 'ltr', 'class' => 'text-nowrap']),
            s($itemlabel($row)),
            money::format((int) $row->amount_minor),
            $s('codestatus_' . $row->status),
            $row->usedby ? s(fullname($row)) . '<br><small>' . userdate($row->timeused, get_string('strftimedatetimeshort', 'langconfig')) . '</small>' : '',
            $row->timeexpires ? userdate($row->timeexpires, get_string('strftimedatefullshort', 'langconfig')) : '-',
            userdate($row->timecreated, get_string('strftimedatefullshort', 'langconfig')),
            s($row->note),
            $action,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
