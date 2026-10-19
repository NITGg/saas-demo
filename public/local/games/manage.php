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
 * "Game control": every game in the corner, and the way in to each one.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_games\admin\manager;
use local_games\admin\output;
use local_games\registry;

require_login();

$context = context_system::instance();
require_capability('local/games:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$gameid = optional_param('gameid', '', PARAM_ALPHANUMEXT);

$pageurl = new moodle_url('/local/games/manage.php');

admin_externalpage_setup('local_games_control', '', null, $pageurl);
$PAGE->set_title(get_string('gamecontrol', 'local_games'));
$PAGE->set_heading(get_string('gamecontrol', 'local_games'));

// Switching a game on or off is one click from the table; everything else about
// a game happens on its own page.
if (($action === 'enable' || $action === 'disable') && $gameid !== '' && confirm_sesskey()) {
    if (registry::get_game($gameid) === null) {
        throw new moodle_exception('errorunknowngame', 'local_games', $pageurl);
    }

    manager::set_enabled($gameid, $action === 'enable');

    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$games = registry::get_games();
$stats = manager::get_play_stats();
$counts = manager::get_row_counts();

echo $OUTPUT->header();

echo html_writer::div(get_string('gamecontrolintro', 'local_games'), 'text-muted mb-3');

$totalplays = 0;
$totalpoints = 0;
foreach ($stats as $row) {
    $totalplays += (int) $row->plays;
    $totalpoints += (int) $row->points;
}

echo html_writer::div(
    get_string('cornertotals', 'local_games', (object) [
        'games'   => count($games),
        'plays'   => $totalplays,
        'points'  => $totalpoints,
        'players' => (int) $DB->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {local_games_progress}'),
        'rows'    => array_sum($counts),
    ]),
    'alert alert-info'
);

echo output::game_table($games, $stats, $counts,
    new moodle_url('/local/games/game.php'), $pageurl);

echo $OUTPUT->footer();
