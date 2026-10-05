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

namespace local_nit_flex\local;

/**
 * Arabic/English text stored in one field with the multilang2 filter syntax.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mlang {

    /**
     * Join the two languages. One empty side stores the other as plain text.
     *
     * @param string $ar
     * @param string $en
     * @return string
     */
    public static function join(string $ar, string $en): string {
        $ar = trim($ar);
        $en = trim($en);
        if ($ar === '' || $en === '') {
            return $ar !== '' ? $ar : $en;
        }
        return '{mlang ar}' . $ar . '{mlang}{mlang en}' . $en . '{mlang}';
    }

    /**
     * Split stored text back into [ar, en]. Plain text counts as Arabic.
     *
     * @param string|null $text
     * @return string[] [ar, en]
     */
    public static function split(?string $text): array {
        $text = (string) $text;
        $ar = preg_match('/\{mlang ar\}(.*?)\{mlang\}/su', $text, $m) ? $m[1] : null;
        $en = preg_match('/\{mlang en\}(.*?)\{mlang\}/su', $text, $m) ? $m[1] : null;
        if ($ar === null && $en === null) {
            return [$text, ''];
        }
        return [(string) $ar, (string) $en];
    }
}
