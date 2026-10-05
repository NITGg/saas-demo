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
 * "My live lessons" (teacher): lesson requests and booked lessons, with accept / suggest /
 * start / complete / cancel / reschedule.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_nit_lessons\api\lessons;
use local_nit_lessons\local\lesson_view;
use local_nit_lessons\service\teacher_service;

require_login(null, false);
$status = optional_param('status', '', PARAM_ALPHAEXT);
$url = new moodle_url('/local/nit_lessons/my_lessons.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mylessons', 'local_nit_lessons'));
$PAGE->set_heading(get_string('mylessons', 'local_nit_lessons'));
local_nit_flex_require_enabled();

$userid = (int) $USER->id;
$teachers = new teacher_service();
if (!$teachers->is_teacher($userid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notateacher', 'local_nit_lessons'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$cards = [];
foreach (lessons::my_lessons($userid, 'teacher', $status) as $lesson) {
    $cards[] = lesson_view::card($lesson, 'teacher');
}
$profile = $teachers->profile($userid);

$PAGE->requires->strings_for_js(['noslots', 'picktimefirst', 'field_note', 'field_completenote', 'field_reason',
    'field_reasonoptional'], 'local_nit_lessons');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_lessons/my_lessons', [
    'sesskey' => sesskey(),
    'actionurl' => (new moodle_url('/local/nit_lessons/action.php'))->out(false),
    'back' => 'teacher',
    'status' => $status,
    'filter' => lesson_view::filter($status),
    'lessons' => $cards,
    'haslessons' => !empty($cards),
    'notbookable' => !$teachers->bookable($userid),
    'available' => $profile['available'],
    'profileurl' => (new moodle_url('/local/nit_lessons/teacher_profile.php'))->out(false),
    'earningsurl' => (new moodle_url('/local/nit_finance/earnings.php'))->out(false),
]);
echo $OUTPUT->footer();
