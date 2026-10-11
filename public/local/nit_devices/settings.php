<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin tree: ONE page, Site administration → Plugins → Local plugins → Device limit
 * (devices.php): the settings, every account's devices and the video protection check.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/nit_devices:manage', context_system::instance())) {
    $ADMIN->add('localplugins', new admin_externalpage('local_nit_devices',
        get_string('pluginname', 'local_nit_devices'),
        new moodle_url('/local/nit_devices/devices.php'),
        'local/nit_devices:manage'));
}

// Site administration → Local plugins: keep our pages in the agreed order.
if (class_exists('\local_academy\local\admin_order')) {
    \local_academy\local\admin_order::apply($ADMIN);
}
