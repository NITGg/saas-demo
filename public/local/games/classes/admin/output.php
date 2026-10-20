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

namespace local_games\admin;

use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use local_games\content;
use local_games\mlang;
use local_games\registry;
use moodle_url;

/**
 * The tables "Game control" is made of.
 *
 * There is no language selector anywhere here. A translatable value is one value
 * carrying {mlang} markup, and local_nit_mlang draws one input per installed
 * language over the field that holds it - the same way the rest of the site
 * handles a course name. Tables show the value in the reader's own language.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output {

    /**
     * The catalogue: every game, what it is called, how much material it holds
     * and how much it is played.
     *
     * @param array<string, array> $games as {@see registry::get_games()} returns them
     * @param array<string, \stdClass> $stats as {@see manager::get_play_stats()} returns them
     * @param array<string, int> $counts as {@see manager::get_row_counts()} returns them
     * @param moodle_url $detailurl the per-game page
     * @param moodle_url $actionurl this page, for the enable/disable links
     * @return string HTML
     */
    public static function game_table(array $games, array $stats, array $counts,
            moodle_url $detailurl, moodle_url $actionurl): string {

        $table = new html_table();
        $table->head = [
            get_string('colgame', 'local_games'),
            get_string('collevel', 'local_games'),
            get_string('colcontent', 'local_games'),
            get_string('colplayers', 'local_games'),
            get_string('colplays', 'local_games'),
            get_string('colstatus', 'local_games'),
            get_string('actions'),
        ];
        $table->attributes['class'] = 'generaltable local-games-catalogue';
        $table->data = [];
        $columns = count($table->head);

        // The section is a heading over the games in it, not a word repeated on
        // every row. It stays inside the table so the columns keep lining up.
        $bysection = [];
        foreach ($games as $id => $game) {
            $bysection[$game['category']][$id] = $game;
        }

        foreach (registry::get_categories() as $catkey => $catemoji) {
            if (empty($bysection[$catkey])) {
                continue;
            }

            $table->data[] = self::section_row($catkey, $catemoji, $columns);

            foreach (array_keys($bysection[$catkey]) as $id) {
                $game = $bysection[$catkey][$id];
                $detail = new moodle_url($detailurl, ['id' => $id]);
                $enabled = $game['status'] === registry::STATUS_LIVE;

                $name = html_writer::span($game['emoji'] . ' ', 'local-games-face', ['aria-hidden' => 'true'])
                    . html_writer::link($detail, mlang::display(registry::name($id)));
                if (manager::is_customised($id)) {
                    $name .= ' ' . html_writer::span(get_string('customised', 'local_games'),
                        'badge bg-info text-dark');
                }

                $rows = $counts[$id] ?? 0;
                $content = html_writer::link($detail,
                    get_string('rowcount', 'local_games', $rows),
                    ['class' => $rows ? '' : 'text-danger']);

                $row = $stats[$id] ?? null;

                $toggle = new moodle_url($actionurl, [
                    'action'  => $enabled ? 'disable' : 'enable',
                    'gameid'  => $id,
                    'sesskey' => sesskey(),
                ]);

                $cells = [
                    new html_table_cell($name),
                    new html_table_cell(str_repeat('⭐', (int) $game['level'])),
                    new html_table_cell($content),
                    new html_table_cell((string) ($row ? (int) $row->players : 0)),
                    new html_table_cell((string) ($row ? (int) $row->plays : 0)),
                    new html_table_cell($enabled
                        ? html_writer::span(get_string('statuslive', 'local_games'), 'badge bg-success')
                        : html_writer::span(get_string('statusoff', 'local_games'), 'badge bg-secondary')),
                    new html_table_cell(
                        html_writer::link($detail, get_string('edit'),
                            ['class' => 'btn btn-sm btn-outline-primary'])
                        . ' '
                        . html_writer::link($toggle,
                            $enabled ? get_string('disable') : get_string('enable'),
                            ['class' => 'btn btn-sm btn-outline-secondary'])),
                ];

                $tablerow = new html_table_row($cells);
                $tablerow->attributes['class'] = 'local-games-game';
                $table->data[] = $tablerow;
            }
        }

        return html_writer::table($table);
    }

    /**
     * The heading row that opens one section of the catalogue.
     *
     * @param string $catkey category key
     * @param string $catemoji its icon
     * @param int $columns how wide the table is
     * @return html_table_row
     */
    protected static function section_row(string $catkey, string $catemoji, int $columns): html_table_row {
        $cell = new html_table_cell(
            html_writer::span($catemoji . ' ', 'local-games-face', ['aria-hidden' => 'true'])
            . get_string('cat_' . $catkey, 'local_games')
        );
        $cell->colspan = $columns;
        $cell->header = true;

        $row = new html_table_row([$cell]);
        $row->attributes['class'] = 'local-games-section';

        return $row;
    }

    /**
     * One game's content: every row it owns, and the ways to change them.
     *
     * @param string $gameid game slug
     * @param moodle_url $rowurl the add/edit form
     * @param moodle_url $actionurl this page, for delete and reorder
     * @return string HTML
     */
    public static function content_table(string $gameid, moodle_url $rowurl, moodle_url $actionurl): string {
        $fields = registry::fields_for($gameid);
        $rows = content::rows($gameid);

        $out = html_writer::div(
            get_string('shapedesc_' . registry::shape_for($gameid), 'local_games'),
            'text-muted mb-3'
        );

        // The href is the real page, and the data attribute is what turns it
        // into a dialogue. A browser with no JavaScript follows the link and
        // gets the same form; nothing here is only reachable through the modal.
        $buttons = html_writer::link(new moodle_url($rowurl, ['id' => $gameid]),
            get_string('addrow', 'local_games'), [
                'class' => 'btn btn-primary',
                'data-local-games-editrow' => '1',
                'data-gameid' => $gameid,
                'data-rowid' => 0,
            ]);
        $buttons .= ' ' . html_writer::link(
            new moodle_url($actionurl, ['action' => 'restore', 'sesskey' => sesskey()]),
            get_string('restoredefaults', 'local_games'),
            ['class' => 'btn btn-outline-secondary']
        );
        $out .= html_writer::div($buttons, 'mb-3');

        if (!$rows) {
            $out .= html_writer::div(get_string('contentempty', 'local_games'), 'alert alert-warning');
            return $out;
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-games-content';
        $table->head = [self::selectall_checkbox()];
        foreach (array_keys($fields) as $field) {
            $table->head[] = get_string('field_' . $field, 'local_games');
        }
        $table->head[] = get_string('actions');
        $table->data = [];

        $last = count($rows) - 1;
        foreach ($rows as $index => $row) {
            $cells = [new html_table_cell(self::row_checkbox((int) $row->id))];
            foreach ($fields as $field => $definition) {
                $cells[] = new html_table_cell(
                    self::cell($definition, (string) ($row->data[$field] ?? '')));
            }
            $cells[] = new html_table_cell(
                self::row_actions($row, $gameid, $rowurl, $actionurl, $index === 0, $index === $last));
            $table->data[] = new html_table_row($cells);
        }

        $out .= self::bulk_form($actionurl, html_writer::table($table));

        return $out;
    }

    /**
     * The reorder / edit / delete buttons of one content row.
     *
     * @param \stdClass $row the content row
     * @param string $gameid game slug
     * @param moodle_url $rowurl the edit form
     * @param moodle_url $actionurl this page
     * @param bool $isfirst hide "move up"
     * @param bool $islast hide "move down"
     * @return string HTML
     */
    protected static function row_actions(\stdClass $row, string $gameid,
            moodle_url $rowurl, moodle_url $actionurl, bool $isfirst, bool $islast): string {
        global $OUTPUT;

        $common = ['rowid' => $row->id, 'sesskey' => sesskey()];
        $out = '';

        if (!$isfirst) {
            $out .= html_writer::link(new moodle_url($actionurl, ['action' => 'moveup'] + $common),
                $OUTPUT->pix_icon('t/up', get_string('moveup')));
        }
        if (!$islast) {
            $out .= html_writer::link(new moodle_url($actionurl, ['action' => 'movedown'] + $common),
                $OUTPUT->pix_icon('t/down', get_string('movedown')));
        }

        $out .= html_writer::link(new moodle_url($rowurl, ['id' => $gameid, 'rowid' => $row->id]),
            $OUTPUT->pix_icon('t/edit', get_string('edit')), [
                'data-local-games-editrow' => '1',
                'data-gameid' => $gameid,
                'data-rowid' => $row->id,
            ]);
        $out .= html_writer::link(new moodle_url($actionurl, ['action' => 'delete'] + $common),
            $OUTPUT->pix_icon('t/delete', get_string('delete')));

        return html_writer::div($out, 'local-games-rowactions text-nowrap');
    }

    /**
     * One cell, drawn for the kind of value it holds.
     *
     * @param array $definition the field definition from the game's shape
     * @param string $value the stored value
     * @return string HTML
     */
    public static function cell(array $definition, string $value): string {
        if ($value === '') {
            return html_writer::span('—', 'text-muted');
        }

        switch ($definition['type']) {
            case 'hex':
                $swatch = html_writer::span('', 'local-games-swatch', [
                    'style' => 'background: '
                        . (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : 'transparent') . ';',
                    'aria-hidden' => 'true',
                ]);
                return $swatch . ' ' . s($value);

            case 'bool':
                return $value === '1'
                    ? html_writer::span(get_string('istrue_yes', 'local_games'), 'badge bg-success')
                    : html_writer::span(get_string('istrue_no', 'local_games'), 'badge bg-danger');

            case 'select':
                $label = 'option_' . $definition['options'] . '_' . $value;
                return html_writer::span(
                    get_string_manager()->string_exists($label, 'local_games')
                        ? get_string($label, 'local_games')
                        : s($value),
                    'badge bg-light text-dark'
                );

            case 'int':
                return s($value);

            case 'emoji':
                return html_writer::span(s($value), 'local-games-face');
        }

        // A display string. Show it in the reader's language, and say so when
        // the row exists only in another one - that is the difference between a
        // row a child will meet and a row that will never be drawn for them.
        $shown = mlang::display($value);
        if (!mlang::has_language($value)) {
            return html_writer::span(s($shown), 'text-muted')
                . ' ' . html_writer::span(get_string('otherlanguageonly', 'local_games'),
                    'badge bg-warning text-dark');
        }

        return s($shown);
    }

    // ------------------------------------------------------------------
    // Selecting several rows to delete at once.
    // ------------------------------------------------------------------

    /**
     * One row's display strings joined into a line, for a confirmation.
     *
     * @param array $data the row's stored fields
     * @param string $gameid game slug
     * @return string plain text
     */
    public static function summary(array $data, string $gameid): string {
        $summary = [];
        foreach (registry::fields_for($gameid) as $field => $definition) {
            $value = (string) ($data[$field] ?? '');
            if ($value !== '' && !empty($definition['translatable'])) {
                $summary[] = mlang::display($value);
            }
        }
        return implode(' — ', $summary);
    }

    /**
     * The "select all" box for a content table's header.
     *
     * @return string HTML
     */
    public static function selectall_checkbox(): string {
        return html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'data-local-games-selectall' => '1',
            'title' => get_string('selectall'),
            'aria-label' => get_string('selectall'),
        ]);
    }

    /**
     * The box that selects one row.
     *
     * @param int $id row id
     * @return string HTML
     */
    public static function row_checkbox(int $id): string {
        return html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'name' => 'rowids[]',
            'value' => $id,
            'data-local-games-select' => '1',
            'aria-label' => get_string('select'),
        ]);
    }

    /**
     * A content table inside the form that deletes the selected rows.
     *
     * The form posts action=deleteselected and rowids[] back to the page, which
     * asks for confirmation before deleting. The button stays disabled until a
     * row is ticked, and shows how many are.
     *
     * @param moodle_url $pageurl the page that handles action=deleteselected
     * @param string $tablehtml the table, with row_checkbox() in each row
     * @return string HTML
     */
    public static function bulk_form(moodle_url $pageurl, string $tablehtml): string {
        global $PAGE;

        $PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var sync = function(form) {
        var boxes = form.querySelectorAll('[data-local-games-select]');
        var ticked = form.querySelectorAll('[data-local-games-select]:checked').length;
        var all = form.querySelector('[data-local-games-selectall]');
        var button = form.querySelector('[data-local-games-deleteselected]');
        if (all) {
            all.checked = boxes.length > 0 && ticked === boxes.length;
            all.indeterminate = ticked > 0 && ticked < boxes.length;
        }
        if (button) {
            button.disabled = ticked === 0;
            button.querySelector('[data-count]').textContent = ticked ? ' (' + ticked + ')' : '';
        }
    };
    document.addEventListener('change', function(e) {
        var form = e.target.closest('[data-local-games-bulk]');
        if (!form) {
            return;
        }
        if (e.target.matches('[data-local-games-selectall]')) {
            form.querySelectorAll('[data-local-games-select]').forEach(function(box) {
                box.checked = e.target.checked;
            });
        }
        sync(form);
    });
    document.querySelectorAll('[data-local-games-bulk]').forEach(sync);
});
JS);

        $button = html_writer::tag('button',
            get_string('deleteselected', 'local_games') . html_writer::span('', '', ['data-count' => '1']), [
                'type' => 'submit',
                'class' => 'btn btn-outline-danger mb-2',
                'data-local-games-deleteselected' => '1',
                'disabled' => 'disabled',
            ]);

        $out = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deleteselected']);
        foreach ($pageurl->params() as $name => $value) {
            $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        $out .= html_writer::div($button, 'mb-2');
        $out .= $tablehtml;

        return html_writer::tag('form', $out, [
            'method' => 'post',
            'action' => $pageurl->out_omit_querystring(),
            'data-local-games-bulk' => '1',
        ]);
    }
}
