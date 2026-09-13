<?php
namespace local_academy;

/**
 * Unit tests for {@see \local_academy\license_status::build()} — the payload the
 * custom API's get_license_status returns to an admin/owner. The capability gate
 * and envelope live in api.php (a script that exits) and are exercised at the HTTP
 * layer; here we test the pure data assembly, which is where the reported bugs
 * (empty subscription, missing GB) actually lived.
 *
 * @package    local_academy
 * @covers     \local_academy\license_status
 */
final class license_status_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** A fully-configured, enforced academy: package resources, GB, and the term. */
    public function test_build_reports_package_gb_and_subscription_term(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        set_config('definition', json_encode([
            'storagegb' => 20,
            'features'  => ['coupons', 'packages'],
        ]), 'local_license');
        set_config('expirydate', '2027-01-15', 'local_license');
        set_config('subscribedat', '2026-07-01', 'local_license');

        $data = license_status::build();

        // Package: resources + GB + the nit2 pointer.
        $this->assertTrue($data['package']['enforced']);
        $this->assertSame('basic', $data['package']['tier']);
        $this->assertSame('Basic', $data['package']['name']);
        $this->assertSame(20, $data['package']['storagegb']);
        $this->assertSame(['coupons', 'packages'], $data['package']['features']);
        $this->assertTrue($data['package']['billing_in_nit2']);
        $this->assertSame(3, $data['package']['limits']['maxcourses']);

        // Subscription: both dates present (the values that read empty before the fix).
        $this->assertSame(strtotime('2026-07-01'), $data['subscription']['subscribedat']);
        $this->assertNotNull($data['subscription']['subscribeddate']);
        $this->assertSame(strtotime('2027-01-15 23:59:59'), $data['subscription']['expiry']);
        $this->assertNotNull($data['subscription']['expirydate']);
        $this->assertSame('active', $data['subscription']['status']);
        $this->assertFalse($data['subscription']['is_expired']);

        // Usage keys are always present and integer-typed.
        foreach (['courses', 'teachers', 'quiz', 'video', 'pdf'] as $k) {
            $this->assertArrayHasKey($k, $data['usage']);
            $this->assertIsInt($data['usage'][$k]);
        }
    }

    /** No term pushed (fresh academy) ⇒ subscription dates are null, status active. */
    public function test_build_null_subscription_when_no_term(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        // No expirydate / subscribedat configured — the fresh-academy case.

        $data = license_status::build();
        $this->assertNull($data['subscription']['subscribedat']);
        $this->assertNull($data['subscription']['subscribeddate']);
        $this->assertNull($data['subscription']['expiry']);
        $this->assertNull($data['subscription']['expirydate']);
        $this->assertNull($data['subscription']['daysleft']);
        $this->assertSame('active', $data['subscription']['status']);
    }

    /** The admin suspend switch surfaces as status "suspended". */
    public function test_build_reports_suspended(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        set_config('suspended', 1, 'local_license');

        $data = license_status::build();
        $this->assertTrue($data['subscription']['is_suspended']);
        $this->assertSame('suspended', $data['subscription']['status']);
    }
}
