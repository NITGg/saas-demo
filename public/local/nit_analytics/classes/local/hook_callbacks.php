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

use local_nit_analytics\conversions;
use local_nit_analytics\tracker;

/**
 * Output hooks: capture UTM on every page; print trackers only with consent; ask for consent.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {

    /**
     * Capture first-touch UTM, then — with consent — add the tracker snippets and any pending
     * sign-up / purchase events to <head>.
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
        // Attribution is kept even without trackers or consent (server-side, no cookie), so the
        // funnel report still knows sign-up sources.
        if (isset($SESSION) && (!isloggedin() || isguestuser())) {
            tracker::capture($_GET, $_COOKIE, $SESSION);
        }
        if (!tracker::should_track((string) $PAGE->pagelayout, $_COOKIE)) {
            return;
        }
        $real = isloggedin() && !isguestuser();
        $signup = $real && tracker::take_pending_signup((int) $USER->id);
        $purchases = $real ? conversions::take_pending((int) $USER->id) : [];
        $hook->add_html(tracker::head_html($signup, $purchases,
            (array) ($SESSION->local_nit_analytics_utm ?? []), $_COOKIE));
    }

    /**
     * The GTM <noscript> fallback right after <body> (only with consent).
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function before_standard_top_of_body_html_generation(
            \core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (during_initial_install() || !tracker::should_track((string) $PAGE->pagelayout, $_COOKIE)) {
            return;
        }
        $hook->add_html(tracker::body_html());
    }

    /**
     * The consent banner, until the visitor accepts or declines.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook
     * @return void
     */
    public static function before_standard_footer_html_generation(
            \core\hook\output\before_standard_footer_html_generation $hook): void {
        global $PAGE;
        if (during_initial_install() || !tracker::should_ask_consent((string) $PAGE->pagelayout, $_COOKIE)) {
            return;
        }
        $hook->add_html(tracker::consent_banner_html());
    }
}
