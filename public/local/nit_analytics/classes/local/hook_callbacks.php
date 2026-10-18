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

namespace local_nit_analytics\local;

use local_nit_analytics\tracker;

/**
 * Output hooks: capture UTM on every page, print the tracking snippets where configured.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {

    /**
     * Capture first-touch UTM, then add the tracker snippets (and a pending sign-up event) to <head>.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(
            \core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE, $SESSION, $USER;
        if (during_initial_install()) {
            return;
        }
        // Attribution is kept even when no tracker id is configured, so sign-up sources are known.
        if (isset($SESSION) && (!isloggedin() || isguestuser())) {
            tracker::capture($_GET, $SESSION);
        }
        if (!tracker::should_track((string) $PAGE->pagelayout)) {
            return;
        }
        $signup = isloggedin() && tracker::take_pending_signup((int) $USER->id);
        $hook->add_html(tracker::head_html($signup));
    }

    /**
     * The GTM <noscript> fallback right after <body>.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function before_standard_top_of_body_html_generation(
            \core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (during_initial_install() || !tracker::should_track((string) $PAGE->pagelayout)) {
            return;
        }
        $hook->add_html(tracker::body_html());
    }
}
