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
 * Marketing analytics: consent, the GA4 / GTM / Meta Pixel snippets, and first-touch UTM attribution
 * that is kept on the account when a visitor signs up.
 *
 * Flow: a visitor lands with ?utm_source=… → capture() keeps the first set seen in the session and
 * logs the landing → with analytics consent the set is also kept in a 90-day first-party cookie, so
 * a visitor who returns later is still attributed → the user_created observer copies it to the new
 * user's preferences and marks a pending sign-up → the next page fires sign_up once. Purchases are
 * recorded by {@see conversions}. No tracker loads until the visitor accepts (setting requireconsent).
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tracker {
    /** @var string This plugin's component. */
    const COMPONENT = 'local_nit_analytics';

    /** @var string[] Query parameters captured on landing (stored as preference PREF_PREFIX . name). */
    const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /** @var string User preference prefix for attribution values. */
    const PREF_PREFIX = 'local_nit_analytics_';

    /** @var string User preference set at sign-up until the conversion event has been sent. */
    const PREF_PENDING = 'local_nit_analytics_signup_pending';

    /** @var string Cookie holding the visitor's analytics choice: granted | denied. */
    const CONSENT_COOKIE = 'nit_analytics_consent';

    /** @var string Cookie holding the first-touch UTM set (JSON), only with consent. */
    const UTM_COOKIE = 'nit_analytics_utm';

    /** @var int Lifetime of the attribution cookie, seconds. */
    const UTM_COOKIE_LIFETIME = 90 * DAYSECS;

    /** @var int Lifetime of the consent cookie, seconds. */
    const CONSENT_COOKIE_LIFETIME = 180 * DAYSECS;

    /** @var string[] Page layouts that never get tracking code or the banner. */
    const SKIP_LAYOUTS = ['maintenance', 'print', 'embedded', 'popup', 'redirect'];

    /** @var array<string,string> Setting => pattern a valid id must match. */
    const ID_PATTERNS = [
        'ga4_id'        => '/^G-[A-Z0-9]{4,20}$/',
        'gtm_id'        => '/^GTM-[A-Z0-9]{4,12}$/',
        'meta_pixel_id' => '/^[0-9]{6,20}$/',
    ];

    /**
     * A configured id, or '' when empty or malformed (a bad value is never printed into the page).
     *
     * @param string $setting key of ID_PATTERNS
     * @return string
     */
    public static function id(string $setting): string {
        $value = strtoupper(trim((string) get_config(self::COMPONENT, $setting)));
        return preg_match(self::ID_PATTERNS[$setting], $value) ? $value : '';
    }

    /**
     * Whether at least one tracker id is configured.
     *
     * @return bool
     */
    public static function configured(): bool {
        return self::id('ga4_id') !== '' || self::id('gtm_id') !== '' || self::id('meta_pixel_id') !== '';
    }

    /**
     * The visitor's analytics choice: 'granted', 'denied' or '' (not asked yet).
     *
     * @param array $cookies normally $_COOKIE
     * @return string
     */
    public static function consent_choice(array $cookies): string {
        $value = $cookies[self::CONSENT_COOKIE] ?? '';
        return in_array($value, ['granted', 'denied'], true) ? $value : '';
    }

    /**
     * Whether analytics may run for this visitor: consent given, or consent not required by the site.
     *
     * @param array $cookies normally $_COOKIE
     * @return bool
     */
    public static function consent_given(array $cookies): bool {
        return !get_config(self::COMPONENT, 'requireconsent') || self::consent_choice($cookies) === 'granted';
    }

    /**
     * Whether this page may show analytics at all (layout, admin exclusion, ids configured).
     *
     * @param string $pagelayout
     * @return bool
     */
    private static function page_eligible(string $pagelayout): bool {
        if (during_initial_install() || in_array($pagelayout, self::SKIP_LAYOUTS, true)) {
            return false;
        }
        if (is_siteadmin() && !get_config(self::COMPONENT, 'trackadmins')) {
            return false;
        }
        return self::configured();
    }

    /**
     * Whether the current page should carry tracking code.
     *
     * @param string $pagelayout
     * @param array $cookies normally $_COOKIE
     * @return bool
     */
    public static function should_track(string $pagelayout, array $cookies): bool {
        return self::page_eligible($pagelayout) && self::consent_given($cookies);
    }

    /**
     * Whether to ask for consent on this page: trackers configured, consent required, not answered.
     *
     * @param string $pagelayout
     * @param array $cookies normally $_COOKIE
     * @return bool
     */
    public static function should_ask_consent(string $pagelayout, array $cookies): bool {
        return self::page_eligible($pagelayout) && get_config(self::COMPONENT, 'requireconsent')
            && self::consent_choice($cookies) === '';
    }

    /**
     * Clean UTM values out of an array (query string or decoded cookie). Unknown keys are ignored;
     * values are cleaned and capped at 100 characters.
     *
     * @param array $params
     * @return array<string,string>
     */
    public static function clean_utm(array $params): array {
        $utm = [];
        foreach (self::UTM_KEYS as $key) {
            if (isset($params[$key]) && is_string($params[$key])) {
                $value = \core_text::substr(trim(clean_param($params[$key], PARAM_TEXT)), 0, 100);
                if ($value !== '') {
                    $utm[$key] = $value;
                }
            }
        }
        return $utm;
    }

    /**
     * Keep the first UTM set of the session (first touch). A fresh landing with UTM parameters is
     * logged for the funnel; without parameters, an earlier visit's attribution cookie is used.
     *
     * @param array $params request parameters (normally $_GET)
     * @param array $cookies normally $_COOKIE
     * @param \stdClass $session the Moodle $SESSION
     * @return void
     */
    public static function capture(array $params, array $cookies, \stdClass $session): void {
        if (!empty($session->local_nit_analytics_utm)) {
            return;
        }
        $cookie = json_decode((string) ($cookies[self::UTM_COOKIE] ?? ''), true);
        $fromcookie = is_array($cookie) ? self::clean_utm($cookie) : [];
        if ($fromcookie) {
            // An earlier visit already holds the first touch: keep it, do not count a new landing.
            $session->local_nit_analytics_utm = $fromcookie;
            return;
        }
        $utm = self::clean_utm($params);
        if ($utm) {
            $session->local_nit_analytics_utm = $utm;
            funnel::log_landing($utm, time());
        }
    }

    /**
     * Store the session's attribution on a new account and mark its sign-up conversion as pending.
     *
     * @param int $userid
     * @param \stdClass $session
     * @return void
     */
    public static function record_signup(int $userid, \stdClass $session): void {
        foreach ($session->local_nit_analytics_utm ?? [] as $key => $value) {
            set_user_preference(self::PREF_PREFIX . $key, $value, $userid);
        }
        set_user_preference(self::PREF_PENDING, 1, $userid);
    }

    /**
     * Read and clear the pending sign-up flag of a user (true once, then false).
     *
     * @param int $userid
     * @return bool
     */
    public static function take_pending_signup(int $userid): bool {
        if (!$userid || isguestuser($userid) || !get_user_preferences(self::PREF_PENDING, 0, $userid)) {
            return false;
        }
        unset_user_preference(self::PREF_PENDING, $userid);
        return true;
    }

    /**
     * Path for our cookies: the site's own path, so two sites on one domain do not share them.
     *
     * @return string
     */
    private static function cookie_path(): string {
        global $CFG;
        $path = (string) parse_url($CFG->wwwroot, PHP_URL_PATH);
        return rtrim($path, '/') . '/';
    }

    /**
     * JavaScript that sets a first-party cookie.
     *
     * @param string $name
     * @param string $value
     * @param int $lifetime seconds
     * @return string
     */
    private static function cookie_js(string $name, string $value, int $lifetime): string {
        global $CFG;
        $secure = strpos($CFG->wwwroot, 'https://') === 0 ? ';Secure' : '';
        return 'document.cookie=' . json_encode($name . '=') . '+encodeURIComponent(' . json_encode($value) . ')+'
            . json_encode(';path=' . self::cookie_path() . ';max-age=' . $lifetime . ';SameSite=Lax' . $secure) . ';';
    }

    /**
     * The <head> snippet for the configured trackers, plus pending conversion events.
     *
     * @param bool $signup fire the sign-up conversion
     * @param array[] $purchases pending purchases (see conversions::take_pending)
     * @param array $session_utm the session's UTM set, kept in the attribution cookie if not there yet
     * @param array $cookies normally $_COOKIE
     * @return string
     */
    public static function head_html(bool $signup, array $purchases = [], array $session_utm = [],
            array $cookies = []): string {
        $ga4 = self::id('ga4_id');
        $gtm = self::id('gtm_id');
        $pixel = self::id('meta_pixel_id');
        $html = '';

        if ($gtm !== '') {
            $html .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),"
                . "event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';"
                . "j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
                . "})(window,document,'script','dataLayer'," . json_encode($gtm) . ");</script>\n";
        }
        if ($ga4 !== '') {
            $html .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . s($ga4) . '"></script>' . "\n"
                . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
                . "gtag('js',new Date());gtag('config'," . json_encode($ga4) . ");</script>\n";
        }
        if ($pixel !== '') {
            $html .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?"
                . "n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;"
                . "n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];"
                . "s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');"
                . "fbq('init'," . json_encode($pixel) . ");fbq('track','PageView');</script>\n";
        }

        $events = [];
        if ($signup) {
            if ($gtm !== '') {
                $events[] = "window.dataLayer=window.dataLayer||[];dataLayer.push({event:'sign_up'});";
            }
            if ($ga4 !== '') {
                $events[] = "gtag('event','sign_up');";
            }
            if ($pixel !== '') {
                $events[] = "fbq('track','CompleteRegistration');";
            }
        }
        foreach ($purchases as $p) {
            $value = round(((int) $p['amount_minor']) / 100, 2);
            $data = json_encode(['transaction_id' => (string) $p['ref'], 'value' => $value,
                'currency' => (string) $p['currency']]);
            if ($gtm !== '') {
                $events[] = "window.dataLayer=window.dataLayer||[];dataLayer.push({event:'purchase',ecommerce:$data});";
            }
            if ($ga4 !== '') {
                $events[] = "gtag('event','purchase',$data);";
            }
            if ($pixel !== '') {
                $events[] = "fbq('track','Purchase'," . json_encode(['value' => $value,
                    'currency' => (string) $p['currency']]) . ',' . json_encode(['eventID' => (string) $p['ref']]) . ');';
            }
        }
        if ($session_utm && empty($cookies[self::UTM_COOKIE])) {
            $events[] = self::cookie_js(self::UTM_COOKIE, json_encode($session_utm), self::UTM_COOKIE_LIFETIME);
        }
        if ($events) {
            $html .= '<script>' . implode('', $events) . "</script>\n";
        }
        return $html;
    }

    /**
     * The consent banner (bottom of the page). Accept stores the choice and reloads so trackers
     * load; decline stores the choice and hides the banner.
     *
     * @return string
     */
    public static function consent_banner_html(): string {
        $text = s(get_string('consent_text', self::COMPONENT));
        $accept = s(get_string('consent_accept', self::COMPONENT));
        $decline = s(get_string('consent_decline', self::COMPONENT));
        $set = function (string $value): string {
            return s(self::cookie_js(self::CONSENT_COOKIE, $value, self::CONSENT_COOKIE_LIFETIME));
        };
        return '<div id="nit-analytics-consent" role="region" aria-label="' . $text . '" style="position:fixed;'
            . 'inset-inline:16px;bottom:16px;z-index:10000;max-width:560px;margin-inline:auto;background:#fff;color:#1d2125;'
            . 'border:1px solid #dee2e6;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.15);padding:14px 16px;'
            . 'display:flex;flex-wrap:wrap;gap:10px;align-items:center">'
            . '<p style="margin:0;flex:1 1 260px;font-size:14px">' . $text . '</p>'
            . '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="' . $set('denied')
            . "document.getElementById('nit-analytics-consent').remove();\">" . $decline . '</button>'
            . '<button type="button" class="btn btn-primary btn-sm" onclick="' . $set('granted')
            . 'location.reload();">' . $accept . '</button></div>' . "\n";
    }

    /**
     * The GTM <noscript> fallback for the top of <body>, or ''.
     *
     * @return string
     */
    public static function body_html(): string {
        $gtm = self::id('gtm_id');
        if ($gtm === '') {
            return '';
        }
        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . s($gtm) . '" height="0" width="0"'
            . ' style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    }
}
