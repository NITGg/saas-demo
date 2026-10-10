<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English strings.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Device limit';
$string['nit_devices:manage'] = 'See and remove the devices of any account';
$string['nit_devices:exempt'] = 'No device limit';

$string['enabled'] = 'Limit devices per account';
$string['enabled_desc'] = 'Each account may be used on a set number of devices (browsers and app installs together). Site admins, managers and course teachers have no limit.';
$string['maxdevices'] = 'Devices per account';
$string['maxdevices_desc'] = 'Browsers and app installs count together. A device stays registered until it is removed on the Devices page (signing out does not free its place).';
$string['policy'] = 'When a device too many signs in';
$string['policy_desc'] = 'Refuse it (the student asks support to remove a device), or sign the device used least recently out to make room.';
$string['policyblock'] = 'refuse the new device';
$string['policyreplace'] = 'sign the oldest device out';
$string['allowlegacylogin'] = 'Allow the old app version';
$string['allowlegacylogin_desc'] = 'App versions that do not send their device id sign in through /login/token.php, and all their phones count as ONE device. Untick once every student has the new app: the old app then gets "appupdaterequired".';

$string['devicelimit'] = 'This account is already used on {$a} devices, the most allowed. Ask the support team to remove a device you no longer use.';
$string['appupdaterequired'] = 'Please update the app to the latest version to sign in.';
$string['siteunavailable'] = 'The academy is not available right now.';
$string['legacyapp'] = 'Mobile app (old version)';

$string['blockedtitle'] = 'Too many devices';
$string['blockedbody'] = 'For your account\'s security, it can only be used on a limited number of devices. You were not signed in on this one.';
$string['backtologin'] = 'Back to sign-in';

$string['devices'] = 'Devices';
$string['mydevices'] = 'My devices';
$string['userdevices'] = 'Devices of {$a}';
$string['device'] = 'Device';
$string['kind'] = 'Type';
$string['kind_web'] = 'Browser';
$string['kind_mobile'] = 'Mobile app';
$string['firstseen'] = 'First used';
$string['lastseen'] = 'Last used';
$string['ip'] = 'IP address';
$string['nodevices'] = 'No devices yet.';
$string['remove'] = 'Remove';
$string['removeconfirm'] = 'Remove this device? It is signed out at once, and its place is freed.';
$string['removed'] = 'Device removed and signed out.';
$string['resetall'] = 'Remove all devices';
$string['resetconfirm'] = 'Remove every device of this account? They are all signed out at once.';
$string['resetdone'] = '{$a} device(s) removed.';
$string['searchuser'] = 'Find an account';
$string['searchplaceholder'] = 'Name, email, username or phone';
$string['nousersfound'] = 'No account found.';
$string['limitinfo'] = 'Up to {$a->max} devices (browsers and app together). One more: {$a->policy}.';
$string['limitoff'] = 'The device limit is switched off (Site administration → Plugins → Local plugins → Device limit).';
$string['exemptinfo'] = 'This account has no device limit (admin, manager or teacher).';

$string['videocheck'] = 'Video protection check';
$string['videocheck_desc'] = 'Only VdoCipher lessons are protected with DRM (Widevine / FairPlay) and the viewer\'s watermark. Vimeo lessons are easier to download or screen-record. These paid courses still have Vimeo lessons; move them to VdoCipher.';
$string['videocheck_none'] = 'Every paid course uses protected (VdoCipher) video lessons only.';
$string['videocheck_found'] = '{$a} paid course(s) still have Vimeo lessons.';
$string['videocheck_course'] = 'Course';
$string['videocheck_vimeo'] = 'Vimeo lessons';
$string['videocheck_vdocipher'] = 'VdoCipher lessons';

$string['privacy:metadata:table'] = 'The devices (browsers and app installs) each account signed in from, for the device limit.';
$string['privacy:metadata:userid'] = 'The account.';
$string['privacy:metadata:kind'] = 'Browser or mobile app.';
$string['privacy:metadata:name'] = 'The browser and system, or the phone model the app sends.';
$string['privacy:metadata:platform'] = 'The operating system.';
$string['privacy:metadata:ip'] = 'The IP address of the latest use.';
$string['privacy:metadata:timecreated'] = 'When the device was first used.';
$string['privacy:metadata:lastseen'] = 'When the device was last used.';
