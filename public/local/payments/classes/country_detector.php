<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class country_detector {

    /**
     * Detect user's country for pricing.
     *
     * IP geolocation is tried FIRST because it is server-determined and cannot be
     * self-selected by the buyer — this stops per-country pricing from being gamed
     * by editing the profile country field or passing a cheaper app_country. The
     * self-selectable sources are used only as a fallback when the IP cannot be
     * resolved (dev/localhost, private IP, or geolocation unavailable).
     *
     * Priority: 1. IP geolocation → 2. User profile → 3. Flutter app header → 4. Admin default
     */
    public static function detect(?int $userid = null, ?string $app_country = null, ?string $ip = null): string {
        global $USER;

        $userid = $userid ?? $USER->id;

        // 1. IP geolocation (trusted — not user-controlled).
        $ip = $ip ?? getremoteaddr();
        $ip_country = self::from_ip($ip);
        if (!empty($ip_country)) {
            return $ip_country;
        }

        // 2. User profile country (fallback when IP is unavailable).
        $profile_country = self::from_profile($userid);
        if (!empty($profile_country)) {
            return $profile_country;
        }

        // 3. Country provided by the Flutter app (fallback).
        if (!empty($app_country) && self::is_valid_country($app_country)) {
            return strtoupper($app_country);
        }

        // 4. Admin default.
        $default = get_config('local_payments', 'default_country');
        if (!empty($default)) {
            return strtoupper($default);
        }

        return 'EG';
    }

    private static function from_profile(int $userid): string {
        global $DB;
        $country = $DB->get_field('user', 'country', ['id' => $userid]);
        if (!empty($country) && self::is_valid_country($country)) {
            return strtoupper($country);
        }
        return '';
    }

    private static function from_ip(string $ip): string {
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            return '';
        }

        // Hash the IP into an alphanumeric cache key: raw IPs contain dots (and
        // colons for IPv6), which are rejected as invalid "simple keys".
        $key = md5($ip);

        $cache = \cache::make('local_payments', 'country_detection');
        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached;
        }

        global $CFG;
        require_once($CFG->dirroot . '/iplookup/lib.php');

        $location = iplookup_find_location($ip);
        if (!empty($location['country'])) {
            $country = strtoupper($location['country']);
            $cache->set($key, $country);
            return $country;
        }

        return '';
    }

    private static function is_valid_country(string $code): bool {
        return preg_match('/^[A-Za-z]{2}$/', $code) === 1;
    }
}
