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
 * Play the game this activity was set to.
 *
 * The page is the corner's own play shell, rendered inside a course module: the
 * same template, the same shell.js and the same per-game file. Re-implementing
 * any of it here would mean a game behaving one way in the corner and another
 * way in a course, which is the one thing this activity must not do.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

use local_games\content;
use local_games\mlang;
use local_games\registry;
use mod_games\play_manager;

$id = optional_param('id', 0, PARAM_INT);           // Course module id.
$g  = optional_param('g', 0, PARAM_INT);            // Instance id.

if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'games');
} else {
    [$course, $cm] = get_course_and_cm_from_instance($g, 'games');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/games:view', $context);

$game = $DB->get_record('games', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/games/view.php', ['id' => $cm->id]));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(format_string($game->name));
$PAGE->set_heading(format_string($course->fullname));
// The corner's styles.css is folded into the theme stylesheet by Moodle, so the
// shell is already styled here; the body class is what the rules hang off.
$PAGE->add_body_class('local-games local-games-play mod-games');

$event = \mod_games\event\course_module_viewed::create([
    'objectid' => $game->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('games', $game);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($game->name));

if (!empty($game->intro)) {
    echo $OUTPUT->box(format_module_intro('games', $game, $cm->id), 'generalbox mod_introbox', 'gamesintro');
}

$slug = $game->gameid;

// The chosen game can stop being playable long after the activity was set up -
// an admin switches it off in Game control, or the slug is left over from a
// backup restored onto a site without that game. Neither is the teacher's fault
// and neither should be a stack trace.
if ($slug === '' || registry::get_game($slug) === null) {
    echo $OUTPUT->notification(get_string('errormissinggame', 'mod_games'), 'error');
    echo $OUTPUT->footer();
    exit;
}

if (!registry::is_live($slug)) {
    echo $OUTPUT->notification(get_string('errorgameoff', 'mod_games', mlang::display(registry::name($slug))), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$definition = registry::get_game($slug);
$key = registry::key($slug);

// Where "back" goes. In the corner it is the hub; here the child came from the
// course page and that is where they expect to land.
$backurl = new moodle_url('/course/view.php', ['id' => $course->id]);

$canplay = has_capability('mod/games:play', $context);

$jsrev = isset($CFG->jsrev) ? $CFG->jsrev : 1;
$PAGE->requires->js(new moodle_url('/local/games/js/shell.js', ['rev' => $jsrev]));
$PAGE->requires->js(new moodle_url('/local/games/js/' . $slug . '.js', ['rev' => $jsrev]));
if ($canplay) {
    // Only a student's rounds are recorded, so only a student's page needs the
    // listener. A teacher trying the game out is not in the report.
    $PAGE->requires->js(new moodle_url('/mod/games/js/activity.js', ['rev' => $jsrev]));

    // Which course module the listener should report the round against. It is
    // an element rather than a query string on the script URL because the file
    // is cached across every Game activity on the site - one copy, and the page
    // tells it where it is.
    echo html_writer::span('', 'mod-games-hook', [
        'data-mod-games-cmid' => $cm->id,
        'data-sesskey'        => sesskey(),
        'hidden'              => 'hidden',
    ]);
}

echo $OUTPUT->render_from_template('local_games/play', [
    'gameid'      => $slug,
    'name'        => mlang::display(registry::name($slug)),
    'emoji'       => $definition['emoji'],
    'huburl'      => $backurl->out(false),
    'backlabel'   => get_string('backtocourse', 'mod_games'),
    'cancellabel' => get_string('cancel', 'local_games'),
    'startlabel'  => get_string('js_start', 'local_games'),
    'readytitle'  => get_string('js_' . $key . '_ready', 'local_games'),
    'howto'       => get_string('js_' . $key . '_howto', 'local_games'),
    'desc'        => mlang::display(registry::description($slug)),
    'scorelabel'  => get_string('js_score', 'local_games'),
    'streaklabel' => get_string('js_streak', 'local_games'),
    'soundonlabel' => get_string('js_sound_on', 'local_games'),
    'config'      => json_encode([
        'gameid'       => $slug,
        'wwwroot'      => $CFG->wwwroot,
        'sesskey'      => sesskey(),
        // The shell puts this on its end card as "back to the games". Inside a
        // course that has to be the course, not the corner.
        'huburl'       => $backurl->out(false),
        'arabicdigits' => content::arabic_digits(),
        'points'       => \local_games\progress::get_totals($USER->id)['points'],
        'strings'      => content::strings(),
        // The chosen game's own content, in the slot its file reads from - the
        // same content the corner would hand it, because it is the same game.
    ] + content::payload($slug), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
]);

// What this child has done in this activity - the corner's own totals are on
// the hub, and are about the whole site rather than about this course.
if ($canplay) {
    $mine = play_manager::get($game->id, $USER->id);
    if ($mine && $mine->plays > 0) {
        echo html_writer::div(
            get_string('yourstanding', 'mod_games', (object) [
                'plays'     => (int) $mine->plays,
                'bestscore' => (int) $mine->bestscore,
            ]),
            'alert alert-info mt-3'
        );
    }
}

if (!empty($game->showhublink)) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/games/index.php'),
            get_string('gotohub', 'mod_games'),
            ['class' => 'btn btn-outline-secondary']),
        'mt-3'
    );
}

if (has_capability('mod/games:viewreports', $context)) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/mod/games/report.php', ['id' => $cm->id]),
            get_string('viewreport', 'mod_games'),
            ['class' => 'btn btn-secondary']),
        'mt-3'
    );
}

echo $OUTPUT->footer();
