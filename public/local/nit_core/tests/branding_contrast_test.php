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

namespace local_nit_core;

use local_nit_core\branding\contrast;

/**
 * Unit tests for the WCAG contrast helper.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\branding\contrast
 */
final class branding_contrast_test extends \advanced_testcase {
    /**
     * Extremes pick the obvious foreground.
     */
    public function test_safe_foreground_extremes(): void {
        $this->assertSame('#ffffff', contrast::safe_foreground('#000000'));
        $this->assertSame('#171b22', contrast::safe_foreground('#ffffff'));
    }

    /**
     * For every preset primary, the chosen foreground is the higher-contrast one.
     */
    public function test_safe_foreground_is_best_choice(): void {
        $colours = ['#2a50c8', '#0e7c86', '#6d28d9', '#7a1f3d', '#0f3d5c', '#1f3a93', '#f7f8fa'];
        foreach ($colours as $bg) {
            $fg = contrast::safe_foreground($bg);
            $other = $fg === '#ffffff' ? '#171b22' : '#ffffff';
            $this->assertGreaterThanOrEqual(
                contrast::ratio($other, $bg),
                contrast::ratio($fg, $bg),
                "Chosen foreground is not the higher-contrast option for {$bg}"
            );
        }
    }

    /**
     * Ratio matches known WCAG extremes.
     */
    public function test_ratio_extremes(): void {
        $this->assertEqualsWithDelta(21.0, contrast::ratio('#ffffff', '#000000'), 0.2);
        $this->assertEqualsWithDelta(1.0, contrast::ratio('#808080', '#808080'), 0.01);
    }
}
