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
 * Add or edit one row of a Game activity's own content, as a page.
 *
 * content.php opens the same form in a dialogue; this is where its links go when
 * JavaScript is off.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_games\registry;
use mod_games\content;
use mod_games\form\content_form;

$cmid  = required_param('cmid', PARAM_INT);
$rowid = optional_param('rowid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'games');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/games:managecontent', $context);

$game = $DB->get_record('games', ['id' => $cm->instance], '*', MUST_EXIST);
$returnurl = new moodle_url('/mod/games/content.php', ['id' => $cm->id]);

if (registry::get_game($game->gameid) === null) {
    throw new moodle_exception('errormissinggame', 'mod_games', $returnurl);
}

$row = null;
if ($rowid > 0) {
    $row = content::get_row($rowid, (int) $game->id);
    if (!$row) {
        throw new moodle_exception('errorunknownrow', 'local_games', $returnurl);
    }
}

$pageurl = new moodle_url('/mod/games/content_edit.php', ['cmid' => $cm->id, 'rowid' => $rowid]);
$heading = get_string($row ? 'editrowtitle' : 'addrowtitle', 'local_games');

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title($heading);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->navbar->add(get_string('gamecontent', 'mod_games'), $returnurl);
$PAGE->navbar->add($heading);

$form = new content_form($pageurl, ['cmid' => $cm->id]);

$data = ['id' => $game->gameid, 'cmid' => $cm->id, 'rowid' => $rowid];
if ($row) {
    $data += content_form::to_data($row->data, $game->gameid);
}
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($submitted = $form->get_data()) {
    content::save_row($rowid, $game, content_form::to_row($submitted, $game->gameid));
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo html_writer::div(get_string('shapedesc_' . registry::shape_for($game->gameid), 'local_games'), 'text-muted mb-3');
$form->display();
echo $OUTPUT->footer();
