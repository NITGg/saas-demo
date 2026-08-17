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

use local_nit_core\manager\cache_manager;

/**
 * Unit tests for the cache manager facade.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\manager\cache_manager
 */
final class cache_manager_test extends \advanced_testcase {
    /**
     * Basic get/set/delete round-trip with a miss returning null.
     */
    public function test_get_set_delete(): void {
        $this->resetAfterTest();
        $cache = new cache_manager();

        $this->assertNull($cache->get('alpha', 'k1'));

        $cache->set('alpha', 'k1', 'value');
        $this->assertSame('value', $cache->get('alpha', 'k1'));

        $cache->set('alpha', 'k2', ['nested' => 1]);
        $this->assertSame(['nested' => 1], $cache->get('alpha', 'k2'));

        $cache->delete('alpha', 'k1');
        $this->assertNull($cache->get('alpha', 'k1'));
        $this->assertSame(['nested' => 1], $cache->get('alpha', 'k2'));
    }

    /**
     * Areas are isolated and purge_area clears only its own keys.
     */
    public function test_area_isolation_and_purge(): void {
        $this->resetAfterTest();
        $cache = new cache_manager();

        $cache->set('alpha', 'shared', 'a');
        $cache->set('beta', 'shared', 'b');
        $this->assertSame('a', $cache->get('alpha', 'shared'));
        $this->assertSame('b', $cache->get('beta', 'shared'));

        $cache->purge_area('alpha');
        $this->assertNull($cache->get('alpha', 'shared'));
        $this->assertSame('b', $cache->get('beta', 'shared'));
    }
}
