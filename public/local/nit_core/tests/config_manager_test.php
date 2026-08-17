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

use local_nit_core\manager\config_manager;

/**
 * Unit tests for the typed config manager.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\manager\config_manager
 */
final class config_manager_test extends \advanced_testcase {
    /**
     * Typed getters return defaults when unset and correct types when set.
     */
    public function test_typed_read_write(): void {
        $this->resetAfterTest();
        $config = new config_manager();

        // Defaults when unset.
        $this->assertSame('fallback', $config->get_string('missing', 'fallback'));
        $this->assertNull($config->get_string('missing'));
        $this->assertSame(7, $config->get_int('missing', 7));
        $this->assertTrue($config->get_bool('missing', true));

        // String.
        $config->set('greeting', 'hello');
        $this->assertSame('hello', $config->get_string('greeting'));

        // Int.
        $config->set('count', 42);
        $this->assertSame(42, $config->get_int('count'));

        // Bool.
        $config->set('flag', true);
        $this->assertTrue($config->get_bool('flag'));
        $config->set('flag', false);
        $this->assertFalse($config->get_bool('flag'));

        // Null unsets.
        $config->set('greeting', null);
        $this->assertNull($config->get_string('greeting'));
    }

    /**
     * for_plugin() scopes reads/writes to a different component.
     */
    public function test_component_scoping(): void {
        $this->resetAfterTest();
        $core = new config_manager();
        $other = $core->for_plugin('local_someother');
        $other->set('shared', 'value');

        $this->assertSame('value', $other->get_string('shared'));
        $this->assertNull($core->get_string('shared'));
    }
}
