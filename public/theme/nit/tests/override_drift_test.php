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

namespace theme_nit;

/**
 * Override-drift guard: keeps the P1 core-template override budget honest.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_nit\output\core_renderer
 */
final class override_drift_test extends \advanced_testcase {
    /**
     * Any overridden core template must exist in core and stay within budget.
     */
    public function test_core_template_overrides_are_budgeted(): void {
        global $CFG;

        $dir = $CFG->dirroot . '/theme/nit/templates/core';
        $overrides = is_dir($dir) ? (glob($dir . '/*.mustache') ?: []) : [];

        // Each core-template override must have a matching core template to diff
        // against; a dangling override is dead weight to be removed.
        foreach ($overrides as $file) {
            $name = basename($file);
            $this->assertFileExists(
                $CFG->dirroot . '/lib/templates/' . $name,
                "Overridden core template {$name} has no matching core template"
            );
        }

        // The P1 budget is six screens; exceeding it needs an architecture review.
        $this->assertLessThanOrEqual(
            6,
            count($overrides),
            'Core template override budget exceeded — review docs/override-budget.md'
        );
    }
}
