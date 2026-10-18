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

namespace local_nit_tutors\local;

/**
 * The teacher sign-up values are not valid; carries [field => message].
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invalid_signup extends \moodle_exception {

    /** @var string[] field => message */
    public array $errors;

    /**
     * Constructor.
     *
     * @param string[] $errors
     */
    public function __construct(array $errors) {
        $this->errors = $errors;
        parent::__construct('err_signup', 'local_nit_tutors', '', implode(' ', $errors));
    }
}
