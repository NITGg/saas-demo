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
 * Upgrade steps for mod_jobform.
 *
 * @package    mod_jobform
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
function xmldb_jobform_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026081501) {
        // Add the optional group heading column to each activity's fields.
        $table = new xmldb_table('jobform_field');
        $field = new xmldb_field('groupname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081501, 'jobform');
    }

    if ($oldversion < 2026081502) {
        // Move from a free-text groupname to managed per-activity group entities.
        if (!$dbman->table_exists('jobform_group')) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/mod/jobform/db/install.xml', 'jobform_group');
        }

        $table = new xmldb_table('jobform_field');
        $groupid = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'name');
        if (!$dbman->field_exists($table, $groupid)) {
            $dbman->add_field($table, $groupid);
        }
        $index = new xmldb_index('groupid_idx', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Migrate existing per-activity group names into group rows and link fields.
        $groupname = new xmldb_field('groupname');
        if ($dbman->field_exists($table, $groupname)) {
            $pairs = $DB->get_recordset_sql(
                "SELECT DISTINCT jobformid, groupname FROM {jobform_field}
                  WHERE groupname IS NOT NULL AND groupname <> ''
               ORDER BY jobformid");
            $now = time();
            $sortbyform = [];
            foreach ($pairs as $pair) {
                $sort = $sortbyform[$pair->jobformid] ?? 0;
                $gid = $DB->insert_record('jobform_group', (object) [
                    'jobformid' => $pair->jobformid, 'name' => $pair->groupname, 'sortorder' => $sort,
                    'usermodified' => 0, 'timecreated' => $now, 'timemodified' => $now,
                ]);
                $sortbyform[$pair->jobformid] = $sort + 1;
                $DB->set_field_select('jobform_field', 'groupid', $gid,
                    'jobformid = ? AND groupname = ?', [$pair->jobformid, $pair->groupname]);
            }
            $pairs->close();
            $dbman->drop_field($table, $groupname);
        }

        upgrade_mod_savepoint(true, 2026081502, 'jobform');
    }

    return true;
}
