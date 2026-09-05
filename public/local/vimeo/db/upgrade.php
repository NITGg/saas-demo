<?php
defined('MOODLE_INTERNAL') || die();

/**
 * local_vimeo upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_vimeo_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Defensive: ensure the mapping table exists on installs that predate it
    // (install.xml only runs on a truly fresh install).
    if ($oldversion < 2026090500) {
        $table = new xmldb_table('local_vimeo_videos');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('videoid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('videohash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'PRE-Upload');
        $table->add_field('length', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('videoid_idx', XMLDB_INDEX_UNIQUE, ['videoid']);
        $table->add_index('cmid_idx', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090500, 'local', 'vimeo');
    }

    return true;
}
