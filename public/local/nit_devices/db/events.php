<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observers.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Every app token handed out (core /login/token.php, Google sign-in, registration)
    // belongs to a device: bind it, or refuse it when the account is full.
    [
        'eventname' => '\core\event\webservice_token_sent',
        'callback' => '\local_nit_devices\observer::token_sent',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_nit_devices\observer::user_deleted',
    ],
];
