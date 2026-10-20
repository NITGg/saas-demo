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
 * A Game activity's own content: where it comes from, and every row of it.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_games\admin\output;
use local_games\mlang;
use local_games\registry;
use mod_games\content;

$id      = required_param('id', PARAM_INT);           // Course module id.
$action  = optional_param('action', '', PARAM_ALPHA);
$rowid   = optional_param('rowid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'games');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/games:managecontent', $context);

$game = $DB->get_record('games', ['id' => $cm->instance], '*', MUST_EXIST);
$gameid = $game->gameid;

$pageurl = new moodle_url('/mod/games/content.php', ['id' => $cm->id]);
$rowurl  = new moodle_url('/mod/games/content_edit.php', ['cmid' => $cm->id]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(format_string($game->name) . ': ' . get_string('gamecontent', 'mod_games'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

if ($gameid === '' || registry::get_game($gameid) === null) {
    throw new moodle_exception('errormissinggame', 'mod_games', new moodle_url('/mod/games/view.php', ['id' => $cm->id]));
}

/**
 * The row an action names, refusing one from another activity.
 *
 * @param int $rowid
 * @param stdClass $game
 * @param moodle_url $pageurl
 * @return stdClass
 */
$requirerow = function (int $rowid, stdClass $game, moodle_url $pageurl): stdClass {
    $row = content::get_row($rowid, (int) $game->id);
    if (!$row) {
        throw new moodle_exception('errorunknownrow', 'local_games', $pageurl);
    }
    return $row;
};

// -- Actions --------------------------------------------------------------

$done = static function (moodle_url $url, ?string $message = null) {
    redirect($url, $message ?? get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
};

if ($action === 'setmode' && confirm_sesskey()) {
    $mode = games_clean_contentmode(required_param('mode', PARAM_INT));
    $DB->set_field('games', 'contentmode', $mode, ['id' => $game->id]);
    $done($pageurl);

} else if ($action === 'moveup' && $rowid > 0 && confirm_sesskey()) {
    $requirerow($rowid, $game, $pageurl);
    content::move_row($rowid, (int) $game->id, -1);
    redirect($pageurl);

} else if ($action === 'movedown' && $rowid > 0 && confirm_sesskey()) {
    $requirerow($rowid, $game, $pageurl);
    content::move_row($rowid, (int) $game->id, 1);
    redirect($pageurl);

} else if ($action === 'delete' && $rowid > 0 && confirm_sesskey() && $confirm) {
    $requirerow($rowid, $game, $pageurl);
    content::delete_row($rowid, (int) $game->id);
    $done($pageurl);

} else if ($action === 'copy' && confirm_sesskey()) {
    // Copying only makes sense alongside "own content", so the mode follows: a
    // teacher who copies the defaults in to edit them expects to see the edits.
    $written = content::copy_defaults($game, false);
    if ((int) $game->contentmode === content::MODE_DEFAULT) {
        $DB->set_field('games', 'contentmode', content::MODE_OWN, ['id' => $game->id]);
    }
    $done($pageurl, get_string('contentcopied', 'mod_games', $written));

} else if ($action === 'clear' && confirm_sesskey() && $confirm) {
    content::delete_all((int) $game->id);
    $done($pageurl);
}

// Adding and editing a row happen in a dialogue; the links still work without it.
$PAGE->requires->js_amd_inline(<<<'JS'
require(['core_form/modalform', 'core/str'], function(ModalForm, Str) {
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-mod-games-editrow]');
        if (!trigger) {
            return;
        }
        e.preventDefault();

        var rowid = parseInt(trigger.getAttribute('data-rowid'), 10) || 0;

        Str.get_string(rowid ? 'editrowtitle' : 'addrowtitle', 'local_games').then(function(title) {
            var form = new ModalForm({
                formClass: 'mod_games\\form\\content_form',
                args: {cmid: trigger.getAttribute('data-cmid'), rowid: rowid},
                modalConfig: {title: title},
                returnFocus: trigger
            });
            form.addEventListener(form.events.FORM_SUBMITTED, function() {
                window.location.reload();
            });
            return form.show();
        }).catch(function() {
            window.location.assign(trigger.href);
        });
    });
});
JS);

echo $OUTPUT->header();

// -- Confirmations --------------------------------------------------------

if ($action === 'delete' && $rowid > 0 && confirm_sesskey() && !$confirm) {
    $row = $requirerow($rowid, $game, $pageurl);
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
        new moodle_url($pageurl, ['action' => 'delete', 'rowid' => $rowid, 'sesskey' => sesskey(), 'confirm' => 1]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'clear' && confirm_sesskey() && !$confirm) {
    echo $OUTPUT->confirm(
        get_string('confirmclearcontent', 'mod_games'),
        new moodle_url($pageurl, ['action' => 'clear', 'sesskey' => sesskey(), 'confirm' => 1]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

// -- The page -------------------------------------------------------------

$definition = registry::get_game($gameid);
echo $OUTPUT->heading(
    html_writer::span($definition['emoji'] . ' ', '', ['aria-hidden' => 'true'])
        . get_string('gamecontent', 'mod_games') . ': ' . s(mlang::display(registry::name($gameid))),
    3
);

echo html_writer::div(
    html_writer::link(new moodle_url('/mod/games/view.php', ['id' => $cm->id]),
        '&larr; ' . get_string('backtoactivity', 'mod_games')),
    'mb-3'
);

echo html_writer::div(get_string('gamecontentintro', 'mod_games', s(mlang::display(registry::name($gameid)))),
    'mb-3');

// Where the content comes from: one button per mode, the current one marked.
$modes = '';
foreach (content::mode_options() as $mode => $label) {
    $current = (int) $game->contentmode === $mode;
    $modes .= html_writer::link(
        new moodle_url($pageurl, ['action' => 'setmode', 'mode' => $mode, 'sesskey' => sesskey()]),
        ($current ? '✓ ' : '') . $label,
        ['class' => 'btn ' . ($current ? 'btn-primary' : 'btn-outline-secondary') . ' me-2 mb-2',
            'aria-pressed' => $current ? 'true' : 'false']
    );
}
echo $OUTPUT->heading(get_string('contentmode', 'mod_games'), 4);
echo html_writer::div($modes, 'mb-1');
echo html_writer::div(get_string('contentmode_desc', 'mod_games'), 'text-muted small mb-3');

// What the class gets right now, in this language, and whether it is enough.
$mode = (int) $game->contentmode;
$own = content::own_resolved($game);
$playing = content::resolved($game);
$minimum = content::minimum($gameid);

if ($mode === content::MODE_DEFAULT) {
    $status = get_string('status_default', 'mod_games', count($playing));
    $level = 'info';
} else if ($mode === content::MODE_OWN && !$own) {
    $status = get_string('status_fallback', 'mod_games', count($playing));
    $level = 'warning';
} else {
    $status = get_string('status_own', 'mod_games', (object) ['own' => count($own), 'total' => count($playing)]);
    $level = 'success';
}
echo $OUTPUT->notification($status, $level, false);

if ($mode !== content::MODE_DEFAULT && $own && count($playing) < $minimum) {
    echo $OUTPUT->notification(get_string('toofewrows', 'mod_games',
        (object) ['minimum' => $minimum, 'count' => count($playing)]), 'warning', false);
}

// The rows.
echo $OUTPUT->heading(get_string('yourcontent', 'mod_games'), 4);
echo html_writer::div(get_string('shapedesc_' . registry::shape_for($gameid), 'local_games'), 'text-muted mb-3');

$buttons = html_writer::link($rowurl, get_string('addrow', 'local_games'), [
    'class' => 'btn btn-primary me-2 mb-2',
    'data-mod-games-editrow' => '1',
    'data-cmid' => $cm->id,
    'data-rowid' => 0,
]);
$buttons .= html_writer::link(new moodle_url($pageurl, ['action' => 'copy', 'sesskey' => sesskey()]),
    get_string('copydefaults', 'mod_games', \local_games\content::count_rows($gameid)),
    ['class' => 'btn btn-outline-secondary me-2 mb-2']);

$rows = content::rows((int) $game->id);
if ($rows) {
    $buttons .= html_writer::link(new moodle_url($pageurl, ['action' => 'clear', 'sesskey' => sesskey()]),
        get_string('clearcontent', 'mod_games'), ['class' => 'btn btn-outline-danger mb-2']);
}
echo html_writer::div($buttons, 'mb-3');

if (!$rows) {
    echo html_writer::div(get_string('nocontentyet', 'mod_games'), 'alert alert-secondary');
    echo $OUTPUT->footer();
    exit;
}

$fields = registry::fields_for($gameid);
$table = new html_table();
$table->attributes['class'] = 'generaltable local-games-content';
$table->head = ['#'];
foreach (array_keys($fields) as $field) {
    $table->head[] = get_string('field_' . $field, 'local_games');
}
$table->head[] = get_string('actions');

$last = count($rows) - 1;
foreach ($rows as $index => $row) {
    $cells = [$index + 1];
    foreach ($fields as $field => $fielddef) {
        $cells[] = output::cell($fielddef, (string) ($row->data[$field] ?? ''));
    }

    $common = ['rowid' => $row->id, 'sesskey' => sesskey()];
    $actions = '';
    if ($index > 0) {
        $actions .= html_writer::link(new moodle_url($pageurl, ['action' => 'moveup'] + $common),
            $OUTPUT->pix_icon('t/up', get_string('moveup')));
    }
    if ($index < $last) {
        $actions .= html_writer::link(new moodle_url($pageurl, ['action' => 'movedown'] + $common),
            $OUTPUT->pix_icon('t/down', get_string('movedown')));
    }
    $actions .= html_writer::link(new moodle_url($rowurl, ['rowid' => $row->id]),
        $OUTPUT->pix_icon('t/edit', get_string('edit')), [
            'data-mod-games-editrow' => '1',
            'data-cmid' => $cm->id,
            'data-rowid' => $row->id,
        ]);
    $actions .= html_writer::link(new moodle_url($pageurl, ['action' => 'delete'] + $common),
        $OUTPUT->pix_icon('t/delete', get_string('delete')));
    $cells[] = html_writer::div($actions, 'local-games-rowactions text-nowrap');

    $table->data[] = $cells;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
