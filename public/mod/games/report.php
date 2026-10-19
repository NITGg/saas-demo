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
 * Who has played this Game activity, and how it went for them.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_games\mlang;
use local_games\registry;
use mod_games\play_manager;

$id = required_param('id', PARAM_INT);              // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'games');

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/games:viewreports', $context);

$game = $DB->get_record('games', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/games/report.php', ['id' => $cm->id]));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('report', 'mod_games'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportfor', 'mod_games', format_string($game->name)));

echo html_writer::div(
    html_writer::link(new moodle_url('/mod/games/view.php', ['id' => $cm->id]),
        '&larr; ' . get_string('backtoactivity', 'mod_games')),
    'mb-3'
);

// Everyone who could play, not only everyone who did: an empty row is the
// answer the teacher came for at least as often as a full one.
$players = get_enrolled_users($context, 'mod/games:play', 0, 'u.*', 'u.lastname, u.firstname');
$rows = play_manager::get_all($game->id);

// An empty roster is not necessarily an empty report: somebody may have played
// and since been unenrolled, and those rounds still have to be reachable.
$strangers = array_diff_key($rows, $players);

if (!$players && !$strangers) {
    echo $OUTPUT->notification(get_string('noplayers', 'mod_games'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('colplays', 'mod_games'),
    get_string('colpoints', 'mod_games'),
    get_string('colbest', 'mod_games'),
    get_string('colstreak', 'mod_games'),
    get_string('collastplayed', 'mod_games'),
];
$table->attributes['class'] = 'generaltable mod-games-report';
$table->data = [];

$playedcount = 0;

foreach ($players as $player) {
    $row = $rows[$player->id] ?? null;
    if ($row && $row->plays > 0) {
        $playedcount++;
    }

    $table->data[] = [
        html_writer::link(
            new moodle_url('/user/view.php', ['id' => $player->id, 'course' => $course->id]),
            fullname($player)
        ),
        $row ? (int) $row->plays : html_writer::span('0', 'text-muted'),
        $row ? (int) $row->points : html_writer::span('0', 'text-muted'),
        $row ? (int) $row->bestscore : html_writer::span('0', 'text-muted'),
        $row ? (int) $row->beststreak : html_writer::span('0', 'text-muted'),
        $row && $row->timemodified
            ? userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'))
            : html_writer::span(get_string('neverplayed', 'mod_games'), 'text-muted'),
    ];
}

echo html_writer::div(
    get_string('reportsummary', 'mod_games', (object) [
        'game'    => registry::get_game($game->gameid)
            ? mlang::display(registry::name($game->gameid))
            : $game->gameid,
        'played'  => $playedcount,
        'total'   => count($players),
    ]),
    'alert alert-info'
);

if ($players) {
    echo html_writer::table($table);
}

// Rounds played by somebody who is no longer on the roster - unenrolled since,
// or a teacher who tried the activity out. Their rows are still in the table
// and would otherwise be invisible to everyone, which is a worse answer to
// "who has played" than a short second list.
if ($strangers) {
    $others = new html_table();
    $others->head = $table->head;
    $others->attributes['class'] = 'generaltable mod-games-report mod-games-report--past';
    $others->data = [];

    foreach ($strangers as $userid => $row) {
        $person = core_user::get_user($userid);
        $others->data[] = [
            $person && !$person->deleted
                ? html_writer::link(new moodle_url('/user/view.php',
                    ['id' => $userid, 'course' => $course->id]), fullname($person))
                : html_writer::span(get_string('unknownuser'), 'text-muted'),
            (int) $row->plays,
            (int) $row->points,
            (int) $row->bestscore,
            (int) $row->beststreak,
            $row->timemodified
                ? userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'))
                : html_writer::span(get_string('neverplayed', 'mod_games'), 'text-muted'),
        ];
    }

    echo $OUTPUT->heading(get_string('notenrolledheading', 'mod_games'), 3, 'mt-4');
    echo html_writer::div(get_string('notenrolledintro', 'mod_games'), 'text-muted mb-2');
    echo html_writer::table($others);
}

echo $OUTPUT->footer();
