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
 * Consent gating, tracker snippets, UTM capture (session + cookie) and sign-up attribution.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_analytics\tracker
 * @covers     \local_nit_analytics\observer
 */
final class tracker_test extends \advanced_testcase {

    /** @var array Consent cookie for "accepted". */
    const GRANTED = [tracker::CONSENT_COOKIE => 'granted'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('requireconsent', 1, 'local_nit_analytics');
    }

    /**
     * Valid ids are accepted (case-insensitively); malformed ones — including script injection —
     * are dropped and never reach the page.
     *
     * @return void
     */
    public function test_ids_are_validated(): void {
        set_config('ga4_id', 'g-abc123xyz', 'local_nit_analytics');
        set_config('gtm_id', "GTM-12';alert(1)//", 'local_nit_analytics');
        set_config('meta_pixel_id', '1234567890', 'local_nit_analytics');

        $this->assertSame('G-ABC123XYZ', tracker::id('ga4_id'));
        $this->assertSame('', tracker::id('gtm_id'));
        $this->assertSame('1234567890', tracker::id('meta_pixel_id'));

        $html = tracker::head_html(false);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('gtm.js', $html, 'a rejected GTM id prints no GTM snippet');
        $this->assertSame('', tracker::body_html());
    }

    /**
     * With consent required, nothing tracks until the visitor accepts; the banner shows only while
     * the choice is open; declining keeps trackers off.
     *
     * @return void
     */
    public function test_consent_gates_tracking(): void {
        $this->setGuestUser();
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');

        $this->assertFalse(tracker::should_track('standard', []), 'no tracking before a choice');
        $this->assertTrue(tracker::should_ask_consent('standard', []), 'the banner asks');

        $this->assertTrue(tracker::should_track('standard', self::GRANTED));
        $this->assertFalse(tracker::should_ask_consent('standard', self::GRANTED));

        $denied = [tracker::CONSENT_COOKIE => 'denied'];
        $this->assertFalse(tracker::should_track('standard', $denied));
        $this->assertFalse(tracker::should_ask_consent('standard', $denied), 'a decline is not asked again');

        $this->assertFalse(tracker::should_track('standard', [tracker::CONSENT_COOKIE => 'yes please']),
            'only the exact value "granted" counts');
    }

    /**
     * Where the site turns consent off, trackers run and no banner shows.
     *
     * @return void
     */
    public function test_consent_can_be_turned_off(): void {
        $this->setGuestUser();
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        set_config('requireconsent', 0, 'local_nit_analytics');
        $this->assertTrue(tracker::should_track('standard', []));
        $this->assertFalse(tracker::should_ask_consent('standard', []));
    }

    /**
     * No ids configured: no tracking and no banner; popups and admins are skipped.
     *
     * @return void
     */
    public function test_nothing_without_ids_and_on_skipped_pages(): void {
        $this->setGuestUser();
        $this->assertFalse(tracker::should_track('standard', self::GRANTED));
        $this->assertFalse(tracker::should_ask_consent('standard', []), 'no banner when there is nothing to track');

        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        $this->assertFalse(tracker::should_track('popup', self::GRANTED));

        $this->setAdminUser();
        $this->assertFalse(tracker::should_track('standard', self::GRANTED));
        set_config('trackadmins', 1, 'local_nit_analytics');
        $this->assertTrue(tracker::should_track('standard', self::GRANTED));
    }

    /**
     * The banner is printed right after <body> (not in the footer, which Boost hides inside the "?"
     * popover), and the callback is registered for that hook.
     *
     * @covers \local_nit_analytics\local\hook_callbacks
     * @return void
     */
    public function test_banner_is_printed_at_top_of_body(): void {
        global $PAGE;
        $this->setGuestUser();
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('frontpage');

        $hook = new \core\hook\output\before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        local\hook_callbacks::before_standard_top_of_body_html_generation($hook);
        $this->assertStringContainsString('id="nit-analytics-consent"', $hook->get_output());

        $callbacks = \core\hook\manager::get_instance()->get_callbacks_for_hook(
            \core\hook\output\before_standard_top_of_body_html_generation::class);
        $ours = array_filter($callbacks, fn($c) => strpos((string) ($c['callback'] ?? ''), 'local_nit_analytics') !== false);
        $this->assertNotEmpty($ours, 'the top-of-body callback must be registered');
    }

    /**
     * The banner's buttons store the choice in the consent cookie on the site's own path.
     *
     * @return void
     */
    public function test_banner_sets_consent_cookie(): void {
        $html = tracker::consent_banner_html();
        $this->assertStringContainsString(get_string('consent_accept', 'local_nit_analytics'), $html);
        $this->assertStringContainsString(tracker::CONSENT_COOKIE, $html);
        $this->assertStringContainsString('granted', $html);
        $this->assertStringContainsString('denied', $html);
    }

    /**
     * Each id adds only its own snippet; sign-up and purchase events are printed for each tracker.
     *
     * @return void
     */
    public function test_snippets_and_conversion_events(): void {
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        $html = tracker::head_html(false);
        $this->assertStringContainsString('gtag/js?id=G-TEST1234', $html);
        $this->assertStringNotContainsString('fbq(', $html);

        set_config('meta_pixel_id', '9876543210', 'local_nit_analytics');
        set_config('gtm_id', 'GTM-ABCD12', 'local_nit_analytics');
        $html = tracker::head_html(true, [['ref' => 'flex-7', 'amount_minor' => 150050, 'currency' => 'EGP']]);
        $this->assertStringContainsString("gtag('event','sign_up')", $html);
        $this->assertStringContainsString("fbq('track','CompleteRegistration')", $html);
        $this->assertStringContainsString('"transaction_id":"flex-7","value":1500.5,"currency":"EGP"', $html);
        $this->assertStringContainsString("fbq('track','Purchase',{\"value\":1500.5,\"currency\":\"EGP\"}", $html);
        $this->assertStringContainsString("event:'purchase'", $html);
        $this->assertStringContainsString('ns.html?id=GTM-ABCD12', tracker::body_html());
        $this->assertStringNotContainsString('sign_up', tracker::head_html(false));
    }

    /**
     * With consent, the session's attribution is written to the 90-day cookie once.
     *
     * @return void
     */
    public function test_attribution_cookie_written_once(): void {
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        $utm = ['utm_source' => 'facebook'];
        $html = tracker::head_html(false, [], $utm, self::GRANTED);
        $this->assertStringContainsString(tracker::UTM_COOKIE . '=', $html);
        $this->assertStringContainsString('max-age=' . tracker::UTM_COOKIE_LIFETIME, $html);

        $already = self::GRANTED + [tracker::UTM_COOKIE => json_encode($utm)];
        $this->assertStringNotContainsString(tracker::UTM_COOKIE . '=', tracker::head_html(false, [], $utm, $already));
    }

    /**
     * First touch wins within a session; a landing with UTM is logged once; values are cleaned.
     *
     * @return void
     */
    public function test_capture_keeps_first_touch_and_logs_landing(): void {
        global $DB;
        $session = new \stdClass();
        tracker::capture(['id' => 5], [], $session);
        $this->assertObjectNotHasProperty('local_nit_analytics_utm', $session, 'no UTM, nothing kept');

        tracker::capture([
            'utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => str_repeat('x', 150),
            'utm_term' => '<b>english</b>', 'gclid' => 'abc',
        ], [], $session);
        tracker::capture(['utm_source' => 'google'], [], $session);

        $utm = $session->local_nit_analytics_utm;
        $this->assertSame('facebook', $utm['utm_source']);
        $this->assertSame(100, \core_text::strlen($utm['utm_campaign']));
        $this->assertSame('english', $utm['utm_term']);
        $this->assertArrayNotHasKey('gclid', $utm);
        $this->assertSame(1, $DB->count_records(funnel::LANDING_TABLE), 'one landing per visit');
        $this->assertSame(1, $DB->count_records(funnel::LANDING_TABLE, ['source' => 'facebook', 'medium' => 'cpc']));
    }

    /**
     * A visitor returning in a new session is attributed from the cookie, not counted as a new
     * landing, and keeps the original source even if the new link has another.
     *
     * @return void
     */
    public function test_returning_visitor_attributed_from_cookie(): void {
        global $DB;
        $session = new \stdClass();
        $cookies = [tracker::UTM_COOKIE => json_encode(['utm_source' => 'tiktok', 'evil' => 'x'])];
        tracker::capture(['utm_source' => 'google'], $cookies, $session);
        $this->assertSame(['utm_source' => 'tiktok'], $session->local_nit_analytics_utm);
        $this->assertSame(0, $DB->count_records(funnel::LANDING_TABLE));

        $broken = new \stdClass();
        tracker::capture([], [tracker::UTM_COOKIE => '{not json'], $broken);
        $this->assertObjectNotHasProperty('local_nit_analytics_utm', $broken, 'a bad cookie is ignored');
    }

    /**
     * A Google Ads auto-tagged visit (gclid / gbraid / wbraid, no UTM) counts as google / cpc;
     * explicit UTM wins over the click id; junk click ids are ignored.
     *
     * @return void
     */
    public function test_google_ads_click_id_counts_as_google_cpc(): void {
        global $DB;
        foreach (['gclid', 'gbraid', 'wbraid'] as $key) {
            $session = new \stdClass();
            tracker::capture([$key => 'Cj0KCQjw-abc_123XYZ'], [], $session);
            $this->assertSame(['utm_source' => 'google', 'utm_medium' => 'cpc'], $session->local_nit_analytics_utm, $key);
        }
        $this->assertSame(3, $DB->count_records(funnel::LANDING_TABLE, ['source' => 'google', 'medium' => 'cpc']));

        $tagged = new \stdClass();
        tracker::capture(['gclid' => 'Cj0KCQjw-abc_123XYZ', 'utm_source' => 'newsletter', 'utm_medium' => 'email'],
            [], $tagged);
        $this->assertSame('newsletter', $tagged->local_nit_analytics_utm['utm_source'], 'explicit UTM wins');

        $junk = new \stdClass();
        tracker::capture(['gclid' => '<script>'], [], $junk);
        $this->assertObjectNotHasProperty('local_nit_analytics_utm', $junk);
    }

    /**
     * The link builder makes clean UTM links to pages of this site only.
     *
     * @return void
     */
    public function test_campaign_url(): void {
        global $CFG;
        $url = tracker::campaign_url('/local/academy/course.php?id=3', 'Google', 'CPC', 'Oct English', 'Video A');
        $this->assertSame($CFG->wwwroot . '/local/academy/course.php?id=3&utm_source=google&utm_medium=cpc'
            . '&utm_campaign=oct_english&utm_content=video_a', $url);

        $this->assertSame($CFG->wwwroot . '/?utm_source=facebook&utm_medium=paid_social&utm_campaign=x',
            tracker::campaign_url('', 'facebook', 'paid social', 'x'));
        $this->assertStringStartsWith($CFG->wwwroot . '/?', tracker::campaign_url('https://evil.example/', 'a', 'b', 'c'),
            'external pages are not allowed');
        $this->assertSame('', tracker::campaign_url('/', 'google', '', 'oct'), 'medium is required');

        // A built link is captured as the same campaign.
        $session = new \stdClass();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        tracker::capture($params, [], $session);
        $this->assertSame('oct_english', $session->local_nit_analytics_utm['utm_campaign']);
    }

    /**
     * A self sign-up stores the session's UTM on the account and fires the conversion once.
     *
     * @return void
     */
    public function test_signup_records_attribution_and_pending_event(): void {
        global $SESSION;
        $SESSION->local_nit_analytics_utm = ['utm_source' => 'facebook', 'utm_campaign' => 'oct'];

        $user = $this->getDataGenerator()->create_user(); // Fires user_created; nobody is logged in.

        $this->assertSame('facebook', get_user_preferences('local_nit_analytics_utm_source', null, $user->id));
        $this->assertSame('oct', get_user_preferences('local_nit_analytics_utm_campaign', null, $user->id));
        $this->assertNull(get_user_preferences('local_nit_analytics_utm_medium', null, $user->id));
        $this->assertTrue(tracker::take_pending_signup((int) $user->id));
        $this->assertFalse(tracker::take_pending_signup((int) $user->id), 'the conversion fires only once');
    }

    /**
     * An account an admin creates is not a marketing sign-up.
     *
     * @return void
     */
    public function test_admin_created_account_is_not_a_signup(): void {
        global $SESSION;
        $SESSION->local_nit_analytics_utm = ['utm_source' => 'facebook'];
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(get_user_preferences('local_nit_analytics_utm_source', null, $user->id));
        $this->assertFalse(tracker::take_pending_signup((int) $user->id));
    }
}
