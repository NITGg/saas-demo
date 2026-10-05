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
 * Upgrade steps for theme_nit.
 *
 * @package   theme_nit
 * @copyright 2026 NIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the theme upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_theme_nit_upgrade($oldversion) {
    if ($oldversion < 2026100401) {
        // The Bassthalk home moved from code-owned sections (settings bthhome_*,
        // pictures in theme file areas) to nit_section blocks (homepage template
        // "bassthalk"): drop the settings and pictures nothing reads any more.
        global $DB;
        $DB->delete_records_select('config_plugins', "plugin = 'theme_nit' AND " . $DB->sql_like('name', ':n'),
            ['n' => 'bthhome\_%']);
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        foreach ($DB->get_fieldset_select('files', 'DISTINCT filearea', "component = 'theme_nit' AND contextid = :ctx AND ("
                . $DB->sql_like('filearea', ':a') . ' OR ' . $DB->sql_like('filearea', ':b') . ')',
                ['ctx' => $syscontext->id, 'a' => 'bthhero%', 'b' => 'bthhowimage%']) as $area) {
            $fs->delete_area_files($syscontext->id, 'theme_nit', $area);
        }
        cache_helper::purge_by_definition('core', 'config');
        upgrade_plugin_savepoint(true, 2026100401, 'theme', 'nit');
    }

    if ($oldversion < 2026100500) {
        // A fresh server has no Site-home sections (they're nit_section blocks)
        // and sits on the default template, which also hides "Add a block": give
        // it the Bassthalk home. Sites that already have sections are left alone.
        global $DB;
        if (!$DB->record_exists('block_instances', ['blockname' => 'nit_section', 'pagetypepattern' => 'site-index'])) {
            \theme_nit\local\template_applier::apply('bassthalk');
        }
        upgrade_plugin_savepoint(true, 2026100500, 'theme', 'nit');
    }
    return true;
}
