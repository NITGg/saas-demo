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
 * Sign-ups per UTM source / medium / campaign.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$days = optional_param('days', 30, PARAM_INT);
$days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;

admin_externalpage_setup('local_nit_analytics_report', '', ['days' => $days]);

$rows = \local_nit_analytics\tracker::signups_by_source(time() - $days * DAYSECS);
$none = get_string('direct', 'local_nit_analytics');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('signupsreport', 'local_nit_analytics'));

$options = [];
foreach ([7, 30, 90, 365] as $d) {
    $options[$d] = get_string('lastndays', 'local_nit_analytics', $d);
}
echo $OUTPUT->single_select(new moodle_url('/local/nit_analytics/report.php'), 'days', $options, $days, null);

if (!$rows) {
    echo $OUTPUT->notification(get_string('nosignups', 'local_nit_analytics'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('source', 'local_nit_analytics'),
        get_string('medium', 'local_nit_analytics'),
        get_string('campaign', 'local_nit_analytics'),
        get_string('signups', 'local_nit_analytics'),
    ];
    foreach ($rows as $row) {
        $table->data[] = [
            s($row['source'] !== '' ? $row['source'] : $none),
            s($row['medium']),
            s($row['campaign']),
            $row['signups'],
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
