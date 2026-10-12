<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\security;

/**
 * Masks personal data before anything is written to the tool audit or the
 * conversation's tool rows: secrets are dropped by key; emails, phone numbers
 * and card numbers are masked in any text.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class redaction {
    /** Keys whose values are never kept. */
    private const SECRET_KEYS = '/^(pass(word)?|token|apikey|api_key|secret|otp|code|cvv|cvc|card(number)?|pan'
        . '|authorization|cookie|sesskey)$/i';

    /**
     * Mask personal data in free text.
     *
     * @param string $text
     * @return string
     */
    public static function text(string $text): string {
        // Card numbers: 13–19 digits with optional spaces/dashes.
        $text = preg_replace('/\b(?:\d[ -]?){12,18}\d\b/', '[card]', $text);
        // Emails: keep the first character and the domain.
        $text = preg_replace_callback(
            '/([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/',
            static fn(array $m): string => $m[1] . '***@' . $m[2],
            $text
        );
        // Phone numbers: 8+ digits (optional +, spaces, dashes) → keep the last 3.
        $text = preg_replace_callback('/\+?\d[\d \-]{6,}\d/', static function (array $m): string {
            $digits = preg_replace('/\D/', '', $m[0]);
            return strlen($digits) < 8 ? $m[0] : '[phone …' . substr($digits, -3) . ']';
        }, $text);
        return $text;
    }

    /**
     * Mask a value of any shape (arrays/objects recursively).
     *
     * @param mixed $value
     * @param int $depth
     * @return mixed
     */
    public static function value(mixed $value, int $depth = 0): mixed {
        if ($depth > 6) {
            return '[…]';
        }
        if (is_string($value)) {
            return self::text($value);
        }
        if (is_array($value) || is_object($value)) {
            $out = [];
            foreach ((array) $value as $k => $v) {
                $out[$k] = is_string($k) && preg_match(self::SECRET_KEYS, $k) ? '[redacted]' : self::value($v, $depth + 1);
            }
            return $out;
        }
        return $value;
    }
}
