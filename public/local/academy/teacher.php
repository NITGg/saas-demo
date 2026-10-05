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
 * The public teacher page — bassthalk.com/teacher/<id> rebuilt over real data:
 * a blue hero (name, title, the years taught, counts), the teacher's photo card
 * and every visible course they teach, with a year filter.
 *
 * The home page teacher cards (theme/nit/blocks/templates/bassthalk/teachers.html,
 * via homedata.php?section=teachers) link here. Public like the home page;
 * behind the log-in when the site forces log-in. Only teachers have a page.
 * Look: theme/nit/scss/components/_bthteacher.scss (Brand Colors → Bassthalk → "Teacher page").
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/teacher.php', ['id' => $id]));
if (!empty($CFG->forcelogin)) {
    require_login();
}

$teacher = \local_academy\local\teacher_page::get($id);
if ($teacher === null) {
    throw new moodle_exception('teacherpage_notteacher', 'local_academy');
}

$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(get_string('teacherpage_title', 'local_academy', $teacher['name']));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-bth-teacher-page');

$free = \local_academy\local\home_data::price_label('');
$dateformat = get_string('strftimedaydate', 'langconfig');
$courses = array_map(static function(array $c) use ($free, $dateformat): array {
    $c['free'] = $c['price'] === $free;
    $c['createdtext'] = userdate($c['created'], $dateformat);
    $c['modifiedtext'] = userdate($c['modified'], $dateformat);
    return $c;
}, $teacher['courses']);

$context = $teacher + [
    'hasyears' => !empty($teacher['years']),
    'filter' => count($teacher['years']) > 1,
];
$context['courses'] = $courses;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_academy/teacher_page', $context);
echo $OUTPUT->footer();
