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
 * Plugin version and metadata for the NIT theme.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_nit';
$plugin->version   = 2026081600;        // YYYYMMDDXX.
$plugin->requires  = 2024100700;        // Moodle 4.5 LTS baseline (pinned per CI matrix).
$plugin->supported = [405, 502];        // Supported branch range: 4.5 LTS .. 5.2.
$plugin->maturity  = MATURITY_ALPHA;    // Foundation + rendering + branding (M2–M5); pre-1.0.
$plugin->release   = '0.3.0';
$plugin->dependencies = [
    'theme_boost'    => ANY_VERSION,    // NIT is a Boost child theme.
    'local_nit_core' => 2026080402,     // Renders SDK view-models &amp; consumes the branding resolver.
];
