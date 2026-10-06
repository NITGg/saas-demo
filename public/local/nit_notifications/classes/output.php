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

namespace local_nit_notifications;

use html_writer;

/**
 * Small HTML pieces shared by the send and log pages.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output {

    /**
     * A notification's title and text, one block per language it was written in
     * (each in its own writing direction, labelled when there is more than one).
     *
     * @param string $title stored title (plain or multilang markup)
     * @param string $body stored text
     * @return string HTML
     */
    public static function versions(string $title, string $body): string {
        $titles = mlang::split($title);
        $bodies = mlang::split($body);
        $codes = array_unique(array_merge(array_keys($titles), array_keys($bodies)));
        $names = get_string_manager()->get_list_of_translations();
        $out = '';
        foreach ($codes as $code) {
            $dir = $code !== '' ? get_string_manager()->get_string('thisdirection', 'langconfig', null, $code) : null;
            $label = count($codes) > 1 && $code !== ''
                ? html_writer::div(s($names[$code] ?? $code), 'badge bg-secondary mb-2') : '';
            $out .= html_writer::div(
                $label .
                html_writer::tag('h5', s($titles[$code] ?? mlang::resolve($title, $code)), ['class' => 'mb-2']) .
                html_writer::div(nl2br(s($bodies[$code] ?? mlang::resolve($body, $code))), 'mb-2'),
                'mb-3', array_filter(['dir' => $dir, 'lang' => $code ?: null]));
        }
        return $out;
    }
}
