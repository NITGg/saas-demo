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
 * Play one game.
 *
 * The page is the same shell for every game: a HUD, a stage, a start card and
 * an end card. The per-game file in js/ fills the stage and reports the result
 * back to the shell.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_games\content;
use local_games\mlang;
use local_games\progress;
use local_games\registry;

require_login();

$context = context_system::instance();
require_capability('local/games:play', $context);

$id = required_param('id', PARAM_ALPHANUMEXT);

if (!registry::is_live($id)) {
    throw new moodle_exception('errorunknowngame', 'local_games', new moodle_url('/local/games/index.php'));
}

$game = registry::get_game($id);
$key  = registry::key($id);

$PAGE->set_url(new moodle_url('/local/games/play.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(mlang::display(registry::name($id)));
$PAGE->set_heading(mlang::display(registry::name($id)));
// styles.css is folded into the theme stylesheet by Moodle - see index.php.
$PAGE->add_body_class('local-games local-games-play');

$PAGE->navbar->add(get_string('hubtitle', 'local_games'), new moodle_url('/local/games/index.php'));
$PAGE->navbar->add(mlang::display(registry::name($id)));

$totals = progress::get_totals($USER->id);

// Both files carry Moodle's JS revision in the URL.
//
// A plain /local/games/js/shell.js is served with no cache headers Moodle
// controls, so a browser that has seen it once keeps it - a shipped fix would
// reach nobody until they cleared their cache by hand. $CFG->jsrev changes
// whenever caches are purged, which is exactly when the file may have changed,
// and it is what core uses for the same reason.
$jsrev = isset($CFG->jsrev) ? $CFG->jsrev : 1;

$PAGE->requires->js(new moodle_url('/local/games/js/shell.js', ['rev' => $jsrev]));
$PAGE->requires->js(new moodle_url('/local/games/js/' . $id . '.js', ['rev' => $jsrev]));

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_games/play', [
    'gameid'    => $id,
    'name'      => mlang::display(registry::name($id)),
    'emoji'     => $game['emoji'],
    'huburl'    => (new moodle_url('/local/games/index.php'))->out(false),
    'backlabel' => get_string('backtohub', 'local_games'),
    'cancellabel' => get_string('cancel', 'local_games'),
    'startlabel' => get_string('js_start', 'local_games'),
    'readytitle' => get_string('js_' . $key . '_ready', 'local_games'),
    'howto'      => get_string('js_' . $key . '_howto', 'local_games'),
    // The same one-liner the hub card shows, so a child who opened the game
    // from somewhere else still knows what it is - on the start card and on
    // the page behind it.
    'desc'       => mlang::display(registry::description($id)),
    'scorelabel' => get_string('js_score', 'local_games'),
    'streaklabel' => get_string('js_streak', 'local_games'),
    'soundonlabel' => get_string('js_sound_on', 'local_games'),
    'config'     => json_encode([
        'gameid'       => $id,
        'wwwroot'      => $CFG->wwwroot,
        'sesskey'      => sesskey(),
        'huburl'       => (new moodle_url('/local/games/index.php'))->out(false),
        'arabicdigits' => content::arabic_digits(),
        'points'       => $totals['points'],
        'strings'      => content::strings(),
        // Only this game's own content, in the slot its file reads from. Every
        // game used to be handed all seven banks - 35 KB of material that 21 of
        // the 22 games never looked at.
    ] + content::payload($id), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
]);

echo $OUTPUT->footer();
