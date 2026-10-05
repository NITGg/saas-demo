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
    $dbman = $DB->get_manager();

    if ($oldversion < 2026092802) {
        require_once(__DIR__ . '/install.php');
        if (function_exists('xmldb_local_parent_install')) {
            xmldb_local_parent_install();
        }
        upgrade_plugin_savepoint(true, 2026092802, 'local', 'parent');
    }

    if ($oldversion < 2026100400) {
        // Parent accounts are gone: a parent now follows a student from the
        // phone-gated dashboard without signing in. Drop what linked parent
        // accounts to students — the parent role (with every assignment of it)
        // and the link table. The parent phones themselves stay in the
        // students' profile fields, which is what the dashboard reads.
        $roleid = (int) get_config('local_parent', 'roleid');
        if (!$roleid) {
            $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'parent']);
        }
        if ($roleid && $DB->record_exists('role', ['id' => $roleid, 'shortname' => 'parent'])) {
            delete_role($roleid);
        }
        unset_config('roleid', 'local_parent');

        $table = new xmldb_table('local_parent_link');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        upgrade_plugin_savepoint(true, 2026100400, 'local', 'parent');
    }

    return true;
}
