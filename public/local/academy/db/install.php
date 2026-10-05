<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. See <http://www.gnu.org/licenses/>.

/**
 * Install steps for local_academy.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create the academy's course custom fields and student profile fields.
 *
 * @return bool
 */
function xmldb_local_academy_install() {
    // Also creates the "is-special" course field.
    \local_academy\local\academic_structure::sync();
    return true;
}
