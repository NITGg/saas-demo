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

use local_nit_core\api\flags;
use local_nit_core\flag\registry;

/**
 * Unit tests for the feature-flag read facade.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\api\flags
 */
final class flags_api_test extends \advanced_testcase {
    /**
     * Reset the memoised registry between tests.
     */
    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * A flag honours its declared default until overridden.
     */
    public function test_default_and_override(): void {
        $this->resetAfterTest();
        $this->assertFalse(flags::enabled('ui_compact_mode'));
        set_config('flag_ui_compact_mode', 1, 'local_nit_core');
        $this->assertTrue(flags::enabled('ui_compact_mode'));
    }

    /**
     * An unknown flag reads false and reports a debugging message.
     */
    public function test_unknown_flag(): void {
        $this->assertFalse(flags::enabled('does_not_exist'));
        $this->assertDebuggingCalled();
    }

    /**
     * reason() distinguishes default from explicit states.
     */
    public function test_reason(): void {
        $this->resetAfterTest();
        $this->assertSame('default', flags::reason('ui_compact_mode'));
        set_config('flag_ui_compact_mode', 1, 'local_nit_core');
        $this->assertSame('enabled', flags::reason('ui_compact_mode'));
        set_config('flag_ui_compact_mode', 0, 'local_nit_core');
        $this->assertSame('disabled', flags::reason('ui_compact_mode'));
        $this->assertSame('unknown', flags::reason('does_not_exist'));
    }
}
