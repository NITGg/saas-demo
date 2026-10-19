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

namespace local_nit_analytics_testgw {
    /**
     * A payment gateway that confirms every payment (for the callback test only).
     *
     * @package    local_nit_analytics
     * @copyright  2026 NIT
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class gateway extends \local_payments\provider\base_provider {
        /** @var float amount the gateway reports as paid */
        public static float $paid = 0;

        public function initialize_payment(\local_payments\provider\payment_request $request):
                \local_payments\provider\checkout_response {
            throw new \coding_exception('not used');
        }

        public function verify_payment(string $ref): \local_payments\provider\verification_result {
            return new \local_payments\provider\verification_result([
                'verified' => true, 'status' => 'SUCCESS', 'amount' => self::$paid, 'currency' => 'EGP',
                'provider_txn_id' => 'txn-' . $ref, 'provider_order_id' => 'ord-' . $ref,
                'payment_method_type' => 'card', 'error_message' => '', 'raw_response' => [],
            ]);
        }

        public function handle_webhook(string $payload, array $headers): \local_payments\provider\webhook_result {
            throw new \coding_exception('not used');
        }

        public function refund(string $id, float $amount, string $currency, string $reason = ''):
                \local_payments\provider\refund_result {
            throw new \coding_exception('not used');
        }

        public function void_payment(string $id, string $reason = ''): \local_payments\provider\refund_result {
            throw new \coding_exception('not used');
        }

        public function get_transaction(string $ref): \local_payments\provider\transaction_info {
            throw new \coding_exception('not used');
        }

        public function supports_refund(): bool {
            return false;
        }

        public function supports_void(): bool {
            return false;
        }

        public function supported_currencies(): array {
            return ['EGP'];
        }

        public function supported_countries(): array {
            return ['*'];
        }
    }
}

namespace local_nit_analytics {
    /**
     * A course paid by card and confirmed on the return page (before the gateway's webhook) is a
     * purchase conversion — once, even when the webhook confirms it again.
     *
     * @package    local_nit_analytics
     * @copyright  2026 NIT
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @covers     \local_payments\manager::verify_callback
     * @covers     \local_nit_analytics\conversions
     */
    final class callback_purchase_test extends \advanced_testcase {

        public function test_course_paid_on_return_page_is_a_conversion(): void {
            global $DB;
            if (!class_exists('\local_payments\manager')) {
                $this->markTestSkipped('local_payments not installed');
            }
            $this->resetAfterTest();
            $this->redirectMessages();
            $gen = $this->getDataGenerator();
            $course = $gen->create_course();
            $student = $gen->create_user();
            $providerid = $DB->insert_record('local_payments_providers', (object) [
                'name' => 'testgw', 'display_name' => 'Test', 'plugin_name' => 'local_nit_analytics_testgw',
                'enabled' => 1, 'priority' => 1, 'supported_countries' => '*', 'supported_currencies' => '*',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            \local_nit_analytics_testgw\gateway::$paid = 150.50;
            $txid = $DB->insert_record('local_payments_transactions', (object) [
                'userid' => $student->id, 'courseid' => $course->id, 'provider_id' => $providerid,
                'order_id' => 'PAY-TEST-1', 'idempotency_key' => 'k1', 'amount' => 150.50, 'original_amount' => 150.50,
                'currency' => 'EGP', 'status' => 'pending', 'provider_session_id' => 'sess-1',
                'metadata' => json_encode(['item_type' => 'course']), 'timecreated' => time(), 'timemodified' => time(),
            ]);
            $this->setUser($student);

            $result = \local_payments\manager::verify_callback('PAY-TEST-1');
            $this->assertTrue($result->success);

            $row = $DB->get_record(conversions::TABLE, ['kind' => 'course', 'ref' => (string) $txid]);
            $this->assertNotFalse($row, 'a course confirmed on the return page must be a conversion');
            $this->assertSame(15050, (int) $row->amount_minor);
            $this->assertSame('EGP', $row->currency);
            $this->assertCount(1, conversions::take_pending((int) $student->id), 'the browser purchase event is queued');

            // The webhook confirming the same payment later does not count it again.
            \local_payments\event\payment_completed::create([
                'context' => \context_course::instance($course->id), 'objectid' => $txid, 'userid' => $student->id,
                'other' => ['courseid' => $course->id, 'amount' => '150.50', 'currency' => 'EGP', 'provider' => 'testgw'],
            ])->trigger();
            $this->assertSame(1, $DB->count_records(conversions::TABLE, ['kind' => 'course', 'ref' => (string) $txid]));
        }
    }
}
