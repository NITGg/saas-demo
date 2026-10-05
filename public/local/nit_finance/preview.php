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
 * Free preview of a paid video lesson: the first minutes play, then the player
 * is removed and the student is offered to unlock the lesson.
 *
 * Open to anyone logged in who may not open the lesson yet — not bought, or not
 * enrolled in the paid course. Watching the preview does not log a view, set
 * completion or save video progress.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\local\preview;

$cmid = required_param('cmid', PARAM_INT);

// Site-level login only: the student may not be enrolled in the course.
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot watch previews');
}

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
$coursecontext = context_course::instance($course->id);
if ((!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext))
        || (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext))) {
    throw new moodle_exception('err_itemnotfound', 'local_nit_finance');
}

$url = preview::url((int) $cm->id);
$buyurl = new moodle_url('/local/nit_finance/buy.php', ['cmid' => $cm->id]);

// No preview for this user: the buy page opens the lesson when they own it.
$seconds = preview::for_user((int) $USER->id, $cm);
if ($seconds <= 0) {
    redirect($buyurl);
}

$name = format_string($cm->name, true, ['context' => $cm->context]);
$coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

$PAGE->set_url($url);
// Like the buy page: no course tabs or course index around it.
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('previewtitle', 'local_nit_finance') . ': ' . $name);
$PAGE->set_heading($coursename);

$iframe = '';
try {
    $play = preview::playback($cm, $USER, $seconds);
    if ($play['provider'] === 'vimeo') {
        $src = $play['embedurl'];
        $allow = 'autoplay; fullscreen; picture-in-picture';
    } else {
        $src = 'https://player.vdocipher.com/v2/?otp=' . rawurlencode($play['otp'])
            . '&playbackInfo=' . rawurlencode($play['playbackInfo']);
        $allow = 'encrypted-media';
    }
    $iframe = html_writer::tag('iframe', '', ['src' => $src, 'title' => $name, 'allow' => $allow,
        'allowfullscreen' => 'allowfullscreen']);
    $PAGE->requires->js(new moodle_url('/local/nit_finance/js/preview.js'));
} catch (\Throwable $e) {
    debugging('local_nit_finance preview playback failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

$length = preview::label($seconds);
$context = [
    'coursename' => $coursename,
    'name' => $name,
    'provider' => $cm->modname,
    'seconds' => $seconds,
    'iframe' => $iframe,
    'badge' => get_string('previewbadge', 'local_nit_finance', $length),
    'intro' => get_string('previewintro', 'local_nit_finance', $length),
    'buyurl' => $buyurl->out(false),
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_finance/preview_page', $context);
echo $OUTPUT->footer();
