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
 * Live monitoring wall: a card per lesson that is on now (or on a chosen day), with
 * its state, who is in and what needs attention. Refreshes itself (monitor_ajax.php)
 * without reloading the page; a card opens in a larger view with everyone in it.
 *
 * Admins and site managers reach it from Site administration → Plugins → Local
 * plugins → Live monitoring and see every lesson; a manager of a category or course
 * reaches it from the course's "More" menu and sees those courses only.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_academysessions\monitor;

require_login();
$scope = monitor::course_scope((int) $USER->id);
if ($scope === []) {
    throw new required_capability_exception(context_system::instance(), monitor::CAP, 'nopermissions', '');
}
$filters = monitor::filters_from_request();
if ($scope !== null && $filters->courseid && !in_array($filters->courseid, $scope, true)) {
    $filters->courseid = 0;
}
$s = fn(string $k, $a = null) => get_string($k, 'local_academysessions', $a);

$pageurl = new moodle_url('/local/academysessions/monitor.php', monitor::filter_params($filters));
if ($scope === null) {
    admin_externalpage_setup('local_academysessions_monitor', '', null, $pageurl);
} else {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($s('monitor'));
    $PAGE->set_heading($s('monitor'));
}
\local_academysessions\event\monitor_viewed::for_session()->trigger();

// Filter choices: the courses and teachers that have lessons, within the viewer's courses.
$lessonscourse = (int) get_config('local_nit_lessons', 'lessons_courseid');
$courseids = $DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {academy_live_sessions}');
if ($lessonscourse) {
    $courseids[] = $lessonscourse;
}
$courseids = array_values(array_unique(array_map('intval', $courseids)));
if ($scope !== null) {
    $courseids = array_values(array_intersect($courseids, $scope));
}
$courseoptions = [];
foreach ($courseids ? $DB->get_records_list('course', 'id', $courseids, 'fullname', 'id, fullname') : [] as $c) {
    $courseoptions[] = ['value' => $c->id, 'label' => format_string($c->fullname), 'selected' => (int) $c->id === $filters->courseid];
}
$teacherids = [];
if ($courseids) {
    [$in, $params] = $DB->get_in_or_equal($courseids);
    $teacherids = $DB->get_fieldset_sql("SELECT DISTINCT teacherid FROM {academy_live_sessions} WHERE courseid $in", $params);
    if ($lessonscourse && in_array($lessonscourse, $courseids, true) && $DB->get_manager()->table_exists('nit_lesson')) {
        $teacherids = array_merge($teacherids, $DB->get_fieldset_sql('SELECT DISTINCT teacherid FROM {nit_lesson}'));
    }
}
$teacheroptions = [];
$teacherids = array_values(array_unique(array_map('intval', $teacherids)));
if ($teacherids) {
    $fields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
    $users = $DB->get_records_list('user', 'id', $teacherids, '', 'id, ' . $fields);
    $names = array_map('fullname', $users);
    core_collator::asort($names);
    foreach ($names as $id => $name) {
        $teacheroptions[] = ['value' => $id, 'label' => $name, 'selected' => (int) $id === $filters->teacherid];
    }
}
$option = fn(string $value, string $label, string $current) => ['value' => $value, 'label' => $label, 'selected' => $value === $current];
$statusoptions = [$option('', $s('monitor_allstatuses'), $filters->status)];
foreach (monitor::ORDER as $st) {
    $statusoptions[] = $option($st, $s('monitor_status_' . $st), $filters->status);
}

$wall = monitor::wall($filters, $scope);
$context = [
    'action' => (new moodle_url('/local/academysessions/monitor.php'))->out(false),
    'viewnow' => $filters->view === 'now',
    'date' => $filters->date,
    'q' => $filters->q,
    'courses' => $courseoptions,
    'teachers' => $teacheroptions,
    'providers' => [
        $option('', $s('monitor_allproviders'), $filters->provider),
        $option('jitsi', $s('monitor_provider_jitsi'), $filters->provider),
        $option('link', $s('monitor_provider_link'), $filters->provider),
    ],
    'statuses' => $statusoptions,
    'grace' => monitor::grace_minutes(),
    'refresh' => monitor::refresh_seconds(),
    'wall' => $wall + ['empty' => !$wall['cards'], 'updated' => userdate(time(), $s('monitor_strftimeseconds'))],
];

$PAGE->requires->js_call_amd('local_academysessions/monitor', 'init', [[
    'url' => (new moodle_url('/local/academysessions/monitor_ajax.php'))->out(false),
    'sesskey' => sesskey(),
    'params' => monitor::filter_params($filters),
    'refresh' => monitor::refresh_seconds(),
    'strings' => [
        'failed' => $s('monitor_refreshfailed'),
        'detailfailed' => $s('monitor_detailfailed'),
    ],
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading($s('monitor'));
echo $OUTPUT->render_from_template('local_academysessions/monitor', $context);
echo $OUTPUT->footer();
