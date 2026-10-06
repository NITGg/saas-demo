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
 * The public subject page — bassthalk.com/subject/<id> rebuilt over real data:
 * one subject of one year (course category + course field "Subject"), its
 * teachers and every visible course of it.
 *
 *   /local/academy/subject.php?year=<category id>&subject=<position in the Subject list>
 *
 * The home page's subject cards (theme/nit/blocks/templates/bassthalk/selected.html,
 * via homedata.php?section=subjects) link here. Public like the home page;
 * behind the log-in when the site forces log-in.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$year = required_param('year', PARAM_INT);
$subject = required_param('subject', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(\local_academy\local\subject_page::url($year, $subject));
if (!empty($CFG->forcelogin)) {
    require_login();
}

$page = \local_academy\local\subject_page::get($year, $subject);
if ($page === null) {
    throw new moodle_exception('subjectpage_notfound', 'local_academy');
}

$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(get_string('subjectpage_title', 'local_academy', $page['name']));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-bth-subject-page');

$page['courses'] = array_map([\local_academy\local\home_data::class, 'card_view'], $page['courses']);
$page['hasteachers'] = !empty($page['teachers']);
$page['browseurl'] = (new moodle_url('/course/index.php'))->out(false);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_academy/subject_page', $page);
echo $OUTPUT->footer();
