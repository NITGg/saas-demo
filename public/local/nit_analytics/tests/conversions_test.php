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

namespace local_nit_analytics;

/**
 * Purchase conversions: recording, idempotency, the browser event queue, and the payment hooks.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_analytics\conversions
 */
final class conversions_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A purchase is recorded once per kind + reference; invalid or free ones are refused.
     *
     * @return void
     */
    public function test_record_is_idempotent_and_validated(): void {
        global $DB;
        $uid = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertTrue(conversions::record_purchase($uid, 'flex', 50000, 'egp', '12', true));
        $this->assertFalse(conversions::record_purchase($uid, 'flex', 50000, 'EGP', '12', true), 'same ref twice');
        $this->assertTrue(conversions::record_purchase($uid, 'course', 50000, 'EGP', '12', true), 'other kind, same ref');

        $this->assertFalse(conversions::record_purchase($uid, 'flex', 0, 'EGP', '99', true), 'free');
        $this->assertFalse(conversions::record_purchase($uid, 'gift', 100, 'EGP', '99', true), 'unknown kind');
        $this->assertFalse(conversions::record_purchase($uid, 'flex', 100, 'EURO', '99', true), 'bad currency');
        $this->assertFalse(conversions::record_purchase(0, 'flex', 100, 'EGP', '99', true), 'no buyer');

        $this->assertSame(2, $DB->count_records(conversions::TABLE));
        $this->assertSame('EGP', $DB->get_field(conversions::TABLE, 'currency', ['kind' => 'flex', 'ref' => '12']));
    }

    /**
     * The browser event is queued only when the buyer paid themselves, and is handed out once.
     *
     * @return void
     */
    public function test_browser_event_only_for_buyer_and_once(): void {
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        conversions::record_purchase($uid, 'flex', 50000, 'EGP', '1', false); // Admin assigned.
        $this->assertSame([], conversions::take_pending($uid));

        conversions::record_purchase($uid, 'flex', 50000, 'EGP', '2', true);
        $pending = conversions::take_pending($uid);
        $this->assertSame([['ref' => 'flex-2', 'amount_minor' => 50000, 'currency' => 'EGP']], $pending);
        $this->assertSame([], conversions::take_pending($uid), 'handed out once');
    }

    /**
     * The major-units helper converts to minor units and treats the current user as the buyer.
     *
     * @return void
     */
    public function test_major_helper_uses_current_user(): void {
        global $DB;
        $buyer = $this->getDataGenerator()->create_user();
        $this->setUser($buyer);
        conversions::record_purchase_major((int) $buyer->id, 'subscription', 199.99, 'EGP', '5');
        $this->assertSame(19999, (int) $DB->get_field(conversions::TABLE, 'amount_minor', ['ref' => '5']));
        $this->assertCount(1, conversions::take_pending((int) $buyer->id));

        $this->setAdminUser();
        conversions::record_purchase_major((int) $buyer->id, 'subscription', 50, 'EGP', '6');
        $this->assertSame([], conversions::take_pending((int) $buyer->id), 'admin acting for the buyer: no event');
    }

    /**
     * A course payment completed in local_payments is recorded through the observer.
     *
     * @return void
     */
    public function test_course_payment_event_is_recorded(): void {
        global $DB;
        if (!class_exists('\local_payments\event\payment_completed')) {
            $this->markTestSkipped('local_payments not installed');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        \local_payments\event\payment_completed::create([
            'context' => \context_course::instance($course->id),
            'objectid' => 77,
            'userid' => $user->id,
            'other' => ['courseid' => $course->id, 'amount' => '350.00', 'currency' => 'SAR', 'provider' => 'test'],
        ])->trigger();

        $row = $DB->get_record(conversions::TABLE, ['kind' => 'course', 'ref' => '77']);
        $this->assertNotFalse($row);
        $this->assertSame(35000, (int) $row->amount_minor);
        $this->assertSame('SAR', $row->currency);
        $this->assertSame((int) $user->id, (int) $row->userid);
    }

    /**
     * Buying a lesson / course from the wallet records a conversion (local_nit_finance calls in).
     *
     * @return void
     */
    public function test_wallet_course_purchase_is_recorded(): void {
        global $DB;
        if (!class_exists('\local_nit_finance\local\purchases')) {
            $this->markTestSkipped('local_nit_finance not installed');
        }
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_and_enrol($course, 'editingteacher');
        $page = $gen->create_module('page', ['course' => $course->id]);
        \local_nit_finance\local\catalog::set_price((int) $page->cmid, (int) $course->id, 5000);
        $student = $gen->create_user();
        \local_nit_finance\local\wallets::move(\local_nit_finance\local\wallets::STUDENT, (int) $student->id, 8000,
            \local_nit_finance\local\wallets::KIND_TOPUP);
        $this->setUser($student);

        \local_nit_finance\local\purchases::buy_with_wallet((int) $student->id, (int) $page->cmid);

        $row = $DB->get_record(conversions::TABLE, ['kind' => 'course', 'userid' => $student->id]);
        $this->assertNotFalse($row, 'a wallet purchase should be a conversion');
        $this->assertSame(5000, (int) $row->amount_minor);
        $this->assertCount(1, conversions::take_pending((int) $student->id));
    }

    /**
     * Buying a Flex package records a conversion (local_nit_flex calls in).
     *
     * @return void
     */
    public function test_flex_package_purchase_is_recorded(): void {
        global $DB;
        if (!class_exists('\local_nit_flex\api\packages')) {
            $this->markTestSkipped('local_nit_flex not installed');
        }
        $student = $this->getDataGenerator()->create_user();
        $packageid = \local_nit_flex\api\packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        $this->setUser($student);
        \local_nit_flex\api\purchase::fulfil((int) $student->id, $packageid);

        $row = $DB->get_record(conversions::TABLE, ['kind' => 'flex', 'userid' => $student->id]);
        $this->assertNotFalse($row, 'a Flex purchase should be a conversion');
        $this->assertSame(100000, (int) $row->amount_minor);
        $this->assertCount(1, conversions::take_pending((int) $student->id));
    }
}
