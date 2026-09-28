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
 * Parent dashboard: pick a child, see their marks and quiz activity.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_parent\link_manager;
use local_parent\child_report;

require_login();

$childid = optional_param('child', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/parent/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('dashboard', 'local_parent'));
$PAGE->set_heading(get_string('dashboard', 'local_parent'));

$children = link_manager::list_children((int) $USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_parent'));

if (!$children) {
    echo $OUTPUT->notification(get_string('nochildren', 'local_parent'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Default to the first child; only accept a child actually linked to this parent.
if (!$childid || !in_array($childid, $children, true)) {
    $childid = (int) reset($children);
}

// Child switcher (only when there is more than one).
if (count($children) > 1) {
    echo html_writer::start_div('nit-parent-children', ['style' => 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1rem;']);
    foreach ($children as $cid) {
        $user = $DB->get_record('user', ['id' => $cid], '*', IGNORE_MISSING);
        if (!$user) {
            continue;
        }
        $active = ($cid == $childid);
        echo html_writer::link(
            new moodle_url('/local/parent/index.php', ['child' => $cid]),
            fullname($user),
            ['class' => 'btn ' . ($active ? 'btn-primary' : 'btn-outline-secondary')]
        );
    }
    echo html_writer::end_div();
}

// Authorize + load this child's data.
child_report::guard((int) $USER->id, $childid);
$childuser = $DB->get_record('user', ['id' => $childid], '*', MUST_EXIST);
echo $OUTPUT->heading(fullname($childuser), 3);

// ── Marks ────────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('marks', 'local_parent'), 4);
$grades = child_report::grades($childid);
if (!$grades) {
    echo html_writer::tag('p', get_string('nomarks', 'local_parent'), ['class' => 'text-muted']);
} else {
    $t = new html_table();
    $t->head = [get_string('col_course', 'local_parent'), get_string('col_grade', 'local_parent')];
    foreach ($grades as $g) {
        $grade = $g['grade'] . ($g['percentage'] !== null ? ' (' . $g['percentage'] . '%)' : '');
        $t->data[] = [$g['coursename'], $grade];
    }
    echo html_writer::table($t);
}

// ── Quiz activity ────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('quizactivity', 'local_parent'), 4);
$quizzes = child_report::quizzes($childid);
if (!$quizzes) {
    echo html_writer::tag('p', get_string('noquizzes', 'local_parent'), ['class' => 'text-muted']);
} else {
    $t = new html_table();
    $t->head = [
        get_string('col_quiz', 'local_parent'),
        get_string('col_course', 'local_parent'),
        get_string('col_score', 'local_parent'),
        get_string('col_taken', 'local_parent'),
        get_string('col_duration', 'local_parent'),
    ];
    foreach ($quizzes as $q) {
        $score = ($q['score'] !== null)
            ? format_float($q['score'], 2) . ' / ' . format_float($q['maxscore'], 2)
            : '—';
        $taken = $q['timestart'] ? userdate($q['timestart'], get_string('strftimedatetimeshort')) : '—';
        $duration = $q['duration'] !== null ? format_time($q['duration']) : '—';
        $t->data[] = [$q['quizname'], $q['coursename'], $score, $taken, $duration];
    }
    echo html_writer::table($t);
}

echo $OUTPUT->footer();
