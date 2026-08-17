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

namespace local_nit_core\exception;

/**
 * Base exception for the NIT Core SDK.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nit_exception extends \moodle_exception {
    /**
     * Construct a NIT exception mapped to a local_nit_core language string.
     *
     * @param string $errorcode language string key (without the "error_" caller prefix is allowed)
     * @param mixed $a language string placeholder value
     * @param string $debuginfo optional developer debug detail
     */
    public function __construct(string $errorcode, $a = null, string $debuginfo = '') {
        parent::__construct($errorcode, 'local_nit_core', '', $a, $debuginfo);
    }
}
