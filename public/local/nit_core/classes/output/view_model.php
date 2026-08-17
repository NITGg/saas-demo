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

use renderable;
use renderer_base;
use templatable;

/**
 * Base view-model: the "what to show" half of the rendering seam.
 *
 * A view-model assembles a surface's data and declares the template that renders
 * it. It contains NO presentation. The theme owns the template (and may override
 * the SDK default) — so data (SDK) and presentation (theme) stay separated.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
abstract class view_model implements renderable, templatable {
    /**
     * Assemble the data context for the template.
     *
     * @param renderer_base $output the active renderer
     * @return array data-only context (no HTML, no logic downstream)
     */
    abstract public function export_for_template(renderer_base $output): array;

    /**
     * The SDK-owned template id for this view-model.
     *
     * Themes override the template by mirroring this path under their
     * templates/ directory; the data contract stays the same.
     *
     * @return string a Mustache template identifier
     */
    abstract public function template_name(): string;
}
