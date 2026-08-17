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

use local_nit_core\contract\config_manager;
use local_nit_core\exception\nit_exception;

/**
 * Unit tests for the DI seam.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\service_manager
 */
final class service_manager_test extends \advanced_testcase {
    /**
     * Reset the static container between tests.
     */
    protected function tearDown(): void {
        service_manager::reset();
        parent::tearDown();
    }

    /**
     * Resolution returns a lazily-built singleton of the right type.
     */
    public function test_get_returns_typed_singleton(): void {
        service_manager::reset();
        $first = service_manager::get(config_manager::class);
        $second = service_manager::get(config_manager::class);
        $this->assertInstanceOf(config_manager::class, $first);
        $this->assertSame($first, $second);
    }

    /**
     * has() reflects resolvable ids.
     */
    public function test_has(): void {
        $this->assertTrue(service_manager::has(config_manager::class));
        $this->assertFalse(service_manager::has('no\\such\\service'));
    }

    /**
     * override() replaces the service with a double.
     */
    public function test_override_replaces_service(): void {
        service_manager::reset();
        $double = $this->createMock(config_manager::class);
        service_manager::override(config_manager::class, $double);
        $this->assertSame($double, service_manager::get(config_manager::class));
    }

    /**
     * Unknown ids raise a NIT exception.
     */
    public function test_unknown_service_throws(): void {
        service_manager::reset();
        $this->expectException(nit_exception::class);
        service_manager::get('does\\not\\Exist');
    }
}
