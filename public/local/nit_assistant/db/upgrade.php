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

/**
 * Upgrade steps.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from an older version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_assistant_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101202) {
        // Leads captured by the sales assistant (phase 1).
        $table = new xmldb_table('local_nit_asst_lead');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('convid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('phone', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('interest', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('consenttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'new');
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('assignedto', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('convid', XMLDB_KEY_FOREIGN, ['convid'], 'local_nit_asst_conv', ['id']);
        $table->add_index('statuscreated', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101202, 'local', 'nit_assistant');
    }

    return true;
}
