<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices;

/**
 * The device limit: which devices an account is registered on, and what happens
 * when one more signs in.
 *
 * A device is one browser (a long-lived cookie) or one app install (the id the app
 * sends). Web and app devices share ONE limit ("maxdevices"). A device stays
 * registered until an admin removes it (or the "replace oldest" policy does), so
 * signing out of a phone does not free its place — otherwise an account could be
 * passed around by signing out and in.
 *
 * Removing a device ends it everywhere: a browser's Moodle session is destroyed,
 * an app's token is deleted (its next call answers 401 and the app signs out).
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string a browser */
    public const WEB = 'web';
    /** @var string an app install */
    public const MOBILE = 'mobile';
    /** @var string the one device of an app build that does not send its id (/login/token.php) */
    public const LEGACY_ID = 'legacy-app';
    /** @var string the browser cookie that names a web device */
    public const COOKIE = 'NITDEVICE';
    /** @var string refuse the new device */
    public const POLICY_BLOCK = 'block';
    /** @var string sign the oldest device out to make room */
    public const POLICY_REPLACE = 'replace';

    /** @var array<int, bool> per-request cache of "is a teacher somewhere" */
    private static array $teachers = [];

    /**
     * Is the device limit switched on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config('local_nit_devices', 'enabled');
    }

    /**
     * How many devices one account may use (web and app together).
     *
     * @return int at least 1
     */
    public static function max_devices(): int {
        return max(1, (int) (get_config('local_nit_devices', 'maxdevices') ?: 2));
    }

    /**
     * What happens when a device signs in on a full account.
     *
     * @return string POLICY_BLOCK or POLICY_REPLACE
     */
    public static function policy(): string {
        return get_config('local_nit_devices', 'policy') === self::POLICY_REPLACE ? self::POLICY_REPLACE : self::POLICY_BLOCK;
    }

    /**
     * May an app build that does not send its device id still sign in (/login/token.php,
     * Google sign-in without deviceid)? Switch off once every student has the new app.
     *
     * @return bool
     */
    public static function legacy_login_allowed(): bool {
        $value = get_config('local_nit_devices', 'allowlegacylogin');
        return $value === false || (bool) $value;
    }

    /**
     * Is the limit enforced for this account? Not for site admins, holders of
     * local/nit_devices:exempt (managers) or anyone teaching a course.
     *
     * @param int $userid
     * @return bool
     */
    public static function applies_to(int $userid): bool {
        if (!self::enabled() || $userid <= 0 || isguestuser($userid) || is_siteadmin($userid)) {
            return false;
        }
        if (has_capability('local/nit_devices:exempt', \context_system::instance(), $userid)) {
            return false;
        }
        return !self::is_teacher($userid);
    }

    /**
     * Does the user hold a teacher role (editing or not) anywhere?
     *
     * @param int $userid
     * @return bool
     */
    private static function is_teacher(int $userid): bool {
        global $DB;
        if (!isset(self::$teachers[$userid])) {
            self::$teachers[$userid] = $DB->record_exists_sql(
                "SELECT 1
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                  WHERE ra.userid = :userid AND r.archetype IN ('editingteacher', 'teacher')",
                ['userid' => $userid]);
        }
        return self::$teachers[$userid];
    }

    /**
     * The account's devices, the one used last first.
     *
     * @param int $userid
     * @return \stdClass[] keyed by id
     */
    public static function devices(int $userid): array {
        global $DB;
        return $DB->get_records('local_nit_devices', ['userid' => $userid], 'lastseen DESC, id DESC');
    }

    /**
     * Register a device on the account, or refresh it when it is already there.
     * On a full account the policy decides: refuse (exception) or sign the oldest
     * device out to make room. Nothing is recorded while the limit is switched off.
     *
     * @param int $userid
     * @param string $kind WEB or MOBILE
     * @param string $deviceid the cookie value / the app install id / LEGACY_ID
     * @param array $info optional name, platform, sid, tokenid
     * @return \stdClass|null the device, null when the limit is off
     * @throws device_limit_exception the account is full and the policy is "block"
     */
    public static function register(int $userid, string $kind, string $deviceid, array $info = []): ?\stdClass {
        global $DB;
        if (!self::enabled()) {
            return null;
        }
        $now = time();
        $fields = array_intersect_key($info, array_flip(['name', 'platform', 'sid', 'tokenid']));
        $fields['ip'] = (string) getremoteaddr('');
        $fields['lastseen'] = $now;
        if (isset($fields['name'])) {
            $fields['name'] = \core_text::substr(clean_param($fields['name'], PARAM_TEXT), 0, 255);
        }
        if (isset($fields['platform'])) {
            $fields['platform'] = \core_text::substr(clean_param($fields['platform'], PARAM_TEXT), 0, 50);
        }

        $device = $DB->get_record('local_nit_devices', ['userid' => $userid, 'kind' => $kind, 'deviceid' => $deviceid]);
        if ($device) {
            foreach ($fields as $name => $value) {
                $device->$name = $value;
            }
            $DB->update_record('local_nit_devices', $device);
            return $device;
        }

        if (self::applies_to($userid)) {
            $devices = self::devices($userid);
            while (count($devices) >= self::max_devices()) {
                if (self::policy() !== self::POLICY_REPLACE) {
                    throw new device_limit_exception(self::max_devices());
                }
                $oldest = array_pop($devices);
                self::revoke($oldest);
            }
        }

        $device = (object) ($fields + [
            'userid' => $userid,
            'kind' => $kind,
            'deviceid' => $deviceid,
            'name' => '',
            'platform' => '',
            'sid' => null,
            'tokenid' => null,
            'timecreated' => $now,
        ]);
        $device->id = $DB->insert_record('local_nit_devices', $device);
        return $device;
    }

    /**
     * Mint (or hand back) the app token of ONE device: unlike core, which gives every
     * phone of an account the same token, each app install gets its own, so one
     * device can be signed out without the others.
     *
     * @param \stdClass $user the signed-in user
     * @param \stdClass $service the external service record
     * @param string $deviceid the app install id
     * @param string $name the device name the app sends (e.g. "Samsung SM-A515F")
     * @param string $platform android / ios / …
     * @return \stdClass the external_tokens record
     * @throws device_limit_exception the account is full and the policy is "block"
     */
    public static function issue_token(\stdClass $user, \stdClass $service, string $deviceid, string $name = '',
            string $platform = ''): \stdClass {
        global $CFG, $DB;
        if (!self::enabled()) {
            // The limit is off: core's token, as /login/token.php gives it (the current user's).
            return \core_external\util::generate_token_for_current_user($service);
        }
        // Who may get a token at all: the same rule as core's generate_token_for_current_user().
        $system = \context_system::instance();
        $mayhave = $service->shortname === MOODLE_OFFICIAL_MOBILE_SERVICE
            ? has_capability('moodle/webservice:createmobiletoken', $system, $user->id)
            : (!is_siteadmin($user) && has_capability('moodle/webservice:createtoken', $system, $user->id));
        if (!$mayhave) {
            throw new \moodle_exception('cannotcreatetoken', 'webservice', '', $service->shortname);
        }

        $device = self::register((int) $user->id, self::MOBILE, $deviceid, ['name' => $name, 'platform' => $platform]);

        // The same install signing in again keeps its token while it is still valid.
        if ($device->tokenid) {
            $token = $DB->get_record('external_tokens', ['id' => $device->tokenid,
                'externalserviceid' => $service->id, 'userid' => $user->id]);
            if ($token && (empty($token->validuntil) || $token->validuntil > time())) {
                return $token;
            }
            $DB->delete_records('external_tokens', ['id' => $device->tokenid]);
        }

        $validuntil = empty($CFG->tokenduration) ? 0 : time() + (int) $CFG->tokenduration;
        $value = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, (int) $user->id,
            $system, $validuntil, '', \core_text::substr('device ' . ($name ?: $deviceid), 0, 255));
        $token = $DB->get_record('external_tokens', ['token' => $value], '*', MUST_EXIST);
        $DB->set_field('local_nit_devices', 'tokenid', $token->id, ['id' => $device->id]);
        return $token;
    }

    /**
     * Remove a device: its browser session ends, its app token is deleted.
     *
     * @param \stdClass $device
     */
    public static function revoke(\stdClass $device): void {
        global $DB;
        if (!empty($device->sid)) {
            \core\session\manager::destroy($device->sid);
        }
        if (!empty($device->tokenid)) {
            $DB->delete_records('external_tokens', ['id' => $device->tokenid]);
        }
        $DB->delete_records('local_nit_devices', ['id' => $device->id]);
    }

    /**
     * Remove every device of an account (the admin's "reset").
     *
     * @param int $userid
     * @return int how many were removed
     */
    public static function reset(int $userid): int {
        $devices = self::devices($userid);
        foreach ($devices as $device) {
            self::revoke($device);
        }
        return count($devices);
    }

    /**
     * "Chrome on Windows" / "Safari on iPhone" from a user agent.
     *
     * @param string $useragent
     * @return array{name:string, platform:string}
     */
    public static function describe_agent(string $useragent): array {
        $platforms = ['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows',
            'Mac OS X' => 'macOS', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'];
        // Order matters: Edge and Opera also say "Chrome", Chrome also says "Safari".
        $browsers = ['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox/' => 'Firefox',
            'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'];
        $platform = '';
        foreach ($platforms as $needle => $label) {
            if (strpos($useragent, $needle) !== false) {
                $platform = $label;
                break;
            }
        }
        $browser = '';
        foreach ($browsers as $needle => $label) {
            if (strpos($useragent, $needle) !== false) {
                $browser = $label;
                break;
            }
        }
        $name = trim($browser . ($browser && $platform ? ' — ' : '') . $platform);
        return ['name' => $name !== '' ? $name : 'Browser', 'platform' => $platform];
    }

    /**
     * Forget the per-request caches (tests).
     */
    public static function reset_caches(): void {
        self::$teachers = [];
    }
}
