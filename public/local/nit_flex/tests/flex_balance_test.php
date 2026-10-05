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

namespace local_nit_flex;

use local_nit_flex\api\flex;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;

/**
 * Tests for the Flex balance engine and purchase rules.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_flex\service\flex_service
 * @covers     \local_nit_flex\service\purchase_service
 */
final class flex_balance_test extends \advanced_testcase {
    /**
     * A package purchase opens the balance; reserve/consume/return move it correctly.
     *
     * @return void
     */
    public function test_reserve_consume_return_cycle(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $packageid = packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        $purchase = purchase::fulfil((int) $student->id, $packageid);
        $this->assertSame(10, $purchase['remaining_flex']);
        $this->assertSame(10000, flex::value_for_purchase($purchase['id']));

        // Reserve one for a lesson.
        $pid = flex::reserve((int) $student->id, 101, (int) $student->id);
        $this->assertSame($purchase['id'], $pid);
        $active = purchase::active((int) $student->id);
        $this->assertSame(9, $active['remaining_flex']);
        $this->assertSame(1, $active['reserved_flex']);

        // Consume it (lesson completed).
        flex::consume((int) $student->id, $pid, 101, (int) $student->id);
        $active = purchase::active((int) $student->id);
        $this->assertSame(9, $active['remaining_flex']);
        $this->assertSame(0, $active['reserved_flex']);
        $this->assertSame(1, $active['consumed_flex']);

        // Reserve + return (early cancel).
        flex::reserve((int) $student->id, 102, (int) $student->id);
        flex::return_flex((int) $student->id, $pid, 102, (int) $student->id);
        $active = purchase::active((int) $student->id);
        $this->assertSame(9, $active['remaining_flex']);
        $this->assertSame(0, $active['reserved_flex']);
    }

    /**
     * A student may hold only one active package at a time.
     *
     * @return void
     */
    public function test_one_active_package_rule(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $packageid = packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        purchase::fulfil((int) $student->id, $packageid);
        $this->expectException(\local_nit_flex\exception\flex_exception::class);
        purchase::fulfil((int) $student->id, $packageid);
    }

    /**
     * Reserving with no active package fails.
     *
     * @return void
     */
    public function test_reserve_without_package_fails(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->expectException(\local_nit_flex\exception\flex_exception::class);
        flex::reserve((int) $student->id, 1, (int) $student->id);
    }

    /**
     * An unused package can be deleted; a used one cannot.
     *
     * @return void
     */
    public function test_delete_unused_package(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $unused = packages::create((object) [
            'name' => 'Unused', 'flex_count' => 5, 'price_minor' => 50000, 'expiration_days' => 0,
        ]);
        packages::delete_unused($unused);
        $this->assertEmpty(packages::all());

        $used = packages::create((object) [
            'name' => 'Used', 'flex_count' => 5, 'price_minor' => 50000, 'expiration_days' => 0,
        ]);
        purchase::fulfil((int) $student->id, $used);
        $this->expectException(\local_nit_flex\exception\flex_exception::class);
        packages::delete_unused($used);
    }
}
