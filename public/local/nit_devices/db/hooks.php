<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook callbacks.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // A web sign-in registers the browser as a device (or is refused).
        'hook' => \core_user\hook\after_login_completed::class,
        'callback' => [\local_nit_devices\hook_callbacks::class, 'after_login_completed'],
    ],
    [
        // Old-app sign-ins when they are switched off; the web device's "last seen".
        'hook' => \core\hook\after_config::class,
        'callback' => [\local_nit_devices\hook_callbacks::class, 'after_config'],
    ],
];
