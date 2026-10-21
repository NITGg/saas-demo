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
 * This view provides a summary for the teacher
 *
 * @package mod_flashcard
 * @category mod
 * @author Valery Fremaux, Gustav Delius, Tomasz Muras
 * @contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @version Moodle 2.0
 */
defined('MOODLE_INTERNAL') || die();

echo $out; // Deffered header.

$groupid = 0;
if (groups_get_activity_groupmode($cm, $COURSE) != NOGROUPS) {
    $groupid = (int) groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm, new moodle_url('/mod/flashcard/view.php',
        ['id' => $cm->id, 'view' => 'summary', 'page' => 'byusers']));
}

echo $renderer->report($flashcard, $cm, 'byusers', $groupid);
