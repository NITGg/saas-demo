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

namespace mod_jobform\output;

use local_jobform\field_types;
use local_jobform\mlang;
use html_writer;

/**
 * A clean, brand-styled read-only rendering of a student's submitted answers.
 *
 * Used instead of a frozen form so the values inherit the theme's brand colours.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_display {

    /**
     * Render the answers, grouped into the activity's sections.
     *
     * @param object[] $fields the activity's fields (in order)
     * @param array $groups group records keyed by id (in order)
     * @param array $answers fieldid => stored value
     * @return string HTML
     */
    public static function render(array $fields, array $groups, array $answers): string {
        $fields = array_values($fields);

        // Bucket fields by group (unknown/zero group id → ungrouped).
        $bygroup = [];
        foreach ($fields as $field) {
            $gid = (int) ($field->groupid ?? 0);
            if (!$gid || !isset($groups[$gid])) {
                $gid = 0;
            }
            $bygroup[$gid][] = $field;
        }
        $usesections = $groups && count(array_diff(array_keys($bygroup), [0])) > 0;

        $out = html_writer::start_div('jobform-readonly');

        if ($usesections) {
            foreach ($groups as $group) {
                if (empty($bygroup[$group->id])) {
                    continue;
                }
                $out .= self::section(mlang::resolve($group->name), $bygroup[$group->id], $answers);
            }
            if (!empty($bygroup[0])) {
                $out .= self::section(get_string('generalsection', 'mod_jobform'),
                    $bygroup[0], $answers);
            }
        } else {
            $out .= self::section(null, $fields, $answers);
        }

        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * Render one section: an optional heading and its label/value rows.
     *
     * @param string|null $heading
     * @param object[] $fields
     * @param array $answers
     * @return string
     */
    protected static function section(?string $heading, array $fields, array $answers): string {
        $out = html_writer::start_div('jobform-ro-section');
        if ($heading !== null) {
            $out .= html_writer::div(s($heading), 'jobform-ro-group');
        }
        foreach ($fields as $field) {
            $value = field_types::format_value($field, $answers[$field->id] ?? '');
            $out .= html_writer::div(
                html_writer::span(mlang::display($field->name), 'jobform-ro-label') .
                html_writer::span($value !== '' ? s($value)
                    : html_writer::span('—', 'text-muted'), 'jobform-ro-value'),
                'jobform-ro-row');
        }
        $out .= html_writer::end_div();
        return $out;
    }
}
