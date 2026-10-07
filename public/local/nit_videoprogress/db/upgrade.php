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
 * Upgrade steps.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_videoprogress_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026100700) {
        // The tracker never counted the first slice (before the first time update), so a
        // short video watched to the end stayed at 99%. Every other slice watched means
        // the student played it from the start: count the first one too.
        $missedfirst = '0' . str_repeat('1', \local_nit_videoprogress\progress::SLICES - 1);
        $rs = $DB->get_recordset_select('nit_video_progress', 'percent = :pct', ['pct' => \local_nit_videoprogress\progress::SLICES - 1],
            '', 'id, watched');
        foreach ($rs as $row) {
            if ($row->watched === $missedfirst) {
                $DB->update_record('nit_video_progress', (object) ['id' => $row->id,
                    'watched' => str_repeat('1', \local_nit_videoprogress\progress::SLICES),
                    'percent' => \local_nit_videoprogress\progress::SLICES]);
            }
        }
        $rs->close();
        upgrade_plugin_savepoint(true, 2026100700, 'local', 'nit_videoprogress');
    }

    return true;
}
