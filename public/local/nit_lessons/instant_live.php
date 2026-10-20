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
 * Admin: instant lessons live — teachers online now, requests waiting, and the last 24 hours
 * (match rate, time to match, no-shows, subjects students ask for that nobody covers).
 * Refreshes itself every 15 seconds.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_lessons\service\instant_service;

admin_externalpage_setup('local_nit_lessons_instant');
require_capability('local/nit_lessons:managesettings', context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_lessons/instant_live.php'));

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_lessons', $a);
$service = new instant_service();

// CSV export of every request (and what happened to it) in the chosen period.
$days = optional_param('days', 0, PARAM_INT);
if (in_array($days, [7, 30, 90, 365], true)) {
    $rows = $service->export_rows(time() - $days * DAYSECS);
    $columns = [];
    foreach (['id', 'created', 'student', 'subject', 'mode', 'trial', 'status', 'waves', 'asked', 'declined', 'missed',
            'noshows', 'teacher', 'secondstomatch', 'lessonid'] as $key) {
        $columns[$key] = $s('export_' . $key);
    }
    \core\dataformat::download_data('instant-lessons-' . $days . 'd', 'csv', $columns, $rows);
    die;
}
$view = $service->live_view();

echo $OUTPUT->header();
echo html_writer::empty_tag('meta', ['http-equiv' => 'refresh', 'content' => '15']);
echo $OUTPUT->heading($s('instantlive'));
if (!$service->enabled()) {
    echo $OUTPUT->notification($s('err_instantoff'), 'warning');
}

$st = $view['stats'];
$cards = [
    [$s('live_requests'), $st['requests']],
    [$s('live_matchrate'), $st['matchrate'] . '%'],
    [$s('live_avgwait'), $s('live_seconds', $st['avgwait'])],
    [$s('live_noshows'), $st['noshows']],
    [$s('live_online'), count($view['teachers'])],
    [$s('live_waiting'), count($view['requests'])],
];
echo html_writer::start_div('d-flex flex-wrap gap-3 mb-4');
foreach ($cards as [$label, $value]) {
    echo html_writer::div(html_writer::div(s($value), 'h3 mb-0') . html_writer::div(s($label), 'small text-muted'),
        'card p-3', ['style' => 'min-width:150px']);
}
echo html_writer::end_div();
echo html_writer::tag('p', $s('live_last24'), ['class' => 'text-muted small']);

$table = function (array $head, array $rows, string $empty) use ($OUTPUT): void {
    if (!$rows) {
        echo $OUTPUT->notification($empty, 'info', false);
        return;
    }
    $t = new html_table();
    $t->head = $head;
    $t->data = $rows;
    echo html_writer::table($t);
};

echo $OUTPUT->heading($s('live_waiting'), 3);
$table([$s('live_student'), $s('live_subject'), $s('live_waited'), $s('live_wave'), $s('live_ringing'), $s('live_left')],
    array_map(fn($r) => [s($r['student']), s($r['subject']), $s('live_seconds', $r['waiting']), $r['wave'], $r['ringing'],
        $s('live_seconds', $r['secondsleft'])], $view['requests']), $s('live_norequests'));

echo $OUTPUT->heading($s('live_online'), 3);
$table([$s('live_teacher'), $s('live_subjects'), $s('live_state'), $s('live_acceptance'), $s('live_seen')],
    array_map(fn($t) => [s($t['name']), s($t['subjects']), $t['busy'] ? $s('live_busy') : $s('live_free'),
        $t['acceptance'] . '%', $s('live_secondsago', $t['lastseen'])], $view['teachers']), $s('live_noteachers'));

echo $OUTPUT->heading($s('live_bysubject'), 3);
$table([$s('live_subject'), $s('live_requests'), $s('live_matched'), $s('live_unmatched')],
    array_map(fn($r) => [s($r['subject']), $r['requests'], $r['matched'], $r['unmatched']], $view['subjects']),
    $s('live_nodata'));

$links = [];
foreach ([7, 30, 90, 365] as $d) {
    $links[] = html_writer::link(new moodle_url('/local/nit_lessons/instant_live.php', ['days' => $d]),
        $s('export_days', $d), ['class' => 'btn btn-outline-secondary btn-sm']);
}
echo $OUTPUT->heading($s('export_heading'), 3);
echo html_writer::div(implode(' ', $links), 'd-flex flex-wrap gap-2 mb-4');

echo $OUTPUT->footer();
