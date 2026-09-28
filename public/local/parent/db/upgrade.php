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
 * Upgrade script for local_parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_parent upgrade tasks.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_parent_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026092802) {
        require_once(__DIR__ . '/install.php');
        if (function_exists('xmldb_local_parent_install')) {
            xmldb_local_parent_install();
        }
        upgrade_plugin_savepoint(true, 2026092802, 'local', 'parent');
    }

    return true;
}
