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

namespace local_nit_subscriptions;

/**
 * Tests for the mobile enrol_course logic (free course / subscription-covered course).
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_subscriptions\subscription_purchase_manager::enrol_free_or_covered
 */
final class enrol_course_test extends \advanced_testcase {

    /**
     * Give a course an active default price in local_payments.
     *
     * @param int $courseid
     */
    private function price(int $courseid): void {
        global $DB;
        $DB->insert_record('local_payments_course_prices', (object) [
            'courseid' => $courseid, 'country' => '*', 'currency' => 'EGP', 'price' => 100,
            'is_default' => 1, 'is_active' => 1, 'priority' => 0, 'created_by' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

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

    public function test_free_course_enrols_without_end_date(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $r = subscription_purchase_manager::enrol_free_or_covered((int) $course->id, (int) $user->id);
        $this->assertSame('free', $r['access']);
        $this->assertFalse($r['already_enrolled']);
        $this->assertSame(0, $r['timeend']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id, '', true));

        $again = subscription_purchase_manager::enrol_free_or_covered((int) $course->id, (int) $user->id);
        $this->assertTrue($again['already_enrolled']);
    }

    public function test_paid_course_requires_payment_unless_covered(): void {
        global $DB;
        if (!class_exists('\local_payments\price_resolver')) {
            $this->markTestSkipped('local_payments not installed');
        }
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->price((int) $course->id);

        $this->assert_error('err_paymentrequired',
            fn() => subscription_purchase_manager::enrol_free_or_covered((int) $course->id, (int) $user->id));

        // An active subscription that covers the course unlocks it until the subscription expires.
        $subid = subscription_manager::create_subscription(['name' => 'Plan', 'price' => 300,
            'duration_days' => 30, 'active' => 1], 2);
        subscription_manager::set_subscription_courses($subid, [(int) $course->id], 2);
        $expires = time() + 30 * DAYSECS;
        $DB->insert_record('nit_sub_purchase', (object) [
            'subscriptionid' => $subid, 'userid' => $user->id, 'type' => 'normal', 'seats' => 0,
            'base_price' => 300, 'price_paid' => 300, 'duration_days' => 30, 'status' => 'active',
            'source' => 'online', 'timeactivated' => time(), 'expires_at' => $expires, 'timecreated' => time(),
        ]);

        $r = subscription_purchase_manager::enrol_free_or_covered((int) $course->id, (int) $user->id);
        $this->assertSame('subscription', $r['access']);
        $this->assertSame($expires, $r['timeend']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id, '', true));
    }

    public function test_unknown_or_site_course_is_not_found(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assert_error('err_coursenotfound',
            fn() => subscription_purchase_manager::enrol_free_or_covered(SITEID, (int) $user->id));
        $this->assert_error('err_coursenotfound',
            fn() => subscription_purchase_manager::enrol_free_or_covered(987654, (int) $user->id));
    }

    public function test_hidden_free_course_is_not_open_for_self_enrolment(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->assert_error('err_coursenotfound',
            fn() => subscription_purchase_manager::enrol_free_or_covered((int) $course->id, (int) $user->id));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user->id));
    }
}
