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
 * The course details page — bassthalk.com/course/<id>: the price card, "عن الكورس /
 * المنتدى" and the lessons. Learners and visitors land here from course/view.php
 * ({@see \local_academy\local\course_route}); staff keep the real Moodle course.
 *
 * Public like the home page (behind the log-in when the site forces log-in): a
 * visitor sees what the course holds but cannot open a lesson, and its button
 * goes to the log-in, which comes back here. The page itself is drawn by the
 * theme's topics-format renderer (theme_nit\output\format_topics_renderer).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$id = required_param('id', PARAM_INT);

if ($id === (int) SITEID) {
    redirect(new moodle_url('/'));
}
$course = get_course($id);
$context = context_course::instance($course->id);
$url = new moodle_url('/local/academy/course.php', ['id' => $course->id]);

if (!empty($CFG->forcelogin)) {
    require_login();
}
if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
    throw new moodle_exception('coursehidden');
}

$visitor = !isloggedin() || isguestuser();
if (!$visitor && is_enrolled($context, null, '', true)) {
    // An enrolled learner: the usual course entry (last access, "course viewed").
    require_login($course, false);
    course_view($context);
}

$PAGE->set_course($course);
$PAGE->set_url($url);
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(format_string($course->fullname, true, ['context' => $context]));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-bth-course-page');

$renderer = $PAGE->get_renderer('format_topics');
if (!method_exists($renderer, 'render_course_details')) {
    // Another theme: Moodle's own course entry (summary + how to enrol).
    redirect(new moodle_url('/enrol/index.php', ['id' => $course->id]));
}

if ($visitor) {
    // After logging in (the button, or the navbar's log-in) come back to this course.
    $SESSION->wantsurl = $url->out(false);
}

echo $OUTPUT->header();
echo $renderer->render_course_details($course);
echo $OUTPUT->footer();
