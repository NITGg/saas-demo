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
use local_nit_finance\local\catalog;
use local_nit_finance\local\purchases;
use local_nit_finance\local\refunds;
use local_nit_finance\local\wallet_checkout;
use local_nit_finance\local\wallets;

/**
 * Refund requests: the student asks within the grace period, an admin approves or
 * rejects; the amount less the fee goes back to the wallet, the teacher's share is
 * taken back, access is removed (TASKS-ROADMAP task 8).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_finance\local\refunds
 */
final class refunds_test extends \advanced_testcase {

    /** @var \stdClass sold at 200 EGP */
    private $course;
    /** @var \stdClass */
    private $teacher;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        set_config('refund_days', 3, 'local_nit_finance');
        set_config('refund_fee_percent', 10, 'local_nit_finance');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $DB->insert_record('local_payments_course_prices', (object) ['courseid' => $this->course->id, 'country' => '*',
            'currency' => 'EGP', 'price' => 200, 'is_default' => 1, 'is_active' => 1, 'priority' => 0,
            'created_by' => 2, 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * A student with money in the wallet.
     *
     * @param int $minor
     * @return int
     */
    private function student(int $minor): int {
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        if ($minor > 0) {
            wallets::move(wallets::STUDENT, $userid, $minor, wallets::KIND_TOPUP);
        }
        return $userid;
    }

    /**
     * The one refundable item of a student.
     *
     * @param int $userid
     * @return array
     */
    private function only_refundable(int $userid): array {
        $items = refunds::refundable($userid);
        $this->assertCount(1, $items);
        return $items[0];
    }

    /**
     * Whether the student is in the course.
     *
     * @param int $userid
     * @return bool
     */
    private function enrolled(int $userid): bool {
        return is_enrolled(\context_course::instance($this->course->id), $userid, '', true);
    }

    public function test_wallet_course_refund_less_fee_takes_the_teacher_share_back_and_unenrols(): void {
        global $DB;
        $student = $this->student(30000);
        $purchase = wallet_checkout::buy_course($student, (int) $this->course->id);
        $this->assertTrue($this->enrolled($student));
        // Sale: 200 → teacher 80, platform 120.
        $this->assertSame(8000, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $platformbefore = wallets::balance(wallets::PLATFORM, 0);

        $item = $this->only_refundable($student);
        $this->assertSame([20000, 2000, 18000], [$item['amount_minor'], $item['fee_minor'], $item['refund_minor']]);

        $sink = $this->redirectMessages();
        $request = refunds::request($student, $item['key'], 'Wrong course');
        $this->assertSame(refunds::PENDING, $request->status);
        $this->assertSame([], refunds::refundable($student), 'one request per purchase');
        $this->assertTrue($this->enrolled($student), 'nothing changes before the admin answers');

        refunds::process((int) get_admin()->id, (int) $request->id, 'approve');

        // 100 left + 180 back; the teacher gives back 80; the platform gives back 100 and keeps the 20 fee.
        $this->assertSame(28000, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame(0, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertSame($platformbefore - 10000, wallets::balance(wallets::PLATFORM, 0));
        $this->assertSame('revoked', $DB->get_field('nit_purchase', 'status', ['id' => $purchase->id]));
        $this->assertSame('reversed', $DB->get_field('nit_earning', 'status', ['purchaseid' => $purchase->id]));
        $this->assertFalse($this->enrolled($student));
        $this->assertSame(refunds::APPROVED, $DB->get_field('nit_refund_request', 'status', ['id' => $request->id]));
        // The admins were told about the request, the student about the answer.
        $this->assertContains((int) $student, array_map(fn($m) => (int) $m->useridto, $sink->get_messages()));

        $this->expectException(finance_exception::class);
        refunds::process((int) get_admin()->id, (int) $request->id, 'approve');
    }

    public function test_lesson_refund_unenrols_only_if_the_lesson_got_the_student_in(): void {
        $cm = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        purchases::set_price((int) $cm->cmid, (int) $this->course->id, 5000);
        $student = $this->student(10000);
        purchases::buy_with_wallet($student, (int) $cm->cmid);
        $this->assertTrue($this->enrolled($student));

        $item = $this->only_refundable($student);
        $this->assertSame(catalog::CM, $item['itemtype']);
        refunds::process((int) get_admin()->id, (int) refunds::request($student, $item['key'])->id, 'approve');
        $this->assertSame(5000 + 4500, wallets::balance(wallets::STUDENT, $student));
        $this->assertFalse($this->enrolled($student));
    }

    public function test_rejection_needs_a_reason_and_keeps_the_purchase(): void {
        $student = $this->student(30000);
        wallet_checkout::buy_course($student, (int) $this->course->id);
        $request = refunds::request($student, $this->only_refundable($student)['key']);
        try {
            refunds::process((int) get_admin()->id, (int) $request->id, 'reject', '  ');
            $this->fail('rejected without a reason');
        } catch (finance_exception $e) {
            $this->assertSame('err_reasonrequired', $e->errorcode);
        }
        refunds::process((int) get_admin()->id, (int) $request->id, 'reject', 'Already watched');
        $this->assertSame(10000, wallets::balance(wallets::STUDENT, $student));
        $this->assertTrue($this->enrolled($student));
        $this->assertSame([], refunds::refundable($student), 'not asked again after a rejection');
    }

    public function test_only_within_the_period_and_not_for_codes(): void {
        global $DB;
        $student = $this->student(30000);
        $purchase = wallet_checkout::buy_course($student, (int) $this->course->id);
        $DB->set_field('nit_purchase', 'timecreated', time() - 4 * DAYSECS, ['id' => $purchase->id]);
        $this->assertSame([], refunds::refundable($student), 'older than 3 days');

        $DB->set_field('nit_purchase', 'timecreated', time(), ['id' => $purchase->id]);
        set_config('refund_days', 0, 'local_nit_finance');
        $this->assertSame([], refunds::refundable($student), 'refunds closed');

        set_config('refund_days', 3, 'local_nit_finance');
        $DB->set_field('nit_purchase', 'method', 'code', ['id' => $purchase->id]);
        $this->assertSame([], refunds::refundable($student), 'paid with a code outside the site');

        $this->expectException(finance_exception::class);
        refunds::request($student, 'wallet:course:' . $purchase->id);
    }

    public function test_online_course_refund_goes_to_the_wallet_and_marks_the_payment(): void {
        global $DB;
        $student = $this->student(0);
        $txid = $DB->insert_record('local_payments_transactions', (object) ['userid' => $student,
            'courseid' => $this->course->id, 'provider_id' => 0, 'order_id' => 'R1', 'idempotency_key' => 'r1',
            'amount' => 150, 'currency' => 'EGP', 'status' => 'completed', 'country' => 'EG',
            'metadata' => json_encode(['item_type' => 'course']), 'expires_at' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        \local_payments\enrollment_handler::enrol_user($student, (int) $this->course->id);

        $item = $this->only_refundable($student);
        $this->assertSame(refunds::SOURCE_ONLINE, $item['source']);
        refunds::process((int) get_admin()->id, (int) refunds::request($student, $item['key'])->id, 'approve');

        $this->assertSame(13500, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame('refunded', $DB->get_field('local_payments_transactions', 'status', ['id' => $txid]));
        $this->assertEquals(135, $DB->get_field('local_payments_refunds', 'amount', ['transaction_id' => $txid]));
        $this->assertFalse($this->enrolled($student));
    }

    public function test_access_from_a_subscription_is_kept(): void {
        global $DB;
        $student = $this->student(0);
        $DB->insert_record('local_payments_transactions', (object) ['userid' => $student,
            'courseid' => $this->course->id, 'provider_id' => 0, 'order_id' => 'R2', 'idempotency_key' => 'r2',
            'amount' => 150, 'currency' => 'EGP', 'status' => 'completed', 'country' => 'EG',
            'metadata' => json_encode(['item_type' => 'course']), 'expires_at' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        \local_payments\enrollment_handler::enrol_user($student, (int) $this->course->id);
        // An active subscription (bought earlier, outside the period) also covers the course.
        $planid = (int) $DB->insert_record('nit_subscription', (object) ['name' => 'Gold', 'price' => 300,
            'duration_days' => 30, 'status' => 'active', 'b2b_enabled' => 0, 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 2]);
        $DB->insert_record('nit_course_access', (object) ['subscriptionid' => $planid,
            'courseid' => $this->course->id, 'timecreated' => time(), 'usermodified' => 2]);
        $DB->insert_record('nit_sub_purchase', (object) ['subscriptionid' => $planid, 'userid' => $student,
            'type' => 'normal', 'seats' => 0, 'base_price' => 300, 'discount_percent' => 0, 'price_paid' => 300,
            'duration_days' => 30, 'status' => 'active', 'source' => 'admin_assigned', 'reference' => 'x',
            'timeactivated' => time(), 'expires_at' => time() + 30 * DAYSECS, 'timecreated' => time() - 10 * DAYSECS]);

        $item = $this->only_refundable($student);
        refunds::process((int) get_admin()->id, (int) refunds::request($student, $item['key'])->id, 'approve');
        $this->assertTrue($this->enrolled($student), 'the subscription still gives the course');
    }

    public function test_wallet_subscription_refund_cancels_it(): void {
        global $DB;
        $planid = (int) $DB->insert_record('nit_subscription', (object) ['name' => 'Silver', 'price' => 100,
            'duration_days' => 30, 'status' => 'active', 'b2b_enabled' => 0, 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 2]);
        $student = $this->student(20000);
        $sub = wallet_checkout::buy_subscription($student, $planid);
        $platformbefore = wallets::balance(wallets::PLATFORM, 0);

        $item = $this->only_refundable($student);
        $this->assertSame(refunds::SUBSCRIPTION, $item['itemtype']);
        refunds::process((int) get_admin()->id, (int) refunds::request($student, $item['key'])->id, 'approve');

        $this->assertSame(10000 + 9000, wallets::balance(wallets::STUDENT, $student));
        $this->assertSame($platformbefore - 9000, wallets::balance(wallets::PLATFORM, 0));
        $this->assertSame('cancelled', $DB->get_field('nit_sub_purchase', 'status', ['id' => $sub->id]));
    }
}
