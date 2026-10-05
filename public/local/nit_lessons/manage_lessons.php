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
 * Admin: every live lesson, its earning, and "return the Flex" (reverse a used Flex: the
 * student gets it back and the teacher/platform shares are taken back from their wallets).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_finance\local\money;
use local_nit_lessons\api\lessons;
use local_nit_lessons\local\lesson_view;

admin_externalpage_setup('local_nit_lessons_manage');
require_capability('local/nit_lessons:managesettings', context_system::instance());

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_lessons', $a);
$status = optional_param('status', '', PARAM_ALPHAEXT);
$pageurl = new moodle_url('/local/nit_lessons/manage_lessons.php', ['status' => $status]);
$PAGE->set_url($pageurl);

if (optional_param('action', '', PARAM_ALPHA) === 'reverse' && confirm_sesskey()) {
    try {
        lessons::reverse_flex(required_param('lessonid', PARAM_INT), (int) $USER->id,
            required_param('reason', PARAM_TEXT));
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($pageurl, $s('reversed'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($s('managelessons'));

if (!\local_nit_lessons\room\jitsi_room::configured()) {
    echo $OUTPUT->notification($s('roomnotconfigured', (new moodle_url('/admin/settings.php',
        ['section' => 'local_nit_lessons_settings']))->out(false)), 'warning');
}

$options = '';
foreach (lesson_view::filter($status) as $o) {
    $options .= html_writer::tag('option', s($o['label']), ['value' => $o['value']] + ($o['selected'] ? ['selected' => 'selected'] : []));
}
echo html_writer::tag('form', html_writer::tag('select', $options, ['name' => 'status', 'class' => 'form-select w-auto',
    'onchange' => 'this.form.submit()']), ['method' => 'get', 'class' => 'mb-3']);

$where = $status !== '' ? 'WHERE l.status = :status' : '';
$sql = "SELECT l.*, e.teacher_amount_minor, e.platform_amount_minor, e.status AS earningstatus
          FROM {nit_lesson} l
     LEFT JOIN {nit_earning} e ON e.source = 'lesson' AND e.lessonid = l.id AND e.status = 'active'
         $where
      ORDER BY l.timecreated DESC";
$rows = $DB->get_records_sql($sql, ['status' => $status], 0, 300);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = ['#', $s('student'), $s('teacher'), $s('field_subject'), $s('when'), get_string('status'),
    $s('earning'), get_string('actions')];
$name = function(int $userid): string {
    $user = core_user::get_user($userid);
    return $user ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $userid]), fullname($user)) : '—';
};
foreach ($rows as $r) {
    $when = (int) $r->confirmed_time ?: (int) $r->requested_time;
    $earning = $r->earningstatus === 'active'
        ? $s('earningsplit', (object) ['teacher' => money::format((int) $r->teacher_amount_minor),
            'platform' => money::format((int) $r->platform_amount_minor)])
        : '—';
    $action = '';
    if ($r->earningstatus === 'active') {
        $action = html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false),
                'class' => 'd-flex gap-2', 'onsubmit' => 'return confirm(' . json_encode($s('confirmreverse')) . ');'])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reverse'])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'lessonid', 'value' => $r->id])
            . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'required' => 'required',
                'class' => 'form-control form-control-sm', 'placeholder' => $s('field_reason')])
            . html_writer::tag('button', $s('reverseflex'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger'])
            . html_writer::end_tag('form');
    }
    $table->data[] = [
        $r->id,
        $name((int) $r->studentid),
        $name((int) $r->teacherid),
        format_string($r->subject),
        userdate($when, get_string('strftimedatetimeshort', 'langconfig')),
        $s('lstat_' . $r->status) . ($r->flex_state !== 'none' ? ' · ' . $s('flexstate_' . $r->flex_state) : ''),
        $earning,
        $action,
    ];
}
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification($s('nolessons_admin'), 'info');
}
echo $OUTPUT->footer();
