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
 * Teacher profile for live lessons: take bookings or not, subjects, weekly hours.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_nit_lessons\service\teacher_service;

require_login(null, false);
$url = new moodle_url('/local/nit_lessons/teacher_profile.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('teacherprofile', 'local_nit_lessons'));
$PAGE->set_heading(get_string('teacherprofile', 'local_nit_lessons'));
local_nit_flex_require_enabled();

$userid = (int) $USER->id;
$service = new teacher_service();
if (!$service->is_teacher($userid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notateacher', 'local_nit_lessons'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (optional_param('action', '', PARAM_ALPHA) === 'save' && confirm_sesskey()) {
    $days = optional_param_array('day', [], PARAM_INT);
    $starts = optional_param_array('start', [], PARAM_RAW_TRIMMED);
    $ends = optional_param_array('end', [], PARAM_RAW_TRIMMED);
    $hours = [];
    foreach ($days as $i => $day) {
        if (($starts[$i] ?? '') === '' && ($ends[$i] ?? '') === '') {
            continue;
        }
        $hours[] = ['dayofweek' => (int) $day, 'starttime' => (string) ($starts[$i] ?? ''),
            'endtime' => (string) ($ends[$i] ?? '')];
    }
    try {
        $service->save($userid, (bool) optional_param('available', 0, PARAM_BOOL),
            optional_param('headline', '', PARAM_TEXT), optional_param_array('subjects', [], PARAM_TEXT), $hours);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url, get_string('profilesaved', 'local_nit_lessons'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$profile = $service->profile($userid);
$daynames = [];
foreach (range(0, 6) as $d) {
    $daynames[] = ['value' => $d, 'label' => get_string('day' . $d, 'local_nit_lessons')];
}
$hours = [];
foreach ($profile['hours'] as $h) {
    $hours[] = [
        'start' => $h['starttime'],
        'end' => $h['endtime'],
        'days' => array_map(fn($d) => $d + ['selected' => $d['value'] === $h['dayofweek']], $daynames),
    ];
}
$subjects = array_map(fn($s) => ['value' => $s], $profile['subjects'] ?: ['']);
$suggestions = [];
if (class_exists('\local_academy\teacher_manager')) {
    foreach (\local_academy\teacher_manager::get_teacher_courses($userid) as $course) {
        $suggestions[] = ['value' => $course['fullname']];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_lessons/teacher_profile', [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'available' => $profile['available'],
    'headline' => $profile['headline'],
    'subjects' => $subjects,
    'suggestions' => $suggestions,
    'hours' => $hours,
    'hashours' => !empty($hours),
    'daynames' => $daynames,
    'timezone' => core_date::get_localised_timezone(core_date::get_user_timezone($USER)),
    'mylessonsurl' => (new moodle_url('/local/nit_lessons/my_lessons.php'))->out(false),
]);
echo $OUTPUT->footer();
