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
 * Reading a price a person typed — one rule for every price field (course,
 * lesson, package…), so "10.999" or "12abc" is refused instead of silently
 * becoming 11.00 or 12 (QA BUG-035).
 *
 * Accepted: digits with up to 2 decimals, Arabic-Indic digits, and "." "," or
 * the Arabic "٫" as the decimal point ("150", "99.5", "١٥٠", "49,50").
 * Refused: signs, exponents ("1e6"), letters, thousands separators ("1,500" has
 * 3 decimals), more than 2 decimals.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class price {

    /**
     * The typed price as a plain decimal string ("150", "99.5"), or null when it
     * is not a valid non-negative amount with at most 2 decimals.
     *
     * @param string|int|float $typed
     * @return string|null
     */
    public static function parse($typed): ?string {
        $text = trim((string) $typed);
        $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5',
            '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => '.']);
        return preg_match('/^\d+(\.\d{1,2})?$/', $text) ? $text : null;
    }
}
