<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Mobile JSON API for the device limit.
 *
 *   GET /local/nit_devices/api.php?function=get_my_devices&token=<wstoken>
 *   → {"status":"success","data":{"enabled","limited","max","policy","devices":[...]}}
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_devices\manager;

api::boot();
$user = api::authenticate();

api::run(function (string $function) use ($user, $DB) {
    switch ($function) {
        // The signed-in user's devices; `current` marks the one making this call.
        case 'get_my_devices':
            $tokenid = (int) $DB->get_field('external_tokens', 'id', ['token' => api::token()]);
            $devices = [];
            foreach (manager::devices((int) $user->id) as $device) {
                $devices[] = [
                    'id' => (int) $device->id,
                    'kind' => $device->kind,
                    'name' => $device->name,
                    'platform' => $device->platform,
                    'current' => $device->kind === manager::MOBILE && (int) $device->tokenid === $tokenid,
                    'firstseen' => (int) $device->timecreated,
                    'lastseen' => (int) $device->lastseen,
                ];
            }
            return [
                'enabled' => manager::enabled(),
                'limited' => manager::applies_to((int) $user->id),
                'max' => manager::max_devices(),
                'policy' => manager::policy(),
                'devices' => $devices,
            ];
    }
    return api::unknown();
});
