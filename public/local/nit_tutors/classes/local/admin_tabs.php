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

namespace local_nit_tutors\local;

/**
 * The tabs of the one admin page "Teacher applications" (Local plugins): applications, application
 * fields, badges, settings. Every tab page opens the same admin node, so the admin menu stays on it.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_tabs {

    /** Tab => [script, capability]. */
    const TABS = [
        'applications' => ['/local/nit_tutors/admin/applications.php', 'local/nit_tutors:review'],
        'fields' => ['/local/nit_tutors/admin/fields.php', 'local/nit_tutors:manage'],
        'badges' => ['/local/nit_tutors/admin/badges.php', 'local/nit_tutors:manage'],
        'settings' => ['/local/nit_tutors/admin/settings.php', 'local/nit_tutors:manage'],
    ];

    /**
     * Set up an admin tab page: the admin node, the capability, the page URL.
     *
     * @param string $tab
     * @param array $params URL parameters of this page
     */
    public static function setup(string $tab, array $params = []): void {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/adminlib.php');
        [$script, $capability] = self::TABS[$tab];
        admin_externalpage_setup('local_nit_tutors', '', $params, new \moodle_url($script));
        require_capability($capability, \context_system::instance());
        $PAGE->set_url(new \moodle_url($script, $params));
    }

    /**
     * The page heading and the tab row.
     *
     * @param string $current
     * @return string
     */
    public static function render(string $current): string {
        global $OUTPUT;
        $context = \context_system::instance();
        $tabs = [];
        foreach (self::TABS as $tab => [$script, $capability]) {
            if (has_capability($capability, $context)) {
                $tabs[] = new \tabobject($tab, new \moodle_url($script), get_string('tab_' . $tab, 'local_nit_tutors'));
            }
        }
        return $OUTPUT->heading(get_string('admincategory', 'local_nit_tutors')) . $OUTPUT->tabtree($tabs, $current);
    }
}
