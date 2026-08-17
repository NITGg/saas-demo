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

namespace local_payments\local\hooks;

defined('MOODLE_INTERNAL') || die();

/**
 * Output hook callbacks for local_payments.
 *
 * @package    local_payments
 */
class output {

    /**
     * Load the course-card price badge script into the page head. The script is tiny and
     * self-guards (does nothing on pages without course links), so it is safe to add globally.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(
            \core\hook\output\before_standard_head_html_generation $hook): void {
        global $CFG, $PAGE;

        if (during_initial_install()) {
            return;
        }
        // Skip layouts where catalog cards never appear.
        $skip = ['maintenance', 'print', 'embedded', 'popup', 'redirect'];
        if (isset($PAGE) && in_array($PAGE->pagelayout, $skip, true)) {
            return;
        }

        $version = (string) get_config('local_payments', 'version');
        $src = $CFG->wwwroot . '/local/payments/js/course_cards.js?v=' . $version;
        $hook->add_html('<script defer src="' . s($src) . '"></script>');
    }
}
