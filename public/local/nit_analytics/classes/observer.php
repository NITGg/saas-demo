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

namespace local_nit_analytics;

/**
 * Event observers of local_nit_analytics.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {

    /**
     * A visitor signed up (self sign-up, Google, …): keep where they came from. Accounts an admin
     * or a CLI creates for someone else are skipped — that is not a marketing sign-up.
     *
     * @param \core\event\user_created $event
     * @return void
     */
    public static function user_created(\core\event\user_created $event): void {
        global $SESSION;
        $cli = CLI_SCRIPT && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST);
        if ($cli || (isloggedin() && !isguestuser())) {
            return;
        }
        try {
            tracker::record_signup((int) $event->objectid, $SESSION ?? new \stdClass());
        } catch (\Throwable $e) {
            debugging('local_nit_analytics: could not record sign-up: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
