<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Callbacks.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * "Devices" on a profile page: the user's own (while the limit is on), or any
 * user's for whoever manages devices.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user the profile's user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function local_nit_devices_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    $manage = has_capability('local/nit_devices:manage', context_system::instance());
    if (!$manage && !($iscurrentuser && \local_nit_devices\manager::enabled())) {
        return;
    }
    $url = new moodle_url('/local/nit_devices/devices.php', ['id' => $user->id]);
    $tree->add_node(new \core_user\output\myprofile\node('miscellaneous', 'local_nit_devices',
        get_string('devices', 'local_nit_devices'), null, $url));
}
