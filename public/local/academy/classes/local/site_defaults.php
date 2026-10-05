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
 * - Cairo as the site timezone. A fresh install picks the server's (UTC or
 *   Europe/London), so lesson times showed 2–3 hours off and "Start lesson"
 *   said it was too early. Users keep "99" (= the site timezone).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_defaults {

    /** The academy's timezone (Egypt). */
    const TIMEZONE = 'Africa/Cairo';

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

        set_config('timezone', self::TIMEZONE);
    }

    /**
     * Turn on Moodle's email self-registration, so a new student confirms their
     * email before signing in (registration::confirmation_required()). Core's own
     * sign-up form, which this opens, is sent to /local/academy/register.php
     * (registration::after_config). Run once: an admin may turn it off again
     * (Site administration → Plugins → Authentication → Self registration).
     */
    public static function email_confirmation(): void {
        \core\plugininfo\auth::enable_plugin('email', 1);
        set_config('registerauth', 'email');
    }

    /**
     * Install the Arabic language pack when missing (it lives in moodledata, not
     * in the code). Without it the navbar AR/EN menu is hidden and ?lang=ar is
     * ignored. Needs the server to reach download.moodle.org; a failure is only
     * reported — install it from Site administration › Language › Language packs.
     *
     * @return bool whether Arabic is installed afterwards
     */
    public static function ensure_arabic(): bool {
        if (get_string_manager()->translation_exists('ar', false)) {
            return true;
        }
        try {
            \core_php_time_limit::raise();
            (new \tool_langimport\controller())->install_languagepacks('ar');
            get_string_manager()->reset_caches();
        } catch (\Throwable $ex) {
            mtrace('local_academy: could not install the Arabic language pack: ' . $ex->getMessage());
        }
        return get_string_manager()->translation_exists('ar', false);
    }
}
