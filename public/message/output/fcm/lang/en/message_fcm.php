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
 * English language strings for message_fcm.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Firebase Cloud Messaging (FCM)';
$string['serviceaccountjson'] = 'Service Account key (JSON contents)';
$string['serviceaccountjson_desc'] = 'Open the key file downloaded from Firebase (Project settings → Service accounts → Generate new private key) in a text editor, copy everything and paste it here. When this is set, the file path below is ignored.';
$string['serviceaccountjson_invalid'] = 'This is not a valid Service Account key. Paste the whole JSON file, from the first to the last character.';
$string['serviceaccountfile'] ='Service Account JSON path';
$string['serviceaccountfile_desc'] = 'Path to the Firebase Service Account JSON credentials file. Can be absolute or relative to the Moodle installation root. Default: ../firebase-service-account.json';
$string['project_id'] = 'Firebase Project ID';
$string['project_id_desc'] = 'The Google Cloud / Firebase project ID. If left blank, it will be automatically extracted from the Service Account JSON file.';
$string['appid'] = 'Target App ID / Package Name filter';
$string['appid_desc'] = 'Optional. If specified (e.g. com.bassthalk.app), notifications will only be sent to devices registered with this App ID. Leave empty to send to all registered devices.';
$string['default_title'] = 'Default Notification Title';
$string['default_title_desc'] = 'Title used when a message or notification does not have an explicit subject or title.';
$string['notconfigured'] = 'Firebase Cloud Messaging is not configured. Please supply a valid Service Account JSON key file in the plugin settings.';
$string['configured'] = 'Firebase Cloud Messaging is configured and authenticated.';
$string['test_page_title'] = 'FCM Test & Diagnostics';
$string['test_push'] = 'Send Test Notification';
$string['test_push_desc'] = 'Send a test push notification to verify Firebase integration and device delivery.';
$string['test_user'] = 'Recipient User ID or Username';
$string['test_title'] = 'Notification Title';
$string['test_body'] = 'Notification Body';
$string['test_send_btn'] = 'Send Test Notification';
$string['test_success'] = 'Test notification sent successfully to {$a} device(s)!';
$string['test_no_devices'] = 'No registered devices found for the selected user. Make sure the user has logged in via the mobile app and registered their device token.';
$string['nodevices'] = 'No mobile devices registered for push notifications.';
$string['devicesregistered'] = 'Registered mobile devices:';
$string['privacy:metadata'] = 'The Firebase Cloud Messaging plugin sends notification content and device tokens to Google Firebase to deliver push notifications to mobile devices.';
