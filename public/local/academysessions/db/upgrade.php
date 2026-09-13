<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_academysessions_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062100) {
        $table = new xmldb_table('academy_live_sessions');
        $field = new xmldb_field('jitsiid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'googlemeetid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062100, 'local', 'academysessions');
    }

    if ($oldversion < 2026062401) {
        $table = new xmldb_table('academy_session_recordings');

        // Drop the index on sessionid before making it nullable (Moodle blocks
        // changing NOTNULL on a field that has a dependent index).
        $index = new xmldb_index('ses_ix', XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Make sessionid nullable so standalone (no session) recordings are supported.
        $field = new xmldb_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $dbman->change_field_notnull($table, $field);

        // Recreate the index (now on a nullable column).
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add cmid — links recording directly to a Jitsi activity (course module).
        $cmid_field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sessionid');
        if (!$dbman->field_exists($table, $cmid_field)) {
            $dbman->add_field($table, $cmid_field);
        }

        upgrade_plugin_savepoint(true, 2026062401, 'local', 'academysessions');
    }

    if ($oldversion < 2026063000) {
        // Track when the teacher actually enters the Jitsi call so students can be held
        // out of the room until the teacher is present (US-LS-3-1 lobby gate).
        $table = new xmldb_table('academy_live_sessions');
        $field = new xmldb_field('teacher_joined_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026063000, 'local', 'academysessions');
    }

    return true;
}
