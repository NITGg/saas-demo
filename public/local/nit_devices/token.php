<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * The app's sign-in with its device: /login/token.php plus the install's id.
 *
 *   POST /local/nit_devices/token.php
 *        username, password, service (moodle_mobile_app),
 *        deviceid (the install's id, the same for every sign-in of this install),
 *        devicename (e.g. "Samsung SM-A515F"), platform (android / ios)
 *   → {"token":"…","privatetoken":"…","deviceid":"…"}
 *   → {"error":"<readable>","errorcode":"devicelimit","maxdevices":2}   the account is full
 *   → {"error":"…","errorcode":"invalidlogin"} …                         as /login/token.php
 *
 * Unlike /login/token.php — which gives every phone of an account the same token —
 * each install gets its own token, so removing one device signs out that phone only.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('REQUIRE_CORRECT_ACCESS', true);
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

use local_nit_devices\device_limit_exception;
use local_nit_devices\hook_callbacks;
use local_nit_devices\manager;

header('Access-Control-Allow-Origin: *');

if (!$CFG->enablewebservices) {
    throw new moodle_exception('enablewsdescription', 'webservice');
}

$username = required_param('username', PARAM_USERNAME);
$password = required_param('password', PARAM_RAW);
$serviceshortname = optional_param('service', MOODLE_OFFICIAL_MOBILE_SERVICE, PARAM_ALPHANUMEXT);
$deviceid = hook_callbacks::request_device_id();
$devicename = optional_param('devicename', '', PARAM_TEXT);
$platform = optional_param('platform', '', PARAM_ALPHANUMEXT);
if ($deviceid === '') {
    throw new moodle_exception('missingparam', '', '', 'deviceid');
}

echo $OUTPUT->header();

$username = trim(core_text::strtolower($username));
if (is_restored_user($username)) {
    throw new moodle_exception('restoredaccountresetpassword', 'webservice');
}
$systemcontext = context_system::instance();

$reason = null;
$user = authenticate_user_login($username, $password, false, $reason, false);
if (empty($user)) {
    throw new moodle_exception('invalidlogin');
}

// The same gates as /login/token.php.
if (!empty($CFG->maintenance_enabled) && !has_capability('moodle/site:maintenanceaccess', $systemcontext, $user)) {
    throw new moodle_exception('sitemaintenance', 'admin');
}
if (isguestuser($user)) {
    throw new moodle_exception('noguest');
}
if (empty($user->confirmed)) {
    throw new moodle_exception('usernotconfirmed', 'moodle', '', $user->username);
}
$userauth = get_auth_plugin($user->auth);
if (!empty($userauth->config->expiration) && $userauth->config->expiration == 1
        && intval($userauth->password_expire($user->username)) < 0) {
    throw new moodle_exception('passwordisexpired', 'webservice');
}
// A suspended / expired academy (local_license) signs nobody in but site admins.
if (class_exists('\local_academy\api\endpoint') && \local_academy\api\endpoint::site_locked((int) $user->id)) {
    throw new moodle_exception('siteunavailable', 'local_nit_devices');
}

enrol_check_plugins($user);
\core\session\manager::set_user($user);

$service = $DB->get_record('external_services', ['shortname' => $serviceshortname, 'enabled' => 1]);
if (empty($service)) {
    throw new moodle_exception('servicenotavailable', 'webservice');
}

try {
    $token = manager::issue_token($user, $service, $deviceid, $devicename, $platform);
} catch (device_limit_exception $e) {
    hook_callbacks::refuse('devicelimit', $e->getMessage(), 200, ['maxdevices' => $e->max]);
}
\core_external\util::log_token_request($token);

$siteadmin = has_capability('moodle/site:config', $systemcontext, $USER->id);
echo json_encode([
    'token' => $token->token,
    // Private token: only over https and never for admins (as /login/token.php).
    'privatetoken' => (is_https() && !$siteadmin) ? $token->privatetoken : null,
    'deviceid' => $deviceid,
]);
