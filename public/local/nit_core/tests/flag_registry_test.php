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

use local_nit_core\flag\flag;
use local_nit_core\flag\registry;

/**
 * Unit tests for the feature-flag registry.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\flag\registry
 */
final class flag_registry_test extends \advanced_testcase {
    /**
     * Reset the memoised registry between tests.
     */
    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * Discovery finds the SDK's own provider flags.
     */
    public function test_discovers_core_flags(): void {
        $all = registry::all();
        $this->assertArrayHasKey('ui_compact_mode', $all);
        $this->assertArrayHasKey('experimental_preview', $all);
        $this->assertInstanceOf(flag::class, $all['ui_compact_mode']);
        $this->assertSame('ui', $all['ui_compact_mode']->domain());
    }

    /**
     * Flags group by their domain.
     */
    public function test_by_domain(): void {
        $grouped = registry::by_domain();
        $this->assertArrayHasKey('ui', $grouped);
        $this->assertArrayHasKey('experimental', $grouped);
        $this->assertContainsOnlyInstancesOf(flag::class, $grouped['ui']);
    }

    /**
     * Unknown keys resolve to null.
     */
    public function test_get_unknown(): void {
        $this->assertNull(registry::get('does_not_exist'));
    }
}
