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
 * Upgrade steps of local_nit_analytics.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_nit_analytics.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_analytics_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101900) {
        // Funnel: landings and purchase conversions.
        foreach (['local_nit_analytics_landing', 'local_nit_analytics_conv'] as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $table);
            }
        }
        // Consent is required by default: no tracker loads before the visitor accepts.
        if (get_config('local_nit_analytics', 'requireconsent') === false) {
            set_config('requireconsent', 1, 'local_nit_analytics');
        }
        upgrade_plugin_savepoint(true, 2026101900, 'local', 'nit_analytics');
    }

    return true;
}
