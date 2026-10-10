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
 * The recordings of a live lesson, to watch it again (its teacher or student only;
 * the student once the lesson is over). Reached from the lesson card in "حصصي" /
 * "طلبات الطلاب" (local/nit_lessons/student.php).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_lessons\api\lessons;
use local_nit_lessons\local\lesson_view;

require_login(null, false);
$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/nit_lessons/recording.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$userid = (int) $USER->id;
try {
    $lesson = lessons::get($userid, $id); // Throws for anyone but the lesson's teacher or student.
} catch (moodle_exception $e) {
    throw new moodle_exception('err_forbidden', 'local_nit_lessons');
}
$role = (int) $lesson['teacherid'] === $userid ? 'teacher' : 'student';
if ($role === 'student' && (int) $lesson['studentid'] !== $userid) {
    throw new moodle_exception('err_forbidden', 'local_nit_lessons');
}
$back = new moodle_url('/local/nit_lessons/student.php', ['tab' => $role === 'teacher' ? 'teaching' : 'lessons']);

$title = get_string('recordingsof', 'local_nit_lessons', $lesson['subject']);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$fmt = get_string('strftimedaydatetime', 'langconfig');
$recordings = [];
foreach (lesson_view::recordings($lesson, $role) as $i => $rec) {
    $recordings[] = [
        'title' => $rec['title'],
        'when' => userdate($rec['timecreated'], $fmt),
        'duration' => $rec['duration'] > 0 ? format_time($rec['duration']) : '',
        'embedurl' => $rec['embed_url'],
        'first' => $i === 0,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_lessons/recordings_page', [
    'title' => $title,
    'with' => get_string($role === 'student' ? 'withteacher' : 'withstudent', 'local_nit_lessons',
        $role === 'student' ? $lesson['teacher_name'] : $lesson['student_name']),
    'when' => userdate($lesson['effective_time'], $fmt),
    'backurl' => $back->out(false),
    'recordings' => $recordings,
    'hasrecordings' => !empty($recordings),
]);
echo $OUTPUT->footer();
