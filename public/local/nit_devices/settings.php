<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin tree: the device limit settings (Local plugins), the devices page
 * (Users → Accounts) and the video protection check (Courses).
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_devices', get_string('pluginname', 'local_nit_devices'));

    $settings->add(new admin_setting_configcheckbox('local_nit_devices/enabled',
        get_string('enabled', 'local_nit_devices'), get_string('enabled_desc', 'local_nit_devices'), 0));

    $options = [];
    for ($i = 1; $i <= 10; $i++) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('local_nit_devices/maxdevices',
        get_string('maxdevices', 'local_nit_devices'), get_string('maxdevices_desc', 'local_nit_devices'), 2, $options));

    $settings->add(new admin_setting_configselect('local_nit_devices/policy',
        get_string('policy', 'local_nit_devices'), get_string('policy_desc', 'local_nit_devices'),
        \local_nit_devices\manager::POLICY_BLOCK, [
            \local_nit_devices\manager::POLICY_BLOCK => get_string('policyblock', 'local_nit_devices'),
            \local_nit_devices\manager::POLICY_REPLACE => get_string('policyreplace', 'local_nit_devices'),
        ]));

    $settings->add(new admin_setting_configcheckbox('local_nit_devices/allowlegacylogin',
        get_string('allowlegacylogin', 'local_nit_devices'), get_string('allowlegacylogin_desc', 'local_nit_devices'), 1));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('courses', new admin_externalpage('local_nit_devices_videos',
        get_string('videocheck', 'local_nit_devices'),
        new moodle_url('/local/nit_devices/videos.php')));
}

if ($hassiteconfig || has_capability('local/nit_devices:manage', context_system::instance())) {
    $ADMIN->add('accounts', new admin_externalpage('local_nit_devices_devices',
        get_string('devices', 'local_nit_devices'),
        new moodle_url('/local/nit_devices/devices.php'),
        'local/nit_devices:manage'));
}

// Site administration → Local plugins: keep our pages in the agreed order.
if (class_exists('\local_academy\local\admin_order')) {
    \local_academy\local\admin_order::apply($ADMIN);
}
