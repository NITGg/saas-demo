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
 * Join the meeting room of a live lesson that has started (its teacher or student only).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_lessons\entity\lesson;

require_login(null, false);
$id = required_param('id', PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_lessons/join.php', ['id' => $id]));

$record = lesson::get_record(['id' => $id]);
$userid = (int) $USER->id;
$isteacher = $record && (int) $record->get('teacherid') === $userid;
$back = new moodle_url('/local/nit_lessons/student.php', ['tab' => $isteacher ? 'teaching' : 'lessons']);
if (!$record || (!$isteacher && (int) $record->get('studentid') !== $userid)) {
    throw new moodle_exception('err_forbidden', 'local_nit_lessons');
}
try {
    $room = (new \local_nit_lessons\service\lesson_service())->open_room($userid, $id);
} catch (\local_nit_lessons\exception\lesson_exception $e) {
    redirect($back, $e->getMessage(), null, \core\output\notification::NOTIFY_WARNING);
}
// The lesson card's "Join" is the user's choice already: straight into the call (join=1).
redirect(new moodle_url($room['join_url'], ['join' => 1]));
