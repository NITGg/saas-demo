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
 * A Jitsi room's attendance log: every time anyone was in the call, one day at a time.
 * Each person is a bar across the day's time with their stretches (joined → left), and
 * how many times they came in and for how long. The whole day downloads as CSV.
 *
 * For the room's hosts (its teachers, admins) and live-monitoring supervisors.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use mod_jitsi\local\roomlog;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'jitsi');
$jitsi = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
if (!\mod_jitsi\local\presence::is_moderator($cm, (int) $USER->id)
        && !has_capability('local/academysessions:monitorlive', $context)) {
    throw new required_capability_exception($context, 'mod/jitsi:moderate', 'nopermissions', '');
}

$days = roomlog::days((int) $jitsi->id);
$day = optional_param('day', '', PARAM_RAW_TRIMMED);
if (!in_array($day, $days, true)) {
    $day = $days[0] ?? userdate(time(), '%Y-%m-%d');
}
$now = time();
$log = roomlog::day((int) $jitsi->id, $day, $now);
$time = fn(int $t) => userdate($t, get_string('strftimetime', 'langconfig'));
$s = fn(string $k, $a = null) => get_string($k, 'jitsi', $a);

// CSV: one row per stretch.
if (optional_param('download', '', PARAM_ALPHA) === 'csv') {
    $csv = new csv_export_writer();
    $csv->set_filename(clean_filename(format_string($jitsi->name) . ' ' . $day));
    $csv->add_data([get_string('name'), $s('log_role'), $s('log_joined'), $s('log_leftat'), $s('log_minutes')]);
    foreach ($log['people'] as $p) {
        foreach ($p['stretches'] as [$joined, $left]) {
            $csv->add_data([$p['name'], $p['host'] ? $s('log_host') : $s('log_student'), userdate($joined),
                $left ? userdate($left) : $s('log_stillin'), (int) floor((($left ?? $now) - $joined) / MINSECS)]);
        }
    }
    $csv->download_file();
    exit;
}

// The day's axis: from the first join to the last leave (or now).
$from = $to = 0;
foreach ($log['people'] as $p) {
    foreach ($p['stretches'] as [$joined, $left]) {
        $from = $from ? min($from, $joined) : $joined;
        $to = max($to, $left ?? $now);
    }
}
$span = max(MINSECS, $to - $from);
$pos = fn(int $t) => round(100 * ($t - $from) / $span, 2);

$hosts = $students = [];
$visits = 0;
foreach ($log['people'] as $p) {
    $segments = $parts = [];
    foreach ($p['stretches'] as [$joined, $left]) {
        $label = $time($joined) . ' – ' . ($left ? $time($left) : $s('log_stillin'));
        $parts[] = $label;
        $segments[] = ['start' => $pos($joined), 'size' => max(0.8, round(100 * (($left ?? $now) - $joined) / $span, 2)),
            'open' => $left === null, 'label' => $label];
    }
    $visits += $p['visits'];
    $row = [
        'name' => $p['name'],
        'role' => $p['host'] ? $s('log_host') : $s('log_student'),
        'isother' => false,
        'stateclass' => $p['open'] ? 'in' : 'out',
        'state' => $p['open'] ? $s('log_inside') : $s('log_left'),
        'summary' => $s('log_summary', ['visits' => $p['visits'], 'minutes' => (int) floor($p['seconds'] / MINSECS),
            'first' => $time($p['first'])]),
        'stretches' => implode(get_string('listsep', 'langconfig') . ' ', $parts),
        'segments' => $segments,
    ];
    if ($p['host']) {
        $hosts[] = $row;
    } else {
        $students[] = $row;
    }
}

$PAGE->set_url('/mod/jitsi/attendance.php', ['id' => $cm->id, 'day' => $day]);
$PAGE->set_context($context);
$PAGE->set_title($s('attendancelog') . ': ' . format_string($jitsi->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('jitsi_attendancelog');

echo $OUTPUT->header();
echo $OUTPUT->heading($s('attendancelog'));

if (!$days) {
    echo $OUTPUT->notification($s('log_empty'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$dayoptions = [];
foreach ($days as $d) {
    [$y, $m, $dd] = array_map('intval', explode('-', $d));
    $dayoptions[$d] = userdate(make_timestamp($y, $m, $dd), get_string('strftimedaydate', 'langconfig'));
}
echo html_writer::start_div('d-flex flex-wrap align-items-end gap-2 mb-3');
echo $OUTPUT->single_select(new moodle_url('/mod/jitsi/attendance.php', ['id' => $cm->id]), 'day', $dayoptions, $day,
    null, null, ['label' => $s('log_day')]);
echo html_writer::link(new moodle_url('/mod/jitsi/attendance.php', ['id' => $cm->id, 'day' => $day, 'download' => 'csv']),
    $s('log_download'), ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::end_div();

echo $OUTPUT->render_from_template('mod_jitsi/attendance', [
    'tiles' => [
        ['label' => $s('log_people'), 'value' => count($log['people'])],
        ['label' => $s('log_students'), 'value' => count($students)],
        ['label' => $s('log_hosts'), 'value' => count($hosts)],
        ['label' => $s('log_visits'), 'value' => $visits],
    ],
    'axis' => ['from' => $time($from), 'to' => $time($to), 'hasnow' => false, 'now' => 0,
        'planstart' => 0, 'plansize' => 0],
    'hosts' => $hosts,
    'hashosts' => (bool) $hosts,
    'students' => $students,
    'studentcount' => count($students),
]);
echo $OUTPUT->footer();
