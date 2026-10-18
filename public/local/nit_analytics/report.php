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
 * Acquisition funnel per UTM source / medium / campaign, with CSV export.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_analytics\funnel;

$days = optional_param('days', 30, PARAM_INT);
$days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
$download = optional_param('download', '', PARAM_ALPHA);

admin_externalpage_setup('local_nit_analytics_report', '', ['days' => $days]);

$s = fn(string $key) => get_string($key, 'local_nit_analytics');
$rows = funnel::export_rows(funnel::rows(time() - $days * DAYSECS), $s('direct'));
$columns = [];
foreach (['source', 'medium', 'campaign', 'landings', 'signups', 'signuprate', 'buyers', 'buyerrate',
        'purchases', 'revenue'] as $key) {
    $columns[$key] = $s('col_' . $key);
}

if ($download === 'csv') {
    \core\dataformat::download_data('funnel-last-' . $days . '-days', 'csv', $columns, $rows);
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($s('funnelreport'));
echo html_writer::tag('p', $s('funnelintro'), ['class' => 'text-muted']);

$options = [];
foreach ([7, 30, 90, 365] as $d) {
    $options[$d] = get_string('lastndays', 'local_nit_analytics', $d);
}
echo $OUTPUT->single_select(new moodle_url('/local/nit_analytics/report.php'), 'days', $options, $days, null);

if (!$rows) {
    echo $OUTPUT->notification($s('nodata'), 'info');
} else {
    $table = new html_table();
    $table->head = array_values($columns);
    foreach ($rows as $row) {
        $table->data[] = array_map(fn($v) => s((string) $v), array_values($row));
    }
    echo html_writer::table($table);
    echo $OUTPUT->single_button(new moodle_url('/local/nit_analytics/report.php', ['days' => $days, 'download' => 'csv']),
        $s('downloadcsv'), 'get');
}

echo $OUTPUT->footer();
