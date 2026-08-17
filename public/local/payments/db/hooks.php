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
 * Hook callbacks for local_payments.
 *
 * @package    local_payments
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // Payment gate: redirect unenrolled users from a paid course to the buy page.
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_payments\hook_callbacks::class, 'before_http_headers'],
    ],
    // Load the catalog course-card price badge script into the page head.
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_payments\local\hooks\output::class, 'before_standard_head_html_generation'],
    ],
];
