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

    if ($oldversion < 2026091302) {
        // VdoCipher recording storage (SaaS): the video id returned by VdoCipher
        // after Jibri's finalize uploads the recording. Replaces Bunny/MinIO.
        $table = new xmldb_table('academy_session_recordings');
        $field = new xmldb_field('vdocipher_videoid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'bunny_video_url');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091302, 'local', 'academysessions');
    }

    if ($oldversion < 2026091303) {
        // Recordings now go to Vimeo (the platform's chosen host) instead of VdoCipher.
        $table = new xmldb_table('academy_session_recordings');
        $field = new xmldb_field('vimeo_videoid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'vdocipher_videoid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091303, 'local', 'academysessions');
    }

    if ($oldversion < 2026101000) {
        // The session teacher's FIRST entry into the call. teacher_joined_at is the
        // student gate (cleared when the teacher leaves, overwritten on a rejoin), so
        // lateness reports read this field instead.
        $table = new xmldb_table('academy_live_sessions');
        $field = new xmldb_field('teacher_first_join', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'teacher_joined_at');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Back-fill from the teacher's attendance row (stamped on their first join);
        // failing that, a teacher_joined_at that is still set shows they did come in.
        $DB->execute("UPDATE {academy_live_sessions}
                         SET teacher_first_join = COALESCE(
                             (SELECT MIN(a.joined_at) FROM {academy_session_attendance} a
                               WHERE a.sessionid = {academy_live_sessions}.id
                                 AND a.userid = {academy_live_sessions}.teacherid),
                             teacher_joined_at)
                       WHERE teacher_first_join IS NULL");

        upgrade_plugin_savepoint(true, 2026101000, 'local', 'academysessions');
    }

    if ($oldversion < 2026101100) {
        // How a session ended (when, by whom, why) and when its teacher last left the call.
        $table = new xmldb_table('academy_live_sessions');
        $fields = [
            new xmldb_field('teacher_last_leave', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'teacher_first_join'),
            new xmldb_field('ended_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'teacher_last_leave'),
            new xmldb_field('ended_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'ended_at'),
            new xmldb_field('end_reason', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'ended_by'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Every stretch a user spent in the call (leave / rejoin), not just first in / last out.
        $table = new xmldb_table('academy_session_presence');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('joined_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('left_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('sessionid_fk', XMLDB_KEY_FOREIGN, ['sessionid'], 'academy_live_sessions', ['id']);
            $table->add_index('session_user_idx', XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'userid']);
            $dbman->create_table($table);

            // Existing attendance becomes one stretch each (first in → last out).
            $DB->execute('INSERT INTO {academy_session_presence} (sessionid, userid, joined_at, left_at)
                          SELECT sessionid, userid, joined_at, left_at FROM {academy_session_attendance}');
        }

        // Sessions already ended: when (their last change, the best we know). How is unknown.
        $DB->execute("UPDATE {academy_live_sessions} SET ended_at = timemodified
                       WHERE status = 'ended' AND ended_at IS NULL");

        upgrade_plugin_savepoint(true, 2026101100, 'local', 'academysessions');
    }

    return true;
}
