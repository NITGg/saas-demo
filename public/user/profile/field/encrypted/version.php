<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Encrypted text profile field version information.
 *
 * @package    profilefield_encrypted
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version      = 2026101200;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires     = 2024100700;        // Requires Moodle 4.5+ (matches local_nit_core baseline).
$plugin->supported    = [405, 502];        // Supported branch range: 4.5 LTS .. 5.2.
$plugin->component    = 'profilefield_encrypted'; // Full name of the plugin (used for diagnostics).
$plugin->dependencies = [
    'local_nit_core' => ANY_VERSION,       // Provides \local_nit_core\helper\crypto (app-layer encryption).
];
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '1.0.0';
