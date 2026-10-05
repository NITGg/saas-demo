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

namespace local_nit_flex\local;

/**
 * Hook callbacks of local_nit_flex.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add "Available packages" for logged-in users and "Manage lesson packages" for managers.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        global $CFG;
        if (!isloggedin() || isguestuser()) {
            return;
        }
        require_once($CFG->dirroot . '/local/nit_flex/lib.php');
        if (!local_nit_flex_enabled()) {
            return;
        }
        $primary = $hook->get_primaryview();
        $primary->add(
            get_string('availablepackages', 'local_nit_flex'),
            new \moodle_url('/local/nit_flex/packages.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_nit_flex_packages'
        );
        if (has_capability('local/nit_flex:managepackages', \context_system::instance())) {
            $primary->add(
                get_string('managepackages', 'local_nit_flex'),
                new \moodle_url('/local/nit_flex/manage_packages.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_nit_flex_manage'
            );
        }
    }
}
