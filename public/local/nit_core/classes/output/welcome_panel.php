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

namespace local_nit_core\output;

use renderer_base;

/**
 * Reference view-model: a dashboard welcome panel.
 *
 * Demonstrates the rendering seam end-to-end. The SDK assembles the greeting and
 * message (data); the theme renders it (presentation).
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
class welcome_panel extends view_model {
    /**
     * The SDK-owned template id (themes may override the template).
     *
     * @return string
     */
    public function template_name(): string {
        return 'local_nit_core/output/welcome_panel';
    }

    /**
     * Assemble the welcome-panel data context.
     *
     * @param renderer_base $output the active renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        return [
            'greeting' => get_string('welcome_greeting', 'local_nit_core', $USER->firstname),
            'message'  => get_string('welcome_message', 'local_nit_core'),
            'ctas'     => [
                [
                    'label' => get_string('welcome_cta', 'local_nit_core'),
                    'url'   => (new \moodle_url('/my/courses.php'))->out(false),
                ],
            ],
        ];
    }
}
