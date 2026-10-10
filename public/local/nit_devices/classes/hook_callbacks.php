<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices;

/**
 * Hook callbacks: the web side of the device limit.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var string the app's web-view sign-in: that is the phone, not a new browser */
    private const APP_AUTOLOGIN = '/admin/tool/mobile/autologin.php';

    /** @var int refresh a browser's "last seen" at most this often (seconds) */
    private const TOUCH_EVERY = 300;

    /**
     * A web sign-in: register this browser, or sign the user straight out again
     * (with an explanation page) when the account is full.
     *
     * @param \core_user\hook\after_login_completed $hook
     */
    public static function after_login_completed(\core_user\hook\after_login_completed $hook): void {
        global $USER, $SESSION, $SCRIPT;
        if (!manager::enabled() || WS_SERVER || CLI_SCRIPT || NO_MOODLE_COOKIES || during_initial_install()
                || \core\session\manager::is_loggedinas()) {
            return;
        }
        if ($SCRIPT === self::APP_AUTOLOGIN) {
            // The app opened a page in its web view: the phone is already a device.
            $SESSION->local_nit_devices_app = true;
            return;
        }

        $agent = manager::describe_agent((string) \core_useragent::get_user_agent_string());
        try {
            $device = manager::register((int) $USER->id, manager::WEB, self::browser_id(), $agent + ['sid' => session_id()]);
        } catch (device_limit_exception $e) {
            require_logout();
            redirect(new \moodle_url('/local/nit_devices/blocked.php'));
        }
        if ($device) {
            $SESSION->local_nit_devices_seen = time();
        }
    }

    /**
     * Every request: refuse the old app's sign-in when it is switched off, and keep
     * a signed-in browser's "last seen" fresh.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG, $SCRIPT, $USER, $SESSION, $DB;
        if (during_initial_install() || !empty($CFG->upgraderunning) || !manager::enabled()) {
            return;
        }

        // An app build that does not send its device id cannot be told apart from the
        // account's other phones (core hands them all the same token).
        if (!manager::legacy_login_allowed()
                && ($SCRIPT === '/login/token.php'
                    || ($SCRIPT === '/local/googleauth/token.php' && self::request_device_id() === ''))) {
            self::refuse('appupdaterequired', get_string('appupdaterequired', 'local_nit_devices'));
        }

        if (CLI_SCRIPT || WS_SERVER || NO_MOODLE_COOKIES || !isloggedin() || isguestuser()
                || !empty($SESSION->local_nit_devices_app)) {
            return;
        }
        $last = (int) ($SESSION->local_nit_devices_seen ?? 0);
        if (time() - $last < self::TOUCH_EVERY) {
            return;
        }
        $SESSION->local_nit_devices_seen = time();
        $DB->set_field_select('local_nit_devices', 'lastseen', time(),
            'userid = :userid AND kind = :kind AND sid = :sid',
            ['userid' => $USER->id, 'kind' => manager::WEB, 'sid' => session_id()]);
    }

    /**
     * This browser's device id: the cookie, set the first time.
     *
     * @return string
     */
    private static function browser_id(): string {
        global $CFG;
        $id = clean_param($_COOKIE[manager::COOKIE] ?? '', PARAM_ALPHANUM);
        if (strlen($id) !== 32) {
            $id = random_string(32);
        }
        // Refreshed on every sign-in, so it lives as long as the browser keeps signing in.
        if (!headers_sent()) {
            setcookie(manager::COOKIE, $id, [
                'expires' => time() + 2 * YEARSECS,
                'path' => empty($CFG->sessioncookiepath) ? '/' : $CFG->sessioncookiepath,
                'domain' => $CFG->sessioncookiedomain ?? '',
                'secure' => is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[manager::COOKIE] = $id;
        return $id;
    }

    /**
     * The device id the app sent with this request ('' when none).
     *
     * @return string
     */
    public static function request_device_id(): string {
        return \core_text::substr(clean_param($_REQUEST['deviceid'] ?? '', PARAM_ALPHANUMEXT), 0, 64);
    }

    /**
     * Answer a raw JSON sign-in endpoint with an error and stop
     * (`error` = readable text, `errorcode` = stable code, as /login/token.php does).
     *
     * @param string $errorcode
     * @param string $message
     * @param int $status HTTP status
     * @param array $extra more fields
     */
    public static function refuse(string $errorcode, string $message, int $status = 200, array $extra = []): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => $message, 'errorcode' => $errorcode] + $extra, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
