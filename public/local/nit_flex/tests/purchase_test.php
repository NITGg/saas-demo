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

use local_nit_finance\local\wallets;
use local_nit_flex\api\flex;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_flex\service\purchase_service;

/**
 * Buying packages with the wallet or online, admin assign/unassign, and expiry.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_flex\service\purchase_service
 */
final class purchase_test extends \advanced_testcase {

    /**
     * A 10-Flex package for 1000 EGP valid 30 days.
     *
     * @return int
     */
    private function package(): int {
        return packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 30,
        ]);
    }

    /**
     * The wallet pays the package; not enough credit leaves nothing behind.
     *
     * @return void
     */
    public function test_buy_with_wallet(): void {
        global $DB;
        $this->resetAfterTest();
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $pkg = $this->package();

        try {
            purchase::buy_with_wallet($student, $pkg);
            $this->fail('empty wallet must not buy');
        } catch (\local_nit_finance\exception\finance_exception $e) {
            $this->assertSame('err_insufficientwallet', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('nit_package_purchase', ['userid' => $student]));

        wallets::move(wallets::STUDENT, $student, 150000, wallets::KIND_TOPUP);
        $p = purchase::buy_with_wallet($student, $pkg);
        $this->assertSame(50000, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(10, $p['remaining_flex']);
        $this->assertSame(100000, $p['price_paid_minor']);
        $this->assertSame(10000, flex::value_for_purchase($p['id']));
        $this->assertTrue($DB->record_exists('nit_payment', ['purchaseid' => $p['id'], 'method' => 'wallet']));

        $this->expectException(\local_nit_flex\exception\flex_exception::class);
        purchase::buy_with_wallet($student, $pkg);
    }

    /**
     * An online order grants one package at the charged price, once; a second paid order while
     * a package is active goes to the wallet.
     *
     * @return void
     */
    public function test_gateway_fulfilment_is_idempotent(): void {
        $this->resetAfterTest();
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $pkg = $this->package();

        $p = purchase::fulfil_from_gateway($student, $pkg, 80000, 'ORDER-1', 501);
        $this->assertSame(80000, $p['price_paid_minor']);
        $this->assertSame(8000, flex::value_for_purchase($p['id']));
        $this->assertNull(purchase::fulfil_from_gateway($student, $pkg, 80000, 'ORDER-1', 501));

        $this->assertNull(purchase::fulfil_from_gateway($student, $pkg, 50000, 'ORDER-2', 502));
        $this->assertSame(50000, wallets::balance(wallets::STUDENT, $student));
        $this->assertCount(1, purchase::my_packages($student));
    }

    /**
     * Admin assign can charge the wallet; unassign refunds unused Flex to the wallet.
     *
     * @return void
     */
    public function test_assign_and_unassign_with_refund(): void {
        $this->resetAfterTest();
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $pkg = $this->package();
        wallets::move(wallets::STUDENT, $student, 100000, wallets::KIND_TOPUP);

        $p = purchase::assign(2, $student, $pkg, 0, 'wallet', 'ref');
        $this->assertSame(0, wallets::balance(wallets::STUDENT, $student));

        flex::reserve($student, 7, $student);
        flex::consume($student, $p['id'], 7, $student);
        $refunded = purchase::unassign($p['id'], true, 2);
        $this->assertSame(90000, $refunded);
        $this->assertSame(90000, wallets::balance(wallets::STUDENT, $student));
        $this->assertNull(purchase::active($student));
    }

    /**
     * An ended package expires its unused Flex into platform income; a Flex returned later to it
     * goes to the platform too.
     *
     * @return void
     */
    public function test_expiry(): void {
        global $DB;
        $this->resetAfterTest();
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $p = purchase::fulfil($student, $this->package());
        flex::reserve($student, 9, $student);
        $DB->set_field('nit_package_purchase', 'expires_at', time() - 1, ['id' => $p['id']]);

        $this->assertSame(1, (new purchase_service())->expire_due(time()));
        $row = $DB->get_record('nit_package_purchase', ['id' => $p['id']]);
        $this->assertSame('expired', $row->status);
        $this->assertSame(0, (int) $row->remaining_flex);
        $this->assertSame(1, (int) $row->reserved_flex);
        $this->assertSame(90000, wallets::balance(wallets::PLATFORM));

        // The reserved lesson is cancelled in time: its Flex cannot be used any more.
        flex::return_flex($student, $p['id'], 9, $student);
        $this->assertSame(100000, wallets::balance(wallets::PLATFORM));
        $this->assertSame(0, (int) $DB->get_field('nit_package_purchase', 'remaining_flex', ['id' => $p['id']]));
    }

    /**
     * Reminders pick packages ending soon that still hold Flex, once.
     *
     * @return void
     */
    public function test_due_for_reminder(): void {
        global $DB;
        $this->resetAfterTest();
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $p = purchase::fulfil($student, $this->package());
        $service = new purchase_service();
        $this->assertCount(0, $service->due_for_reminder(time(), 3));
        $DB->set_field('nit_package_purchase', 'expires_at', time() + 2 * DAYSECS, ['id' => $p['id']]);
        $this->assertCount(1, $service->due_for_reminder(time(), 3));
        $DB->set_field('nit_package_purchase', 'expiry_notified', time(), ['id' => $p['id']]);
        $this->assertCount(0, $service->due_for_reminder(time(), 3));
    }
}
