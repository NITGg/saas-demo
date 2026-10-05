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
 * Library functions for local_nit_flex.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Whether the academy's licence includes lesson packages. True when local_license is absent or
 * enforcement is off (license::has_feature() already returns true then).
 *
 * @return bool
 */
function local_nit_flex_enabled(): bool {
    if (!class_exists('\local_license\license')) {
        return true;
    }
    return \local_license\license::has_feature('packages');
}

/**
 * Stop a page when lesson packages are not on the licence. Call before header().
 *
 * @return void
 */
function local_nit_flex_require_enabled(): void {
    global $OUTPUT;
    if (local_nit_flex_enabled()) {
        return;
    }
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('feature_unavailable', 'local_nit_flex'), 'info');
    echo $OUTPUT->footer();
    exit;
}
