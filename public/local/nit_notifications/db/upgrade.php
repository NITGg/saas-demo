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
 * Upgrade steps for local_nit_notifications.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_nit_notifications.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_notifications_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100801) {
        // A title now holds one version per language (multilang markup): give it room.
        $table = new xmldb_table('local_nit_notif');
        $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '1333', null, XMLDB_NOTNULL, null, null, 'courseid');
        $dbman->change_field_precision($table, $field);

        // Whether each recipient's email copy went out.
        $table = new xmldb_table('local_nit_notif_rcpt');
        $field = new xmldb_field('emailstatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'messageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026100801, 'local', 'nit_notifications');
    }

    if ($oldversion < 2026100802) {
        // Email only when the sender asks AND the user turned email on (the new
        // announcement_email row): Moodle must no longer email "announcement" on its own.
        // Provider defaults are written once, at install, so lock email off here.
        set_config('email_provider_local_nit_notifications_announcement_locked', 1, 'message');
        $name = 'message_provider_local_nit_notifications_announcement_enabled';
        $enabled = array_diff(explode(',', (string) get_config('message', $name)), ['email', '']);
        set_config($name, $enabled ? implode(',', $enabled) : 'none', 'message');

        upgrade_plugin_savepoint(true, 2026100802, 'local', 'nit_notifications');
    }

    if ($oldversion < 2026100900) {
        // Automatic notifications are sent with their own message provider (a row of
        // their own in the users' notification preferences).
        $table = new xmldb_table('local_nit_notif');
        $field = new xmldb_field('provider', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null,
            'local_nit_notifications/announcement', 'source');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Receipts of automatic notifications sent once per item (a quiz, a session reminder).
        $table = new xmldb_table('local_nit_notif_once');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('sourceitem', XMLDB_KEY_UNIQUE, ['source', 'itemid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026100900, 'local', 'nit_notifications');
    }

    return true;
}
