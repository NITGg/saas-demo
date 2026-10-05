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

namespace local_nit_finance\local;

/**
 * Money helpers. Amounts are stored as integer minor units (piastres); people
 * type and read pounds.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class money {

    /**
     * Pounds as typed by a person ("150", "99.5", "١٥٠") to minor units.
     *
     * @param string|float|int $major
     * @return int|null null when it is not a valid non-negative amount
     */
    public static function to_minor($major): ?int {
        $text = trim((string) $major);
        // Arabic-Indic digits and the Arabic decimal separator.
        $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5',
            '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => '.']);
        if ($text === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $text)) {
            return null;
        }
        return (int) round(((float) $text) * 100);
    }

    /**
     * Minor units as pounds for a form field ("150" or "99.50").
     *
     * @param int $minor
     * @return string
     */
    public static function to_major(int $minor): string {
        return $minor % 100 === 0 ? (string) intdiv($minor, 100) : number_format($minor / 100, 2, '.', '');
    }

    /**
     * Minor units for display, with the currency ("150 ج.م").
     *
     * @param int $minor
     * @return string
     */
    public static function format(int $minor): string {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);
        $number = $abs % 100 === 0 ? number_format(intdiv($abs, 100)) : number_format($abs / 100, 2);
        return $sign . get_string('amountwithcurrency', 'local_nit_finance', $number);
    }
}
