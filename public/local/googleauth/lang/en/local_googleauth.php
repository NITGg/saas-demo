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
 * Google mobile authentication - language strings.
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Google mobile authentication';
$string['enabled'] = 'Enable Google mobile token endpoint';
$string['enabled_desc'] = 'Allow the mobile app to exchange a verified Google ID token for a Moodle web service token via /local/googleauth/token.php.';
$string['clientids'] = 'Allowed Google client IDs';
$string['clientids_desc'] = 'Comma-separated list of accepted OAuth client IDs (the "aud" claim of the Google ID token). Include the Web/server client ID you request the ID token with, plus any Android/iOS client IDs.';
$string['allowcreate'] = 'Auto-create users';
$string['allowcreate_desc'] = 'If enabled, a new Moodle account is created when no existing user matches the verified Google email. If disabled, unknown emails are rejected.';
$string['newuserauth'] = 'Auth method for new users';
$string['newuserauth_desc'] = 'Authentication plugin assigned to auto-created accounts (e.g. oauth2 or manual). Must be an enabled auth method.';
$string['restrictdomain'] = 'Restrict to email domains';
$string['restrictdomain_desc'] = 'Optional comma-separated list of allowed email domains (e.g. nitg-eg.com). Leave empty to allow any verified Google email.';
$string['allowedlinkauth'] = 'Linkable auth methods';
$string['allowedlinkauth_desc'] = 'Comma-separated list of authentication methods an existing account may be linked to via Google sign-in (e.g. oauth2). A Google login is rejected if the matching account uses an auth method not in this list — this prevents taking over a local password account that shares the same email. Defaults to oauth2.';
$string['ratelimit'] = 'Rate limit (requests/minute per IP)';
$string['ratelimit_desc'] = 'Maximum token requests accepted from a single IP address per minute. Excess requests get HTTP 429. Set to 0 to disable throttling. Default 20.';
$string['privacy:metadata'] = 'The Google mobile authentication plugin does not store personal data itself; it verifies Google ID tokens and issues web service tokens using core Moodle mechanisms.';
