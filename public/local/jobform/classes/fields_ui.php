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

namespace local_jobform;

use moodle_url;
use html_writer;
use html_table;
use html_table_row;
use html_table_cell;

/**
 * Renders the "fields" management table. Shared by the admin template editor
 * (local_jobform manage.php) and the per-activity editor (mod_jobform).
 *
 * Fields are shown grouped: each group is a header row inside the table with
 * its fields listed underneath, and ungrouped fields fall under a final section.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields_ui {

    /**
     * Render the full fields panel: action buttons and the grouped table.
     *
     * @param array $fields field records (->id, ->name, ->groupid, ->type, ->configdata, ->required)
     * @param moodle_url $editurl page that shows the add/edit field form
     * @param moodle_url $actionurl page that handles field/group delete/move (gets ?fieldaction=…&sesskey=)
     * @param array $opts optional extras:
     *      'groups'        => group records keyed by id (defines the sections)
     *      'groupediturl'  => moodle_url to the add/edit group page (enables "Add group" + group editing)
     *      'usedefaulturl' => moodle_url that resets to the default template (enables "Use default fields")
     * @return string HTML
     */
    public static function render(array $fields, moodle_url $editurl, moodle_url $actionurl,
            array $opts = []): string {
        $groups = $opts['groups'] ?? [];
        $groupediturl = $opts['groupediturl'] ?? null;
        $usedefaulturl = $opts['usedefaulturl'] ?? null;

        $out = html_writer::start_div('local-jobform-fields');

        // Action buttons: Add field · Add group · Use default fields (same style).
        $buttons = html_writer::link(new moodle_url($editurl),
            get_string('addfield', 'local_jobform'), ['class' => 'btn btn-primary']);
        if ($groupediturl) {
            $buttons .= ' ' . html_writer::link(new moodle_url($groupediturl),
                get_string('addgroup', 'local_jobform'), ['class' => 'btn btn-outline-primary']);
        }
        if ($usedefaulturl) {
            $buttons .= ' ' . html_writer::link($usedefaulturl,
                get_string('usedefaultfields', 'local_jobform'), ['class' => 'btn btn-outline-primary']);
        }
        $out .= html_writer::div($buttons, 'mb-3');

        if (!$fields && !$groups) {
            $out .= html_writer::div(get_string('nofields', 'local_jobform'), 'alert alert-info');
            $out .= html_writer::end_div();
            return $out;
        }

        // Rank fields by global sort order so move up/down bounds stay correct.
        $order = array_values($fields);
        $total = count($order);
        $rank = [];
        foreach ($order as $idx => $f) {
            $rank[(int) $f->id] = $idx;
        }

        // Bucket fields by group (unknown/zero group id falls to the ungrouped bucket).
        $bygroup = [];
        foreach ($order as $f) {
            $gid = (int) ($f->groupid ?? 0);
            if (!$gid || !isset($groups[$gid])) {
                $gid = 0;
            }
            $bygroup[$gid][] = $f;
        }

        $table = new html_table();
        $table->head = [
            get_string('fieldname', 'local_jobform'),
            get_string('fieldtype', 'local_jobform'),
            get_string('fielddetails', 'local_jobform'),
            get_string('fieldrequired', 'local_jobform'),
            get_string('actions', 'local_jobform'),
        ];
        $table->attributes['class'] = 'generaltable local-jobform-fields-table';
        $colspan = count($table->head);

        if ($groups) {
            // One section per group, in order, then a section for ungrouped fields.
            foreach ($groups as $group) {
                $table->data[] = self::group_header_row($group, $colspan, $editurl, $groupediturl, $actionurl);
                if (empty($bygroup[$group->id])) {
                    $table->data[] = self::empty_row($colspan);
                    continue;
                }
                foreach ($bygroup[$group->id] as $f) {
                    $table->data[] = self::field_row($f, $editurl, $actionurl,
                        $rank[$f->id] === 0, $rank[$f->id] === $total - 1);
                }
            }
            if (!empty($bygroup[0])) {
                $table->data[] = self::group_header_row(null, $colspan, $editurl, null, null);
                foreach ($bygroup[0] as $f) {
                    $table->data[] = self::field_row($f, $editurl, $actionurl,
                        $rank[$f->id] === 0, $rank[$f->id] === $total - 1);
                }
            }
        } else {
            // No groups defined — a plain flat list.
            foreach ($order as $f) {
                $table->data[] = self::field_row($f, $editurl, $actionurl,
                    $rank[$f->id] === 0, $rank[$f->id] === $total - 1);
            }
        }

        $out .= html_writer::table($table);
        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * Build one field data row (cells match the table head).
     *
     * @param object $field
     * @param moodle_url $editurl
     * @param moodle_url $actionurl
     * @param bool $isfirst
     * @param bool $islast
     * @return array
     */
    protected static function field_row(object $field, moodle_url $editurl,
            moodle_url $actionurl, bool $isfirst, bool $islast): array {
        return [
            mlang::display($field->name),
            get_string(field_types::all()[$field->type] ?? 'fieldtype_text', 'local_jobform'),
            self::describe($field),
            $field->required ? get_string('yes') : get_string('no'),
            self::row_actions($field, $editurl, $actionurl, $isfirst, $islast),
        ];
    }

    /**
     * Build a full-width section header row for a group (or the ungrouped section).
     *
     * @param object|null $group the group record, or null for the ungrouped section
     * @param int $colspan number of table columns to span
     * @param moodle_url $editurl the add-field page (used for "add field to this group")
     * @param moodle_url|null $groupediturl the add/edit group page (null hides group edit)
     * @param moodle_url|null $actionurl handles group delete (null hides group delete)
     * @return html_table_row
     */
    protected static function group_header_row(?object $group, int $colspan, moodle_url $editurl,
            ?moodle_url $groupediturl, ?moodle_url $actionurl): html_table_row {
        global $OUTPUT;

        $label = $group ? mlang::display($group->name) : get_string('nogroup', 'local_jobform');
        $title = html_writer::tag('strong', $label);

        $tools = '';
        if ($group) {
            // Add a field already assigned to this group.
            $addfield = new moodle_url($editurl, ['groupid' => $group->id]);
            $tools .= $OUTPUT->action_icon($addfield,
                new \pix_icon('t/add', get_string('addfieldtogroup', 'local_jobform')));
            if ($groupediturl) {
                $edit = new moodle_url($groupediturl, ['groupid' => $group->id]);
                $tools .= $OUTPUT->action_icon($edit, new \pix_icon('t/edit', get_string('editgroup', 'local_jobform')));
            }
            if ($actionurl) {
                $delete = new moodle_url($actionurl,
                    ['groupaction' => 'delete', 'groupid' => $group->id, 'sesskey' => sesskey()]);
                $tools .= $OUTPUT->action_icon($delete, new \pix_icon('t/delete', get_string('delete')));
            }
        }

        $content = html_writer::div(
            $title . html_writer::span($tools, 'local-jobform-group-tools'),
            'd-flex justify-content-between align-items-center');

        $cell = new html_table_cell($content);
        $cell->colspan = $colspan;
        $cell->attributes['class'] = 'local-jobform-group-header'
            . ($group ? '' : ' local-jobform-group-header-none');
        $row = new html_table_row([$cell]);
        $row->attributes['class'] = 'local-jobform-group-row';
        return $row;
    }

    /**
     * A muted "no fields in this group yet" row.
     *
     * @param int $colspan
     * @return html_table_row
     */
    protected static function empty_row(int $colspan): html_table_row {
        $cell = new html_table_cell(
            html_writer::span(get_string('nofieldsingroup', 'local_jobform'), 'text-muted'));
        $cell->colspan = $colspan;
        return new html_table_row([$cell]);
    }

    /**
     * A short human description of a field's type-specific configuration.
     *
     * @param object $field
     * @return string
     */
    protected static function describe(object $field): string {
        $config = field_types::decode_config($field->configdata ?? null);
        if (field_types::has_options($field->type)) {
            $mode = $config['multiple']
                ? get_string('fieldmultiple', 'local_jobform')
                : get_string('fieldsingle', 'local_jobform');
            $resolved = array_map([mlang::class, 'resolve'], $config['options']);
            $preview = implode(', ', array_slice($resolved, 0, 5));
            if (count($resolved) > 5) {
                $preview .= ' …';
            }
            return html_writer::span(s($preview), 'text-muted') .
                html_writer::empty_tag('br') .
                html_writer::span('(' . $mode . ')', 'badge badge-secondary');
        }
        if (field_types::is_fixed($field->type)) {
            return html_writer::span(s(mlang::resolve($config['fixedvalue'])), 'text-muted');
        }
        return html_writer::span('—', 'text-muted');
    }

    /**
     * Build the per-row action buttons (edit, delete, move up/down).
     *
     * @param object $field
     * @param moodle_url $editurl
     * @param moodle_url $actionurl
     * @param bool $isfirst
     * @param bool $islast
     * @return string
     */
    protected static function row_actions(object $field, moodle_url $editurl,
            moodle_url $actionurl, bool $isfirst, bool $islast): string {
        global $OUTPUT;

        $buttons = '';

        // Edit.
        $edit = new moodle_url($editurl, ['fieldid' => $field->id]);
        $buttons .= $OUTPUT->action_icon($edit, new \pix_icon('t/edit', get_string('edit')));

        // Move up.
        if (!$isfirst) {
            $up = new moodle_url($actionurl,
                ['fieldaction' => 'moveup', 'fieldid' => $field->id, 'sesskey' => sesskey()]);
            $buttons .= $OUTPUT->action_icon($up, new \pix_icon('t/up', get_string('moveup')));
        }
        // Move down.
        if (!$islast) {
            $down = new moodle_url($actionurl,
                ['fieldaction' => 'movedown', 'fieldid' => $field->id, 'sesskey' => sesskey()]);
            $buttons .= $OUTPUT->action_icon($down, new \pix_icon('t/down', get_string('movedown')));
        }
        // Delete. The action page shows an $OUTPUT->confirm() dialog before deleting.
        $delete = new moodle_url($actionurl,
            ['fieldaction' => 'delete', 'fieldid' => $field->id, 'sesskey' => sesskey()]);
        $buttons .= $OUTPUT->action_icon($delete, new \pix_icon('t/delete', get_string('delete')));

        return $buttons;
    }
}
