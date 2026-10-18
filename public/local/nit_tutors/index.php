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
 * Find a teacher: the "Teachers" tab of the catalogue (/local/nit_category/index.php?tab=teachers),
 * which draws it in the same frame as the courses — title, search box, Courses | Teachers tabs,
 * sort box, side column — so switching tabs moves nothing. This address (old links, the app,
 * "Find a teacher" buttons) keeps working: it opens that tab with the same filters.
 *
 * Without local_nit_category the tab is drawn here on its own.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_tutors\local\search;
use local_nit_tutors\local\search_view;

$filters = search::normalise(search_view::raw_from_request());
$page = optional_param('page', 0, PARAM_INT);
$params = search_view::params($filters) + ($page ? ['page' => $page] : []);

if (core_component::get_component_directory('local_nit_category') !== null) {
    redirect(new moodle_url('/local/nit_category/index.php', ['tab' => 'teachers'] + $params));
}

$url = new moodle_url('/local/nit_tutors/index.php', $params);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
if (!empty($CFG->forcelogin)) {
    require_login();
}
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('findteacher', 'local_nit_tutors'));
$PAGE->set_heading(get_string('findteacher', 'local_nit_tutors'));

$view = search_view::context($filters, $page,
    new moodle_url('/local/nit_tutors/index.php', $filters['q'] !== '' ? ['q' => $filters['q']] : []));
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_tutors/search_filters', $view['filters'] + [
    'actionurl' => (new moodle_url('/local/nit_tutors/index.php'))->out(false), 'tab' => 'teachers']);
echo $OUTPUT->render_from_template('local_nit_tutors/search_results', $view['results']);
echo $OUTPUT->paging_bar($view['total'], $page, search_view::PERPAGE, $url);
echo $OUTPUT->footer();
