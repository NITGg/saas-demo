<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * NIT devices — the device limit per account (web + mobile app) and its sessions.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_nit_devices';
$plugin->version   = 2026101000;
$plugin->requires  = 2024100700;
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
