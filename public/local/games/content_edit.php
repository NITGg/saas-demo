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
 * Add or edit one row of one game's content.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_games\admin\manager;
use local_games\form\content_form;
use local_games\mlang;
use local_games\registry;

require_login();

$context = context_system::instance();
require_capability('local/games:manage', $context);

$gameid = required_param('id', PARAM_ALPHANUMEXT);
$rowid  = optional_param('rowid', 0, PARAM_INT);

if (registry::get_game($gameid) === null) {
    throw new moodle_exception('errorunknowngame', 'local_games',
        new moodle_url('/local/games/manage.php'));
}

$returnurl = new moodle_url('/local/games/game.php', ['id' => $gameid]);

$row = null;
if ($rowid > 0) {
    $row = manager::get_row($rowid);
    // The row id comes off the URL, so it has to be shown to belong to this game
    // before its values are put on screen.
    if (!$row || $row->gameid !== $gameid) {
        throw new moodle_exception('errorunknownrow', 'local_games', $returnurl);
    }
}

$pageurl = new moodle_url('/local/games/content_edit.php', ['id' => $gameid, 'rowid' => $rowid]);

admin_externalpage_setup('local_games_control', '', null, $pageurl);

$gamename = mlang::display(registry::name($gameid));
$heading = $row
    ? get_string('editrow', 'local_games', $gamename)
    : get_string('addrowto', 'local_games', $gamename);

$PAGE->navbar->add($gamename, $returnurl);
$PAGE->navbar->add($heading);
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

$form = new content_form($pageurl, ['gameid' => $gameid]);

$data = ['id' => $gameid, 'rowid' => $rowid];
if ($row) {
    $data += content_form::to_data($row->data, $gameid);
}
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($submitted = $form->get_data()) {
    manager::save_row($rowid, $gameid, content_form::to_row($submitted, $gameid));
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

echo html_writer::div(
    get_string('shapedesc_' . registry::shape_for($gameid), 'local_games'),
    'text-muted mb-3'
);

$form->display();

echo $OUTPUT->footer();
