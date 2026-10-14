<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Hook callbacks.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Bind the model client (tests replace it with \core\di::set()).
        'hook' => \core\hook\di_configuration::class,
        'callback' => [\local_nit_assistant\local\hook_callbacks::class, 'di_configuration'],
    ],
    [
        // The chat button on the site's pages.
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_nit_assistant\local\launcher::class, 'before_footer'],
    ],
];
