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
 * One game's control page: how its card reads, whether it is on, and every row
 * of the content it is played from.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_games\admin\manager;
use local_games\admin\output;
use local_games\form\game_form;
use local_games\mlang;
use local_games\registry;

require_login();

$context = context_system::instance();
require_capability('local/games:manage', $context);

$gameid = required_param('id', PARAM_ALPHANUMEXT);

$game = registry::get_game($gameid);
if ($game === null) {
    throw new moodle_exception('errorunknowngame', 'local_games',
        new moodle_url('/local/games/manage.php'));
}

$action  = optional_param('action', '', PARAM_ALPHA);
$rowid   = optional_param('rowid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$selected = array_values(array_filter(optional_param_array('rowids', [], PARAM_INT)));

$manageurl = new moodle_url('/local/games/manage.php');
$pageurl   = new moodle_url('/local/games/game.php', ['id' => $gameid]);
$rowurl    = new moodle_url('/local/games/content_edit.php');

admin_externalpage_setup('local_games_control', '', null, $pageurl);
$PAGE->navbar->add(mlang::display(registry::name($gameid)));
$PAGE->set_title(get_string('gamecontrol', 'local_games'));
$PAGE->set_heading(get_string('gamecontrol', 'local_games'));

/**
 * Refuse a content action aimed at a row belonging to another game.
 *
 * The row id comes off the URL, so without this an admin could be linked into
 * deleting a row from a game they are not looking at.
 *
 * @param int $rowid row id from the request
 * @param string $gameid the game this page is about
 * @param moodle_url $pageurl where to send them back to
 * @return \stdClass the row
 */
$requirerow = function (int $rowid, string $gameid, moodle_url $pageurl): \stdClass {
    $row = manager::get_row($rowid);
    if (!$row || $row->gameid !== $gameid) {
        throw new moodle_exception('errorunknownrow', 'local_games', $pageurl);
    }
    return $row;
};

// -- Actions --------------------------------------------------------------

if ($action === 'moveup' && $rowid > 0 && confirm_sesskey()) {
    $requirerow($rowid, $gameid, $pageurl);
    manager::move_row($rowid, -1);
    redirect($pageurl);

} else if ($action === 'movedown' && $rowid > 0 && confirm_sesskey()) {
    $requirerow($rowid, $gameid, $pageurl);
    manager::move_row($rowid, 1);
    redirect($pageurl);

} else if ($action === 'delete' && $rowid > 0 && confirm_sesskey() && $confirm) {
    $requirerow($rowid, $gameid, $pageurl);
    manager::delete_row($rowid);
    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);

} else if ($action === 'deleteselected' && $selected && confirm_sesskey() && $confirm) {
    $deleted = manager::delete_rows($selected, $gameid);
    redirect($pageurl, get_string('rowsdeleted', 'local_games', $deleted), null,
        \core\output\notification::NOTIFY_SUCCESS);

} else if ($action === 'restore' && confirm_sesskey() && $confirm) {
    $written = manager::seed($gameid, true);
    redirect($pageurl, get_string('contentrestored', 'local_games', $written), null,
        \core\output\notification::NOTIFY_SUCCESS);

}

// -- The card form --------------------------------------------------------

$override = manager::get_overrides()[$gameid] ?? null;

$form = new game_form($pageurl, ['gameid' => $gameid]);
$form->set_data(['id' => $gameid, 'logo' => \local_games\logo::prepare_draft($gameid)]
    + game_form::to_data($gameid, $override));

if ($form->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $form->get_data()) {
    manager::save_game($gameid, game_form::to_record($data, $gameid));
    \local_games\logo::save_draft($gameid, (int) $data->logo);
    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Adding and editing a content row happen in a dialogue on this page. The links
// still point at content_edit.php, so this only upgrades them - it is not what
// makes them work.
$PAGE->requires->js_amd_inline(<<<'JS'
require(['core_form/modalform', 'core/str'], function(ModalForm, Str) {
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-local-games-editrow]');
        if (!trigger) {
            return;
        }
        e.preventDefault();

        var rowid = parseInt(trigger.getAttribute('data-rowid'), 10) || 0;

        Str.get_string(rowid ? 'editrowtitle' : 'addrowtitle', 'local_games').then(function(title) {
            var form = new ModalForm({
                formClass: 'local_games\\form\\content_form',
                args: {id: trigger.getAttribute('data-gameid'), rowid: rowid},
                modalConfig: {title: title},
                returnFocus: trigger
            });
            // The table is built server-side, so the honest way to show the new
            // row is to fetch the page again rather than to guess at the markup.
            form.addEventListener(form.events.FORM_SUBMITTED, function() {
                window.location.reload();
            });
            return form.show();
        }).catch(function() {
            // The dialogue could not be built; the link still goes somewhere.
            window.location.assign(trigger.href);
        });
    });
});
JS);

echo $OUTPUT->header();

// -- Confirmations --------------------------------------------------------

if ($action === 'delete' && $rowid > 0 && confirm_sesskey() && !$confirm) {
    $row = $requirerow($rowid, $gameid, $pageurl);
    $summary = [];
    foreach (registry::fields_for($gameid) as $field => $definition) {
        $value = (string) ($row->data[$field] ?? '');
        if ($value !== '' && !empty($definition['translatable'])) {
            $summary[] = mlang::display($value);
        }
    }
    echo $OUTPUT->confirm(
        get_string('confirmdeleterow', 'local_games')
            . html_writer::tag('p', s(implode(' — ', $summary)), ['class' => 'mt-2 fw-bold']),
        new moodle_url($pageurl, ['action' => 'delete', 'rowid' => $rowid,
            'sesskey' => sesskey(), 'confirm' => 1]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'deleteselected' && $selected && confirm_sesskey() && !$confirm) {
    echo $OUTPUT->confirm(
        get_string('confirmdeleterows', 'local_games', count($selected)),
        new moodle_url($pageurl, ['action' => 'deleteselected', 'rowids' => $selected,
            'sesskey' => sesskey(), 'confirm' => 1]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'restore' && confirm_sesskey() && !$confirm) {
    echo $OUTPUT->confirm(
        get_string('confirmrestore', 'local_games'),
        new moodle_url($pageurl, ['action' => 'restore', 'sesskey' => sesskey(), 'confirm' => 1]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

// -- The page -------------------------------------------------------------

$logourl = \local_games\logo::url($gameid);
echo $OUTPUT->heading(
    ($logourl
        ? html_writer::img($logourl, '', ['class' => 'local-games-logo', 'width' => 40, 'height' => 40]) . ' '
        : html_writer::span($game['emoji'] . ' ', 'local-games-face', ['aria-hidden' => 'true']))
        . s(mlang::display(registry::name($gameid)))
);

echo html_writer::div(
    html_writer::link($manageurl, '&larr; ' . get_string('backtocontrol', 'local_games')),
    'mb-3'
);

$stats = manager::get_play_stats()[$gameid] ?? null;
echo html_writer::div(
    get_string('gamestats', 'local_games', (object) [
        'players'   => $stats ? (int) $stats->players : 0,
        'plays'     => $stats ? (int) $stats->plays : 0,
        'points'    => $stats ? (int) $stats->points : 0,
        'bestscore' => $stats ? (int) $stats->bestscore : 0,
    ]),
    'alert alert-info'
);

if (registry::is_live($gameid)) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/games/play.php', ['id' => $gameid]),
            get_string('playthisgame', 'local_games'),
            ['class' => 'btn btn-outline-secondary', 'target' => '_blank']),
        'mb-3'
    );
}

echo $OUTPUT->heading(get_string('gamesettings', 'local_games'), 3);
$form->display();

echo $OUTPUT->heading(get_string('gamecontent', 'local_games'), 3);
echo output::content_table($gameid, $rowurl, $pageurl);

echo $OUTPUT->footer();
