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
 * Marketing analytics: the GA4 / GTM / Meta Pixel snippets, and first-touch UTM attribution that is
 * kept on the account when a visitor signs up.
 *
 * Flow: a visitor lands with ?utm_source=… → capture() keeps the first set seen in the session →
 * the user_created observer copies it to the new user's preferences and marks a pending sign-up →
 * the next page the user sees fires the sign_up / CompleteRegistration event once.
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

    /** @var string[] Page layouts that never get tracking code. */
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
     * Whether the current page should carry tracking code.
     *
     * @param string $pagelayout
     * @return bool
     */
    public static function should_track(string $pagelayout): bool {
        if (during_initial_install() || in_array($pagelayout, self::SKIP_LAYOUTS, true)) {
            return false;
        }
        if (is_siteadmin() && !get_config(self::COMPONENT, 'trackadmins')) {
            return false;
        }
        return self::id('ga4_id') !== '' || self::id('gtm_id') !== '' || self::id('meta_pixel_id') !== '';
    }

    /**
     * Keep the first UTM set of the session (first touch). Values are trimmed to 100 characters.
     *
     * @param array $params request parameters (normally $_GET)
     * @param \stdClass $session the Moodle $SESSION
     * @return void
     */
    public static function capture(array $params, \stdClass $session): void {
        if (!empty($session->local_nit_analytics_utm)) {
            return;
        }
        $utm = [];
        foreach (self::UTM_KEYS as $key) {
            if (isset($params[$key]) && is_string($params[$key])) {
                $value = \core_text::substr(trim(clean_param($params[$key], PARAM_TEXT)), 0, 100);
                if ($value !== '') {
                    $utm[$key] = $value;
                }
            }
        }
        if ($utm) {
            $session->local_nit_analytics_utm = $utm;
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
     * The <head> snippet for the configured trackers, plus the sign-up conversion when $signup.
     *
     * @param bool $signup
     * @return string
     */
    public static function head_html(bool $signup): string {
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
        if ($signup) {
            $events = [];
            if ($gtm !== '') {
                $events[] = "window.dataLayer=window.dataLayer||[];dataLayer.push({event:'sign_up'});";
            }
            if ($ga4 !== '') {
                $events[] = "gtag('event','sign_up');";
            }
            if ($pixel !== '') {
                $events[] = "fbq('track','CompleteRegistration');";
            }
            if ($events) {
                $html .= '<script>' . implode('', $events) . "</script>\n";
            }
        }
        return $html;
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

    /**
     * Sign-ups per source / medium / campaign since a time, most first. Accounts without
     * attribution are grouped under empty strings (direct, or created by an admin).
     *
     * @param int $since unix time
     * @return array[] each {source, medium, campaign, signups}
     */
    public static function signups_by_source(int $since): array {
        global $DB, $CFG;
        $sql = "SELECT COALESCE(ps.value, '') AS source, COALESCE(pm.value, '') AS medium,
                       COALESCE(pc.value, '') AS campaign, COUNT(u.id) AS signups
                  FROM {user} u
             LEFT JOIN {user_preferences} ps ON ps.userid = u.id AND ps.name = :ps
             LEFT JOIN {user_preferences} pm ON pm.userid = u.id AND pm.name = :pm
             LEFT JOIN {user_preferences} pc ON pc.userid = u.id AND pc.name = :pc
                 WHERE u.deleted = 0 AND u.id <> :guest AND u.timecreated >= :since
              GROUP BY COALESCE(ps.value, ''), COALESCE(pm.value, ''), COALESCE(pc.value, '')
              ORDER BY COUNT(u.id) DESC, COALESCE(ps.value, '') ASC";
        $rows = $DB->get_recordset_sql($sql, [
            'ps' => self::PREF_PREFIX . 'utm_source',
            'pm' => self::PREF_PREFIX . 'utm_medium',
            'pc' => self::PREF_PREFIX . 'utm_campaign',
            'guest' => (int) $CFG->siteguest,
            'since' => $since,
        ]);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'source' => (string) $row->source,
                'medium' => (string) $row->medium,
                'campaign' => (string) $row->campaign,
                'signups' => (int) $row->signups,
            ];
        }
        $rows->close();
        return $out;
    }
}
