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

namespace local_nit_core\helper;

/**
 * Arabic text folded for searching (QA BUG-056): people type the same word in
 * several ways — أحمد / احمد, مدرسة / مدرسه, على / علي, with or without
 * diacritics — and expect the same results. fold() is for text compared in PHP,
 * sql_fold() the same folding of a column inside an SQL LIKE.
 *
 * Folded: أ إ آ ٱ → ا · ة → ه · ى → ي · ؤ → و · ئ → ي · diacritics (tashkeel) and
 * the tatweel ـ removed · Arabic-Indic digits → 0-9 · lower case.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class arabic {

    /** Letter forms and their search form. */
    private const LETTERS = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];

    /** Marks dropped: tanween, fatha … sukun, shadda, superscript alef, tatweel. */
    private const MARKS = ["\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}", "\u{0651}", "\u{0652}",
        "\u{0670}", "\u{0640}"];

    /**
     * Text in its search form.
     *
     * @param string $text
     * @return string
     */
    public static function fold(string $text): string {
        $text = str_replace(self::MARKS, '', $text);
        return \core_text::strtolower(strtr($text, self::LETTERS));
    }

    /**
     * SQL for a column in its search form (the same folding as fold(), except
     * lower case: use it with $DB->sql_like(..., false) and a fold()ed value).
     *
     * @param string $field column or expression
     * @return string
     */
    public static function sql_fold(string $field): string {
        foreach (self::MARKS as $mark) {
            $field = "REPLACE($field, '$mark', '')";
        }
        foreach (self::LETTERS as $from => $to) {
            $field = "REPLACE($field, '$from', '$to')";
        }
        return $field;
    }
}
