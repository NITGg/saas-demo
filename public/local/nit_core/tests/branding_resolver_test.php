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

use local_nit_core\api\branding;
use local_nit_core\branding\resolver;

/**
 * Unit tests for the branding resolver.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\branding\resolver
 * @covers     \local_nit_core\api\branding
 */
final class branding_resolver_test extends \advanced_testcase {
    /**
     * The default preset resolves to the NIT-blue education brand.
     */
    public function test_default_preset(): void {
        $this->resetAfterTest();
        $tokens = resolver::resolve();
        $this->assertSame('education', $tokens['preset']);
        $this->assertSame('#2a50c8', $tokens['primary']);
        $this->assertNotEmpty($tokens['font']);
        $this->assertNotEmpty($tokens['onprimary']);
    }

    /**
     * Selecting a preset changes the resolved primary.
     */
    public function test_preset_selection(): void {
        $this->resetAfterTest();
        set_config('brand_preset', 'medical', 'local_nit_core');
        $tokens = resolver::resolve();
        $this->assertSame('medical', $tokens['preset']);
        $this->assertSame('#0e7c86', $tokens['primary']);
    }

    /**
     * A primary override wins over the preset and gets a safe foreground.
     */
    public function test_primary_override(): void {
        $this->resetAfterTest();
        set_config('brand_preset', 'education', 'local_nit_core');
        set_config('brand_primary', '#c0392b', 'local_nit_core');
        $tokens = resolver::resolve();
        $this->assertSame('#c0392b', $tokens['primary']);
        $this->assertSame('#ffffff', $tokens['onprimary']);
    }

    /**
     * A colour without a leading hash is normalised.
     */
    public function test_hex_normalisation(): void {
        $this->resetAfterTest();
        set_config('brand_primary', '0e7c86', 'local_nit_core');
        $this->assertSame('#0e7c86', resolver::resolve()['primary']);
    }

    /**
     * The facade exposes the active preset and tokens.
     */
    public function test_facade(): void {
        $this->resetAfterTest();
        set_config('brand_preset', 'kids', 'local_nit_core');
        $this->assertSame('kids', branding::active_preset());
        $this->assertSame('#6d28d9', branding::tokens()['primary']);
    }
}
