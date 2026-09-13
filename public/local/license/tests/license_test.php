<?php
namespace local_license;

/**
 * Unit tests for {@see \local_license\license}.
 *
 * Covers the core decisions this class makes from plugin config: tier resolution,
 * the dynamic-definition merge, expiry/subscribed-at parsing, and the
 * enforcement-off short circuits. These are the values the academy's mobile API
 * (get_license_status) and the web "My plan" page report, and the ones that read
 * empty when the control plane hasn't pushed a term.
 *
 * @package    local_license
 * @covers     \local_license\license
 */
final class license_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** With nothing configured, the tier defaults to 'demo' and enforcement is off. */
    public function test_defaults_when_unconfigured(): void {
        $this->assertFalse(license::is_enforced());
        $this->assertSame('demo', license::tier());
        // Enforcement off ⇒ everything is unlimited / all sources, regardless of tier.
        $this->assertSame(-1, license::max_courses());
        $this->assertSame(-1, license::bucket_limit('quiz'));
        $this->assertSame('all', license::video_source());
        $this->assertTrue(license::has_feature('drm'));
    }

    /** A configured tier drives the built-in TIERS numbers once enforcement is on. */
    public function test_tier_limits_from_catalogue(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');

        $this->assertTrue(license::is_enforced());
        $this->assertSame('basic', license::tier());
        $this->assertSame('Basic', license::tiername());
        $this->assertSame(3, license::max_courses());
        $this->assertSame(1, license::max_teachers());
        $this->assertSame('youtube', license::video_source());
    }

    /** A pushed JSON definition wins over the catalogue, and missing keys backfill. */
    public function test_pushed_definition_overrides_and_backfills(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        // Control plane pushes richer limits + features + storage; omits maxteachers.
        set_config('definition', json_encode([
            'name'       => 'Basic Plus',
            'maxcourses' => 7,
            'storagegb'  => 20,
            'features'   => ['coupons', 'packages'],
            'limits'     => ['quiz' => 5, 'video' => -1, 'pdf' => 3, 'default' => -1],
        ]), 'local_license');

        $def = license::tierdef();
        $this->assertSame('Basic Plus', $def['name']);          // pushed wins
        $this->assertSame(7, $def['maxcourses']);               // pushed wins
        $this->assertSame(20, (int) $def['storagegb']);         // pushed-only key
        $this->assertSame(1, (int) $def['maxteachers']);        // backfilled from catalogue
        $this->assertSame(5, license::bucket_limit('quiz'));    // from pushed limits
        $this->assertTrue(license::has_feature('coupons'));
        $this->assertFalse(license::has_feature('drm'));        // not in pushed features
    }

    /** Invalid JSON in the definition falls back to the catalogue, never fatals. */
    public function test_bad_definition_falls_back_to_catalogue(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'standard', 'local_license');
        set_config('definition', '{not valid json', 'local_license');

        $this->assertSame('Standard', license::tiername());
        $this->assertSame(10, license::max_courses());
    }

    /** expiry(): empty ⇒ 0; a plain YYYY-MM-DD parses to end-of-day; a unix ts passes through. */
    public function test_expiry_parsing(): void {
        $this->assertSame(0, license::expiry());

        set_config('expirydate', '2027-01-15', 'local_license');
        $this->assertSame(strtotime('2027-01-15 23:59:59'), license::expiry());

        $ts = 1900000000;
        set_config('expirydate', (string) $ts, 'local_license');
        $this->assertSame($ts, license::expiry());
    }

    /** subscribed_at(): empty ⇒ 0; a date string parses; a unix ts passes through. */
    public function test_subscribed_at_parsing(): void {
        $this->assertSame(0, license::subscribed_at());

        set_config('subscribedat', '2026-07-01', 'local_license');
        $this->assertSame(strtotime('2026-07-01'), license::subscribed_at());

        $ts = 1780000000;
        set_config('subscribedat', (string) $ts, 'local_license');
        $this->assertSame($ts, license::subscribed_at());
    }

    /** No expiry set ⇒ never expires, whatever enforcement says. */
    public function test_no_expiry_never_locks(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        $this->assertFalse(license::is_expired());
    }

    /** Past the expiry date + grace, an enforced academy is expired. */
    public function test_expired_after_grace(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        set_config('gracedays', 0, 'local_license');
        set_config('expirydate', date('Y-m-d', strtotime('-2 days')), 'local_license');
        $this->assertTrue(license::is_expired());
    }

    /** A future expiry within an enforced term is not expired, and grace holds a lapse. */
    public function test_not_expired_within_grace(): void {
        set_config('enabled', 1, 'local_license');
        set_config('tier', 'basic', 'local_license');
        set_config('gracedays', 7, 'local_license');
        // Expired yesterday but inside a 7-day grace window ⇒ still not locked.
        set_config('expirydate', date('Y-m-d', strtotime('-1 day')), 'local_license');
        $this->assertFalse(license::is_expired());
    }

    /** Enforcement off ⇒ is_expired() is always false even with a past date. */
    public function test_not_enforced_never_expired(): void {
        set_config('expirydate', '2000-01-01', 'local_license');
        $this->assertFalse(license::is_expired());
    }

    /** The admin suspend switch is independent of tier/enforcement. */
    public function test_suspended_flag(): void {
        $this->assertFalse(license::is_suspended());
        set_config('suspended', 1, 'local_license');
        $this->assertTrue(license::is_suspended());
    }
}
