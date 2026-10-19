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
 * List every Game activity in a course.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_games\mlang;
use local_games\registry;

$id = required_param('id', PARAM_INT);              // Course id.

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url(new moodle_url('/mod/games/index.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_games'));

$instances = get_all_instances_in_course('games', $course);
if (!$instances) {
    notice(get_string('noinstances', 'mod_games'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('thegame', 'mod_games'),
    get_string('section'),
];

foreach ($instances as $instance) {
    $link = new moodle_url('/mod/games/view.php', ['id' => $instance->coursemodule]);

    // The slug is what the activity stores; what it is called can have been
    // changed since, and can be missing entirely on a course restored onto a
    // site without that game.
    $definition = registry::get_game($instance->gameid);
    $gamename = $definition
        ? $definition['emoji'] . ' ' . mlang::display(registry::name($instance->gameid))
        : html_writer::span(get_string('errormissinggame', 'mod_games'), 'text-danger');

    $table->data[] = [
        html_writer::link($link, format_string($instance->name),
            $instance->visible ? [] : ['class' => 'dimmed']),
        $gamename,
        $instance->section,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
