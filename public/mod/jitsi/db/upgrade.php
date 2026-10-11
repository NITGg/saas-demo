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
 * mod_jitsi upgrade steps.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade mod_jitsi.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_jitsi_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101200) {
        // The waiting room is now kept by Moodle (a host lets each student in; hosts never
        // wait) and every stretch in a room is logged for its attendance page.
        $table = new xmldb_table('jitsi_admission');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('jitsiid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'waiting');
            $table->add_field('timerequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('decidedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('jitsiid_fk', XMLDB_KEY_FOREIGN, ['jitsiid'], 'jitsi', ['id']);
            $table->add_index('jitsi_user_unique', XMLDB_INDEX_UNIQUE, ['jitsiid', 'userid']);
            $table->add_index('jitsi_status_idx', XMLDB_INDEX_NOTUNIQUE, ['jitsiid', 'status']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('jitsi_presence');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('jitsiid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('host', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('joined_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('left_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('jitsiid_fk', XMLDB_KEY_FOREIGN, ['jitsiid'], 'jitsi', ['id']);
            $table->add_index('jitsi_user_idx', XMLDB_INDEX_NOTUNIQUE, ['jitsiid', 'userid']);
            $table->add_index('jitsi_joined_idx', XMLDB_INDEX_NOTUNIQUE, ['jitsiid', 'joined_at']);
            $dbman->create_table($table);
        }

        // Course rooms: students now wait for a host by default (asked for 11/10/2026).
        // The field's default follows; rooms linked to a live session ignore it.
        $field = new xmldb_field('lobby_enabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $dbman->change_field_default(new xmldb_table('jitsi'), $field);
        $DB->set_field('jitsi', 'lobby_enabled', 1);

        upgrade_mod_savepoint(true, 2026101200, 'jitsi');
    }

    if ($oldversion < 2026101300) {
        // The waiting room is Jitsi's own lobby again (the teacher admits students inside the
        // meeting, hosts let each other in), so Moodle's request table goes.
        $table = new xmldb_table('jitsi_admission');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        upgrade_mod_savepoint(true, 2026101300, 'jitsi');
    }

    return true;
}
