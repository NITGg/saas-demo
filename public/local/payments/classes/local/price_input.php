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

namespace local_payments\local;

/**
 * A course price as typed in the course settings, the course pricing page or the
 * app (BUG-035). The fields used to be PARAM_FLOAT, which Moodle converts before
 * validation, so "10.999" was saved as 11.00, "12abc" as 12 and "1,500" as 1 with
 * no message. Now the typed text is checked with the shared price rule
 * (\local_nit_core\helper\price) and refused with a clear error instead.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class price_input {

    /** The largest price the price column (NUMBER(10,2)) can hold. */
    const MAX = 99999999.99;

    /**
     * What is wrong with a typed course price.
     *
     * @param string|int|float $typed
     * @return string '' when valid, else the problem: "format", "positive" or "max"
     */
    public static function problem($typed): string {
        $text = \local_nit_core\helper\price::parse($typed);
        if ($text === null) {
            return 'format';
        }
        if ((float) $text <= 0) {
            return 'positive';
        }
        return (float) $text > self::MAX ? 'max' : '';
    }

    /**
     * The form error for a typed price ('' when valid).
     *
     * @param string|int|float $typed
     * @return string
     */
    public static function form_error($typed): string {
        $problem = self::problem($typed);
        return $problem === '' ? '' : get_string('error_price_' . $problem, 'local_payments', self::max_text());
    }

    /**
     * A valid typed price as a number (call problem() first).
     *
     * @param string|int|float $typed
     * @return float
     */
    public static function value($typed): float {
        return round((float) \local_nit_core\helper\price::parse($typed), 2);
    }

    /**
     * The largest price, formatted for the error message.
     *
     * @return string
     */
    private static function max_text(): string {
        return number_format(self::MAX, 2);
    }
}
