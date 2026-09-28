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

/**
 * Standard library callbacks for local_parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Validate extra fields on signup form.
 * Ensures phone and parent phone fields reject arbitrary strings and letters.
 *
 * @param array $data Submitted signup form data.
 * @return array Array of errors [field => error message].
 */
function local_parent_validate_extend_signup_form(array $data): array {
    $errors = [];

    // Check all fields that represent a phone number.
    foreach ($data as $fieldname => $val) {
        if (!is_string($val) || trim($val) === '') {
            continue;
        }
        $isphonefield = (
            strpos($fieldname, 'phone') !== false ||
            $fieldname === 'phone1' ||
            $fieldname === 'phone2'
        );

        if ($isphonefield) {
            $raw = trim($val);
            // Must contain at least 7 digits and only valid phone characters (digits, spaces, +, -, parentheses).
            $digitsonly = preg_replace('/\D+/', '', $raw);
            if (!preg_match('/^[+]?[0-9\s\-()]{7,25}$/', $raw) || strlen($digitsonly) < 7) {
                $errors[$fieldname] = get_string('err_invalidphone', 'local_parent');
            }
        }
    }

    return $errors;
}
