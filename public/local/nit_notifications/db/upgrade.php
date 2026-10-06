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

    return true;
}
