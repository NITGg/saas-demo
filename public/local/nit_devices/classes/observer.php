<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices;

/**
 * Event observers: the app side of the device limit.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * An app token is about to be handed out (core /login/token.php, Google
     * sign-in, registration). A token minted by manager::issue_token() is already
     * its device's. Any other comes from an app build without a device id: all of
     * that account's old-app phones count as ONE device ("legacy-app"), refused
     * like any new device when the account is full.
     *
     * Runs before the endpoint prints the token, so refusing here answers the
     * request with an error instead (an exception would only be logged by the
     * event manager).
     *
     * @param \core\event\webservice_token_sent $event
     */
    public static function token_sent(\core\event\webservice_token_sent $event): void {
        global $DB;
        if (!manager::enabled() || (CLI_SCRIPT && !PHPUNIT_TEST)) {
            return;
        }
        $token = $event->get_record_snapshot('external_tokens', $event->objectid);
        if (!$token || empty($token->userid)) {
            return;
        }

        $bound = $DB->get_record('local_nit_devices', ['tokenid' => $token->id]);
        if ($bound) {
            if ($bound->deviceid !== manager::LEGACY_ID && hook_callbacks::request_device_id() === '') {
                // Core handed an old-app phone the token of another (new-app) device.
                hook_callbacks::refuse('appupdaterequired', get_string('appupdaterequired', 'local_nit_devices'));
            }
            $DB->set_field('local_nit_devices', 'lastseen', time(), ['id' => $bound->id]);
            return;
        }

        try {
            manager::register((int) $token->userid, manager::MOBILE, manager::LEGACY_ID,
                ['name' => get_string('legacyapp', 'local_nit_devices'), 'tokenid' => (int) $token->id]);
        } catch (device_limit_exception $e) {
            $DB->delete_records('external_tokens', ['id' => $token->id]);
            hook_callbacks::refuse('devicelimit', $e->getMessage(), 200, ['maxdevices' => $e->max]);
        }
    }

    /**
     * A deleted account leaves no devices behind.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('local_nit_devices', ['userid' => $event->objectid]);
    }
}
