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
 * Tracker snippets, UTM capture and sign-up attribution.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_analytics\tracker
 * @covers     \local_nit_analytics\observer
 */
final class tracker_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * Nothing configured: no tracking; each id adds only its own snippet.
     *
     * @return void
     */
    public function test_snippets_follow_configuration(): void {
        $this->setGuestUser();
        $this->assertFalse(tracker::should_track('standard'));
        $this->assertSame('', tracker::head_html(true));

        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        $this->assertTrue(tracker::should_track('standard'));
        $this->assertFalse(tracker::should_track('popup'), 'popups are never tracked');
        $html = tracker::head_html(false);
        $this->assertStringContainsString('gtag/js?id=G-TEST1234', $html);
        $this->assertStringNotContainsString('fbq(', $html);

        set_config('meta_pixel_id', '9876543210', 'local_nit_analytics');
        set_config('gtm_id', 'GTM-ABCD12', 'local_nit_analytics');
        $html = tracker::head_html(false);
        $this->assertStringContainsString("fbq('init',\"9876543210\")", $html);
        $this->assertStringContainsString('"GTM-ABCD12"', $html);
        $this->assertStringContainsString('ns.html?id=GTM-ABCD12', tracker::body_html());
    }

    /**
     * Admins are not tracked unless the setting says so.
     *
     * @return void
     */
    public function test_admins_skipped_by_default(): void {
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        $this->setAdminUser();
        $this->assertFalse(tracker::should_track('standard'));
        set_config('trackadmins', 1, 'local_nit_analytics');
        $this->assertTrue(tracker::should_track('standard'));
    }

    /**
     * The sign-up event is printed once, for each configured tracker.
     *
     * @return void
     */
    public function test_signup_event_snippet(): void {
        set_config('ga4_id', 'G-TEST1234', 'local_nit_analytics');
        set_config('meta_pixel_id', '9876543210', 'local_nit_analytics');
        $html = tracker::head_html(true);
        $this->assertStringContainsString("gtag('event','sign_up')", $html);
        $this->assertStringContainsString("fbq('track','CompleteRegistration')", $html);
        $this->assertStringNotContainsString('sign_up', tracker::head_html(false));
    }

    /**
     * First touch wins: a later visit with other UTM values in the same session changes nothing.
     * Unknown parameters are ignored and values are cleaned and capped.
     *
     * @return void
     */
    public function test_capture_keeps_first_touch(): void {
        $session = new \stdClass();
        tracker::capture(['id' => 5], $session);
        $this->assertObjectNotHasProperty('local_nit_analytics_utm', $session, 'no UTM, nothing kept');

        tracker::capture([
            'utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => str_repeat('x', 150),
            'utm_term' => '<b>english</b>', 'gclid' => 'abc',
        ], $session);
        tracker::capture(['utm_source' => 'google'], $session);

        $utm = $session->local_nit_analytics_utm;
        $this->assertSame('facebook', $utm['utm_source']);
        $this->assertSame('cpc', $utm['utm_medium']);
        $this->assertSame(100, \core_text::strlen($utm['utm_campaign']));
        $this->assertSame('english', $utm['utm_term']);
        $this->assertArrayNotHasKey('gclid', $utm);
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

    /**
     * The report counts recent sign-ups per source; old or unattributed accounts are separate.
     *
     * @return void
     */
    public function test_signups_by_source(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $session = new \stdClass();
        $session->local_nit_analytics_utm = ['utm_source' => 'facebook', 'utm_medium' => 'cpc'];
        foreach ([1, 2] as $unused) {
            tracker::record_signup((int) $gen->create_user()->id, $session);
        }
        $session->local_nit_analytics_utm = ['utm_source' => 'google'];
        $old = $gen->create_user();
        tracker::record_signup((int) $old->id, $session);
        $DB->set_field('user', 'timecreated', time() - 100 * DAYSECS, ['id' => $old->id]);

        $rows = tracker::signups_by_source(time() - 30 * DAYSECS);
        $bysource = array_column($rows, 'signups', 'source');

        $this->assertSame(2, $bysource['facebook']);
        $this->assertArrayNotHasKey('google', $bysource, 'sign-ups older than the period are left out');
        $this->assertSame('facebook', $rows[0]['source'], 'biggest source first');
        $this->assertSame('cpc', $rows[0]['medium']);
    }
}
