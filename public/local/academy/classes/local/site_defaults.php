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

namespace local_academy\local;

/**
 * Site settings the academy depends on, so a fresh server works like dev.
 *
 * - The multilang2 filter on, for content AND headings, with format_string()
 *   filtering: every {mlang ar}…{mlang}{mlang en}…{mlang} text (governorates,
 *   study systems, course and category names…) depends on it.
 * - The site home page as the home page (the logo / "Home" link), not the
 *   dashboard, and open to visitors who are not logged in.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_defaults {

    /**
     * Apply the settings (safe to run again).
     */
    public static function apply(): void {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');

        if (file_exists($CFG->dirroot . '/filter/multilang2/version.php')) {
            filter_set_global_state('multilang2', TEXTFILTER_ON);
            filter_set_applies_to_strings('multilang2', true);
            set_config('filterall', 1);
        }

        set_config('defaulthomepage', HOMEPAGE_SITE);
        // The home page is public: visitors see it before logging in. index.php
        // also sends visitors to the login page when the dashboard is disabled.
        set_config('forcelogin', 0);
        set_config('enablemyhome', 1);
    }
}
