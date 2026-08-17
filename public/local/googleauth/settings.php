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
 * Google mobile authentication - admin settings.
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_googleauth', get_string('pluginname', 'local_googleauth'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_googleauth/enabled',
        get_string('enabled', 'local_googleauth'),
        get_string('enabled_desc', 'local_googleauth'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_googleauth/clientids',
        get_string('clientids', 'local_googleauth'),
        get_string('clientids_desc', 'local_googleauth'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_googleauth/allowcreate',
        get_string('allowcreate', 'local_googleauth'),
        get_string('allowcreate_desc', 'local_googleauth'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_googleauth/newuserauth',
        get_string('newuserauth', 'local_googleauth'),
        get_string('newuserauth_desc', 'local_googleauth'),
        'oauth2',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_googleauth/restrictdomain',
        get_string('restrictdomain', 'local_googleauth'),
        get_string('restrictdomain_desc', 'local_googleauth'),
        '',
        PARAM_RAW
    ));

    // Which auth methods an existing account may be linked to via Google SSO.
    $settings->add(new admin_setting_configtext(
        'local_googleauth/allowedlinkauth',
        get_string('allowedlinkauth', 'local_googleauth'),
        get_string('allowedlinkauth_desc', 'local_googleauth'),
        'oauth2',
        PARAM_RAW
    ));

    // Note: the CORS browser-origin allowlist is intentionally kept in code
    // (see token.php), not exposed as a setting, so it can't be misconfigured.

    // Per-IP request throttle (requests per minute; 0 disables).
    $settings->add(new admin_setting_configtext(
        'local_googleauth/ratelimit',
        get_string('ratelimit', 'local_googleauth'),
        get_string('ratelimit_desc', 'local_googleauth'),
        20,
        PARAM_INT
    ));
}
