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
 * Usage and cost of the assistant over the last 30 days (estimated from the
 * token counts and the price table; the Anthropic console has the exact bill).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_assistant\config;
use local_nit_assistant\inbox\manager;
use local_nit_assistant\inbox\ui;
use local_nit_assistant\runtime\budget;

admin_externalpage_setup('local_nit_assistant_usage');
$url = new moodle_url('/local/nit_assistant/usage.php');

$check = null;
if (optional_param('check', 0, PARAM_BOOL)) {
    require_sesskey();
    $check = \local_nit_assistant\llm\check::run();
}

$usage = manager::usage(30);
$money = static fn(float $v): string => '$' . number_format($v, 2);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('usage', 'local_nit_assistant'));
echo ui::tabs('usage');

// Is the AI reachable? The test result, the last error the chat met, and the test button.
if ($check !== null) {
    echo $check['ok']
        ? $OUTPUT->notification(get_string('check_ok', 'local_nit_assistant', (object) $check), 'success', false)
        : $OUTPUT->notification(get_string('check_failed', 'local_nit_assistant', s($check['error'])), 'error', false);
}
$lasterror = config::last_error();
if ($lasterror && $check === null) {
    echo $OUTPUT->notification(get_string('lasterror', 'local_nit_assistant', (object) [
        'time' => userdate($lasterror['time'], get_string('strftimedatetimeshort', 'langconfig')),
        'message' => s($lasterror['message']),
    ]), 'warning', false);
}
echo $OUTPUT->single_button(
    new moodle_url($url, ['check' => 1, 'sesskey' => sesskey()]),
    get_string('check_button', 'local_nit_assistant'),
    'post',
    ['type' => 'secondary']
);

$budget = config::daily_budget();
$cards = [
    get_string('usage_today', 'local_nit_assistant') =>
        $money((float) $DB->get_field('local_nit_asst_usage', 'spent', ['day' => budget::day()])) . ' / ' . $money($budget),
    get_string('usage_30days', 'local_nit_assistant') => $money((float) $usage['totals']['spent']),
    get_string('usage_conversations', 'local_nit_assistant') => (int) $usage['totals']['conversations'],
    get_string('usage_leads', 'local_nit_assistant') => (int) $usage['totals']['leads'],
    get_string('usage_handoffs', 'local_nit_assistant') => (int) $usage['totals']['handoffs'],
];
$html = '';
foreach ($cards as $label => $value) {
    $html .= html_writer::div(
        html_writer::div($label, 'small text-muted') . html_writer::div($value, 'h4 mb-0'),
        'border rounded p-3'
    );
}
echo html_writer::div($html, 'd-flex flex-wrap gap-3 mb-4');
echo html_writer::tag('p', get_string('usage_estimate', 'local_nit_assistant'), ['class' => 'small text-muted']);

if ($usage['models']) {
    $table = new html_table();
    $table->head = [get_string('col_model', 'local_nit_assistant'), get_string('col_calls', 'local_nit_assistant'),
        get_string('col_tokensin', 'local_nit_assistant'), get_string('col_tokensout', 'local_nit_assistant'),
        get_string('col_cost', 'local_nit_assistant')];
    foreach ($usage['models'] as $m) {
        $table->data[] = [s($m->model), (int) $m->calls, number_format((int) $m->tokensin), number_format((int) $m->tokensout),
            $money((float) $m->cost)];
    }
    echo html_writer::table($table);
}

if ($usage['days']) {
    $table = new html_table();
    $table->head = [get_string('col_day', 'local_nit_assistant'), get_string('col_cost', 'local_nit_assistant'),
        get_string('col_tokensin', 'local_nit_assistant'), get_string('col_tokensout', 'local_nit_assistant'),
        get_string('col_cacheread', 'local_nit_assistant')];
    foreach ($usage['days'] as $d) {
        $table->data[] = [s($d->day), $money((float) $d->spent), number_format((int) $d->tokensin),
            number_format((int) $d->tokensout), number_format((int) $d->cacheread)];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('usage_none', 'local_nit_assistant'), 'info', false);
}

echo $OUTPUT->footer();
