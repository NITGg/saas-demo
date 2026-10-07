<?php
namespace local_payments;

/**
 * "Already purchased" must end when the student is taken out of the course.
 *
 * @package    local_payments
 * @covers     \local_payments\price_resolver::is_purchased
 */
final class price_resolver_test extends \advanced_testcase {

    /**
     * A completed course payment.
     *
     * @param int $userid
     * @param int $courseid
     */
    private function paid(int $userid, int $courseid): void {
        global $DB;
        $DB->insert_record('local_payments_transactions', (object) ['userid' => $userid, 'courseid' => $courseid,
            'provider_id' => 0, 'order_id' => 'T-' . random_string(8), 'amount' => 100, 'currency' => 'EGP',
            'status' => status_machine::COMPLETED, 'timecreated' => time(), 'timemodified' => time()]);
    }

    public function test_paid_and_enrolled_is_purchased(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $this->paid((int) $user->id, (int) $course->id);
        $gen->enrol_user($user->id, $course->id, 'student');

        $this->assertTrue(price_resolver::is_purchased((int) $course->id, (int) $user->id));
    }

    public function test_unenrolled_student_can_buy_again(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $this->paid((int) $user->id, (int) $course->id);
        $gen->enrol_user($user->id, $course->id, 'student');

        // The admin removes the student from the course.
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $user->id);

        // Before the fix this stayed true, so the buy button never came back.
        $this->assertFalse(price_resolver::is_purchased((int) $course->id, (int) $user->id));
    }

    public function test_no_payment_is_not_purchased(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');

        $this->assertFalse(price_resolver::is_purchased((int) $course->id, (int) $user->id));
    }
}
