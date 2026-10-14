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

namespace local_nit_finance;

use local_nit_finance\exception\finance_exception;
use local_nit_finance\local\access;
use local_nit_finance\local\catalog;
use local_nit_finance\local\purchases;
use local_nit_finance\local\wallet_checkout;
use local_nit_finance\local\wallets;

/**
 * Paying for a whole course and a subscription plan from the student wallet.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_finance\local\wallet_checkout
 * @covers     \local_nit_subscriptions\subscription_purchase_manager
 */
final class wallet_checkout_test extends \advanced_testcase {

    /** @var \stdClass sold at 200 EGP */
    private $course;
    /** @var \stdClass */
    private $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->set_course_price((int) $this->course->id, 200, 'EGP');
    }

    /**
     * Give a course its online price (local_payments).
     *
     * @param int $courseid
     * @param float $price
     * @param string $currency
     */
    private function set_course_price(int $courseid, float $price, string $currency): void {
        global $DB;
        $DB->insert_record('local_payments_course_prices', (object) ['courseid' => $courseid, 'country' => '*',
            'currency' => $currency, 'price' => $price, 'is_default' => 1, 'is_active' => 1, 'priority' => 0,
            'created_by' => 2, 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * A student with money in the wallet.
     *
     * @param int $minor
     * @return int user id
     */
    private function student(int $minor): int {
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        if ($minor > 0) {
            wallets::move(wallets::STUDENT, $userid, $minor, wallets::KIND_TOPUP);
        }
        return $userid;
    }

    /**
     * An active subscription plan covering the course.
     *
     * @param float $price
     * @return int plan id
     */
    private function plan(float $price): int {
        global $DB;
        $id = (int) $DB->insert_record('nit_subscription', (object) ['name' => 'Gold', 'price' => $price,
            'duration_days' => 30, 'status' => 'active', 'b2b_enabled' => 0, 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 2]);
        $DB->insert_record('nit_course_access', (object) ['subscriptionid' => $id,
            'courseid' => $this->course->id, 'timecreated' => time(), 'usermodified' => 2]);
        return $id;
    }

    public function test_course_purchase_charges_wallet_splits_and_enrols(): void {
        global $DB;
        $student = $this->student(25000);

        $purchase = wallet_checkout::buy_course($student, (int) $this->course->id);

        $this->assertSame(5000, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(8000, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertSame(12000, wallets::balance(wallets::PLATFORM));
        $this->assertSame(catalog::COURSE, $purchase->itemtype);
        $this->assertSame(purchases::METHOD_WALLET, $purchase->method);
        $this->assertSame(20000, (int) $purchase->amount_minor);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $student, '', true));
        $this->assertTrue(access::owns_course($student, (int) $this->course->id));
        $this->assertTrue($DB->record_exists('nit_earning',
            ['purchaseid' => $purchase->id, 'teacherid' => $this->teacher->id, 'teacher_amount_minor' => 8000]));
    }

    public function test_course_purchase_without_enough_balance_changes_nothing(): void {
        global $DB;
        $student = $this->student(19999);
        try {
            wallet_checkout::buy_course($student, (int) $this->course->id);
            $this->fail('Expected err_insufficientwallet');
        } catch (finance_exception $e) {
            $this->assertSame('err_insufficientwallet', $e->errorcode);
        }
        $this->assertSame(19999, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(0, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertSame(0, wallets::balance(wallets::PLATFORM));
        $this->assertFalse($DB->record_exists('nit_purchase', ['userid' => $student]));
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $student));
    }

    public function test_course_cannot_be_bought_twice(): void {
        $student = $this->student(50000);
        wallet_checkout::buy_course($student, (int) $this->course->id);
        try {
            wallet_checkout::buy_course($student, (int) $this->course->id);
            $this->fail('Expected err_alreadyowned');
        } catch (finance_exception $e) {
            $this->assertSame('err_alreadyowned', $e->errorcode);
        }
        $this->assertSame(30000, wallets::balance(wallets::STUDENT, $student));
    }

    public function test_course_can_be_bought_again_after_admin_unenrols(): void {
        global $DB;
        $student = $this->student(50000);
        $first = wallet_checkout::buy_course($student, (int) $this->course->id);
        $this->setAdminUser();
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $student);

        $this->assertSame('revoked', $DB->get_field('nit_purchase', 'status', ['id' => $first->id]));
        $this->assertFalse(wallet_checkout::has_course($student, (int) $this->course->id));

        $again = wallet_checkout::buy_course($student, (int) $this->course->id);
        $this->assertSame((int) $first->id, (int) $again->id);
        $this->assertSame('active', $DB->get_field('nit_purchase', 'status', ['id' => $first->id]));
        $this->assertSame(10000, wallets::balance(wallets::STUDENT, $student));
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $student, '', true));
    }

    public function test_subscription_cancel_keeps_course_bought_from_wallet(): void {
        global $DB;
        $student = $this->student(50000);
        wallet_checkout::buy_course($student, (int) $this->course->id);
        $sub = wallet_checkout::buy_subscription($student, $this->plan(100));

        \local_nit_subscriptions\subscription_purchase_manager::unsubscribe((int) $sub->id);

        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $student, '', true));
        $this->assertTrue($DB->record_exists('nit_purchase',
            ['userid' => $student, 'itemid' => $this->course->id, 'status' => 'active']));
    }

    public function test_subscription_cancel_keeps_course_paid_online(): void {
        global $DB;
        $student = $this->student(10000);
        $DB->insert_record('local_payments_transactions', (object) ['userid' => $student,
            'courseid' => $this->course->id, 'provider_id' => 0, 'order_id' => 'S1', 'idempotency_key' => 's1',
            'amount' => 200, 'currency' => 'EGP', 'status' => 'completed', 'country' => 'EG',
            'metadata' => json_encode(['item_type' => 'course']), 'expires_at' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        \local_payments\enrollment_handler::enrol_user($student, (int) $this->course->id);
        $sub = wallet_checkout::buy_subscription($student, $this->plan(100));

        \local_nit_subscriptions\subscription_purchase_manager::unsubscribe((int) $sub->id);

        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $student, '', true));
    }

    public function test_student_with_single_lessons_can_buy_the_whole_course(): void {
        $cm = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        catalog::set_price((int) $cm->cmid, (int) $this->course->id, 3000);
        $student = $this->student(23000);
        purchases::buy_with_wallet($student, (int) $cm->cmid);
        $this->assertTrue(access::course_state($student, (int) $this->course->id)['lessononly']);
        $this->assertFalse(wallet_checkout::has_course($student, (int) $this->course->id));

        wallet_checkout::buy_course($student, (int) $this->course->id);

        $this->assertSame(0, wallets::balance(wallets::STUDENT, $student));
        $this->assertTrue(wallet_checkout::has_course($student, (int) $this->course->id));
        $this->assertFalse(access::course_state($student, (int) $this->course->id)['lessononly']);
    }

    public function test_free_or_foreign_currency_course_is_not_payable(): void {
        $student = $this->student(100000);
        $free = $this->getDataGenerator()->create_course();
        $dollars = $this->getDataGenerator()->create_course();
        $this->set_course_price((int) $dollars->id, 20, 'USD');

        $this->assertTrue(wallet_checkout::course_payable($student, (int) $this->course->id));
        $this->assertFalse(wallet_checkout::course_payable($student, (int) $free->id));
        $this->assertFalse(wallet_checkout::course_payable($student, (int) $dollars->id));
        try {
            wallet_checkout::buy_course($student, (int) $dollars->id);
            $this->fail('Expected err_walletcurrency');
        } catch (finance_exception $e) {
            $this->assertSame('err_walletcurrency', $e->errorcode);
        }
        $this->assertSame(100000, wallets::balance(wallets::STUDENT, $student));
    }

    public function test_coupon_lowers_the_wallet_charge_and_is_used_once(): void {
        global $DB;
        $couponid = $DB->insert_record('nit_coupon', (object) ['code' => 'HALF', 'discount_type' => 'percent',
            'discount_value' => 50, 'usage_type' => 'multiple', 'usage_limit' => 0, 'startdate' => 0, 'enddate' => 0,
            'status' => 'active', 'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 2]);
        $DB->insert_record('nit_coupon_item', (object) ['couponid' => $couponid, 'item_type' => 'course', 'item_id' => 0]);
        $student = $this->student(10000);

        $this->assertSame(10000, wallet_checkout::course_quote($student, (int) $this->course->id, 'HALF')['final_minor']);
        $purchase = wallet_checkout::buy_course($student, (int) $this->course->id, 'HALF');

        $this->assertSame(0, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(10000, (int) $purchase->amount_minor);
        $this->assertSame(1, $DB->count_records('nit_coupon_usage', ['couponid' => $couponid, 'userid' => $student]));
    }

    public function test_unknown_coupon_stops_the_payment(): void {
        $student = $this->student(30000);
        try {
            wallet_checkout::buy_course($student, (int) $this->course->id, 'NOPE');
            $this->fail('Expected a coupon error');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_couponnotfound', $e->errorcode);
        }
        $this->assertSame(30000, wallets::balance(wallets::STUDENT, $student));
    }

    public function test_subscription_purchase_charges_wallet_and_covers_courses(): void {
        global $DB;
        $planid = $this->plan(300);
        $student = $this->student(50000);

        $purchase = wallet_checkout::buy_subscription($student, $planid);

        $this->assertSame(20000, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(30000, wallets::balance(wallets::PLATFORM));
        $row = $DB->get_record('nit_sub_purchase', ['id' => $purchase->id]);
        $this->assertSame('wallet', $row->source);
        $this->assertSame('active', $row->status);
        $this->assertEquals(300, (float) $row->price_paid);
        $this->assertTrue(\local_payments\price_resolver::is_covered_by_active_subscription(
            (int) $this->course->id, $student));
        $history = \local_nit_subscriptions\subscription_purchase_manager::get_subscription_payment_history($student);
        $this->assertCount(1, $history);
        $this->assertSame('wallet', $history[0]['payment_method']);
        $this->assertSame('completed', $history[0]['status']);
    }

    public function test_second_subscription_is_refused_and_not_charged(): void {
        $planid = $this->plan(100);
        $student = $this->student(50000);
        wallet_checkout::buy_subscription($student, $planid);
        try {
            wallet_checkout::buy_subscription($student, $planid);
            $this->fail('Expected err_subactive');
        } catch (finance_exception $e) {
            $this->assertSame('err_subactive', $e->errorcode);
        }
        $this->assertSame(40000, wallets::balance(wallets::STUDENT, $student));
    }

    public function test_subscription_without_enough_balance_creates_nothing(): void {
        global $DB;
        $planid = $this->plan(300);
        $student = $this->student(29999);
        try {
            wallet_checkout::buy_subscription($student, $planid);
            $this->fail('Expected err_insufficientwallet');
        } catch (finance_exception $e) {
            $this->assertSame('err_insufficientwallet', $e->errorcode);
        }
        $this->assertSame(29999, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(0, wallets::balance(wallets::PLATFORM));
        $this->assertFalse($DB->record_exists('nit_sub_purchase', ['userid' => $student]));
    }
}
