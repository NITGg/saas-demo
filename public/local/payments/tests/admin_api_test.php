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

namespace local_payments;

/**
 * Tests for the classes behind the payments mobile admin API (api.php).
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_payments\course_price_manager
 * @covers     \local_payments\provider_manager
 * @covers     \local_payments\report_manager
 */
final class admin_api_test extends \advanced_testcase {

    /**
     * Assert that a callable throws a moodle_exception with the given errorcode.
     *
     * @param string $errorcode
     * @param callable $fn
     */
    private function assert_error(string $errorcode, callable $fn): void {
        try {
            $fn();
            $this->fail("Expected moodle_exception $errorcode");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    /**
     * Insert a provider row.
     *
     * @param array $overrides
     * @return int id
     */
    private function provider(array $overrides = []): int {
        global $DB;
        return (int) $DB->insert_record('local_payments_providers', (object) ($overrides + [
            'name' => 'testpay', 'display_name' => 'Test Pay', 'plugin_name' => 'paymentprovider_testpay',
            'enabled' => 0, 'priority' => 100, 'supported_countries' => '["EG","SA"]',
            'supported_currencies' => '*', 'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * Insert a transaction row.
     *
     * @param array $overrides
     * @return int id
     */
    private function transaction(array $overrides): int {
        global $DB;
        static $n = 0;
        $n++;
        return (int) $DB->insert_record('local_payments_transactions', (object) ($overrides + [
            'userid' => 2, 'courseid' => 0, 'provider_id' => 0, 'order_id' => 'T' . $n, 'idempotency_key' => 'k' . $n,
            'amount' => 10, 'currency' => 'EGP', 'status' => status_machine::COMPLETED, 'country' => 'EG',
            'metadata' => '{}', 'expires_at' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    public function test_first_price_is_default_and_validation_rules(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $first = course_price_manager::save_course_price($course->id, 0,
            ['country' => '*', 'currency' => 'EGP', 'price' => 120.5], 2);
        $this->assertTrue($first['is_default']);
        $this->assertTrue($first['is_active']);
        $this->assertSame(12050, $first['price_minor']);
        $this->assertSame(120.5, $first['price']);

        $eg = course_price_manager::save_course_price($course->id, 0,
            ['country' => 'eg', 'currency' => 'EGP', 'price' => 99], 2);
        $this->assertSame('EG', $eg['country']);
        $this->assertFalse($eg['is_default']);

        $this->assert_error('err_oneactivepercountry', fn() => course_price_manager::save_course_price($course->id, 0,
            ['country' => 'EG', 'currency' => 'EGP', 'price' => 80], 2));
        $this->assert_error('err_onedefault', fn() => course_price_manager::save_course_price($course->id, 0,
            ['country' => 'SA', 'currency' => 'SAR', 'price' => 30, 'is_default' => 1], 2));
        $this->assert_error('err_pricepositive', fn() => course_price_manager::save_course_price($course->id, 0,
            ['country' => 'SA', 'currency' => 'SAR', 'price' => 0], 2));
        $this->assert_error('err_invalidcurrency', fn() => course_price_manager::save_course_price($course->id, 0,
            ['country' => 'SA', 'currency' => 'XXX', 'price' => 5], 2));
        $this->assert_error('err_invalidcountry', fn() => course_price_manager::save_course_price($course->id, 0,
            ['country' => 'ZZ', 'currency' => 'SAR', 'price' => 5], 2));
        $this->assert_error('err_coursenotfound', fn() => course_price_manager::get_course_prices(SITEID));

        // Editing a rule keeps its own country/default without tripping the uniqueness rules.
        $edited = course_price_manager::save_course_price($course->id, $eg['id'],
            ['country' => 'EG', 'currency' => 'EGP', 'price' => 95], 2);
        $this->assertSame(95.0, $edited['price']);

        $list = course_price_manager::get_course_prices($course->id);
        $this->assertCount(2, $list['prices']);
        $this->assertSame('*', $list['prices'][0]['country']);
        $this->assertTrue(price_resolver::has_pricing($course->id));

        $other = $this->getDataGenerator()->create_course();
        $this->assert_error('err_pricenotfound', fn() => course_price_manager::delete_course_price($other->id, $eg['id']));
        $this->assertTrue(course_price_manager::delete_course_price($course->id, $eg['id']));
        $this->assertCount(1, course_price_manager::get_course_prices($course->id)['prices']);
    }

    public function test_set_provider_changes_only_what_is_sent(): void {
        $this->resetAfterTest();
        $id = $this->provider();

        $p = provider_manager::set_provider($id, true, null);
        $this->assertTrue($p['enabled']);
        $this->assertSame(100, $p['priority']);
        $this->assertSame(['EG', 'SA'], $p['supported_countries']);
        $this->assertSame([], $p['supported_currencies']);

        $p = provider_manager::set_provider($id, null, 20);
        $this->assertTrue($p['enabled']);
        $this->assertSame(20, $p['priority']);

        $this->assert_error('err_invalidpriority', fn() => provider_manager::set_provider($id, null, -1));
        $this->assert_error('err_nothingtochange', fn() => provider_manager::set_provider($id, null, null));
        $this->assert_error('err_providernotfound', fn() => provider_manager::set_provider($id + 100, true, null));
        $this->assertCount(1, provider_manager::get_providers());
    }

    public function test_revenue_report_and_transaction_filters(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $pid = $this->provider();

        $this->transaction(['userid' => $user->id, 'courseid' => $course->id, 'provider_id' => $pid, 'amount' => 150,
            'metadata' => json_encode(['item_type' => 'course', 'item_id' => $course->id])]);
        $this->transaction(['userid' => $user->id, 'provider_id' => $pid, 'amount' => 300,
            'metadata' => json_encode(['item_type' => 'subscription', 'item_id' => 7, 'subscription_name' => 'Gold'])]);
        $this->transaction(['provider_id' => $pid, 'courseid' => $course->id, 'amount' => 10, 'currency' => 'USD',
            'country' => 'SA']);
        $this->transaction(['provider_id' => $pid, 'status' => status_machine::FAILED]);

        $report = report_manager::get_revenue_report();
        $this->assertSame(3, $report['total_transactions']);
        $this->assertSame(460.0, $report['total_revenue']);
        $this->assertSame(1, $report['failed_count']);
        $this->assertCount(2, $report['revenue_by_currency']);
        $this->assertSame(45000, $report['revenue_by_currency'][0]['revenue_minor']);
        $this->assertCount(2, $report['top_courses']); // subscription (courseid 0) is not a course.

        $all = report_manager::get_transactions([], 0, 2);
        $this->assertSame(4, $all['total']);
        $this->assertCount(2, $all['transactions']);

        $mine = report_manager::get_transactions(['userid' => $user->id, 'status' => 'completed']);
        $this->assertSame(2, $mine['total']);
        $types = array_column($mine['transactions'], 'item_type');
        sort($types);
        $this->assertSame(['course', 'subscription'], $types);
        $names = array_column($mine['transactions'], 'item_name', 'item_type');
        $this->assertSame('Gold', $names['subscription']);

        $this->assert_error('err_invalidstatus', fn() => report_manager::get_transactions(['status' => 'bogus']));
        $this->assertSame(0, report_manager::get_transactions(['datefrom' => time() + 3600])['total']);
    }
}
