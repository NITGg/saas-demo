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
 * The Games Corner hub - every game in the corner, on one page.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_games\mlang;
use local_games\progress;
use local_games\registry;

require_login();

$context = context_system::instance();
require_capability('local/games:play', $context);

$PAGE->set_url(new moodle_url('/local/games/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('hubtitle', 'local_games'));
$PAGE->set_heading(get_string('hubtitle', 'local_games'));
// styles.css needs no explicit include: Moodle folds every plugin's styles.css
// into the theme stylesheet, which also gets it the theme revision cache-buster.
$PAGE->add_body_class('local-games');

$totals   = progress::get_totals($USER->id);
$played   = progress::get_user_progress($USER->id);
$earned   = progress::get_user_badges($USER->id);
$allgames = registry::get_games();

// Group the catalogue into the hub's sections, keeping the registry's order
// inside each one.
$categories = [];
foreach (registry::get_categories() as $catkey => $catemoji) {
    $cards = [];

    foreach ($allgames as $id => $game) {
        if ($game['category'] !== $catkey) {
            continue;
        }
        $live = $game['status'] === registry::STATUS_LIVE;

        $cards[] = [
            'id'        => $id,
            'emoji'     => $game['emoji'],
            'name'      => mlang::display(registry::name($id)),
            'desc'      => mlang::display(registry::description($id)),
            'stars'     => str_repeat('⭐', $game['level']),
            'live'      => $live,
            'url'       => $live ? (new moodle_url('/local/games/play.php', ['id' => $id]))->out(false) : null,
            'played'    => isset($played[$id]) && $played[$id]->plays > 0,
            'bestscore' => isset($played[$id])
                ? get_string('bestscore', 'local_games', $played[$id]->bestscore)
                : '',
        ];
    }

    if (!$cards) {
        continue;
    }

    // Only the "big worlds" section carries an explanatory note.
    $note = $catkey === 'worlds' ? get_string('cat_worlds_note', 'local_games') : null;

    $categories[] = [
        'key'     => $catkey,
        'emoji'   => $catemoji,
        'name'    => get_string('cat_' . $catkey, 'local_games'),
        'note'    => $note,
        'hasnote' => (bool) $note,
        'games'   => $cards,
    ];
}

// The badge shelf: every badge the corner can give, with the earned ones lit up.
$badgeshelf = [];
foreach ($allgames as $id => $game) {
    foreach ($game['badges'] ?? [] as $badge => $unusedrule) {
        $badgekey = registry::key($badge);
        $badgeshelf[] = [
            'name'   => get_string('badge_' . $badgekey, 'local_games'),
            'hint'   => get_string('badgehint_' . $badgekey, 'local_games'),
            'earned' => in_array($badge, $earned, true),
        ];
    }
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_games/hub', [
    'title'        => get_string('hubtitle', 'local_games'),
    'intro'        => get_string('hubintro', 'local_games'),
    'pointslabel'  => get_string('yourpoints', 'local_games'),
    'badgeslabel'  => get_string('yourbadges', 'local_games'),
    'points'       => $totals['points'],
    'badges'       => $totals['badges'],
    'playlabel'    => get_string('play', 'local_games'),
    'soonlabel'    => get_string('comingsoon', 'local_games'),
    'categories'   => $categories,
    'badgeshelf'   => $badgeshelf,
    'hasbadgeshelf' => (bool) $badgeshelf,
]);

echo $OUTPUT->footer();
