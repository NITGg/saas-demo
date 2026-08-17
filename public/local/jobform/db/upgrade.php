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
 * Upgrade steps for local_jobform.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply the schema/data changes for a given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_jobform_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026081501) {
        // Add the optional group heading column to the template fields.
        $table = new xmldb_table('local_jobform_field');
        $field = new xmldb_field('groupname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026081501, 'local', 'jobform');
    }

    if ($oldversion < 2026081502) {
        // Move from a free-text groupname to managed group entities referenced by id.
        if (!$dbman->table_exists('local_jobform_group')) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/local/jobform/db/install.xml', 'local_jobform_group');
        }

        $table = new xmldb_table('local_jobform_field');
        $groupid = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'name');
        if (!$dbman->field_exists($table, $groupid)) {
            $dbman->add_field($table, $groupid);
        }
        $index = new xmldb_index('groupid_idx', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Migrate any existing group names into group rows and link the fields.
        $groupname = new xmldb_field('groupname');
        if ($dbman->field_exists($table, $groupname)) {
            $names = $DB->get_fieldset_sql(
                "SELECT DISTINCT groupname FROM {local_jobform_field}
                  WHERE groupname IS NOT NULL AND groupname <> ''");
            $now = time();
            $sort = 0;
            foreach ($names as $gname) {
                $gid = $DB->insert_record('local_jobform_group', (object) [
                    'name' => $gname, 'sortorder' => $sort++,
                    'usermodified' => 0, 'timecreated' => $now, 'timemodified' => $now,
                ]);
                $DB->set_field('local_jobform_field', 'groupid', $gid, ['groupname' => $gname]);
            }
            $dbman->drop_field($table, $groupname);
        }

        upgrade_plugin_savepoint(true, 2026081502, 'local', 'jobform');
    }

    return true;
}
