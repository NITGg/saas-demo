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
 * Settings configuration for message_fcm.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Status Banner
    $isConfigured = \message_fcm\fcm_client::is_configured();
    $resolvedPath = \message_fcm\fcm_client::get_credentials_path();
    $projectId = \message_fcm\fcm_client::get_project_id();

    if ($isConfigured) {
        $statusMsg = get_string('configured', 'message_fcm') .
            '<br><small class="text-muted">' .
            'Project ID: <code>' . s($projectId) . '</code> | File: <code>' . s($resolvedPath) . '</code>' .
            '</small>';
        $statusBanner = new \core\output\notification($statusMsg, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        $statusMsg = get_string('notconfigured', 'message_fcm');
        $statusBanner = new \core\output\notification($statusMsg, \core\output\notification::NOTIFY_ERROR);
    }

    $settings->add(new admin_setting_heading(
        'message_fcm/status_heading',
        '',
        $OUTPUT->render($statusBanner)
    ));

    // Service Account File Path
    $settings->add(new admin_setting_configtext(
        'message_fcm/serviceaccountfile',
        get_string('serviceaccountfile', 'message_fcm'),
        get_string('serviceaccountfile_desc', 'message_fcm'),
        '../firebase-service-account.json',
        PARAM_RAW_TRIMMED
    ));

    // Project ID
    $settings->add(new admin_setting_configtext(
        'message_fcm/project_id',
        get_string('project_id', 'message_fcm'),
        get_string('project_id_desc', 'message_fcm'),
        '',
        PARAM_ALPHANUMEXT
    ));

    // App ID Filter
    $settings->add(new admin_setting_configtext(
        'message_fcm/appid',
        get_string('appid', 'message_fcm'),
        get_string('appid_desc', 'message_fcm'),
        '',
        PARAM_NOTAGS
    ));

    // Default Notification Title
    $settings->add(new admin_setting_configtext(
        'message_fcm/default_title',
        get_string('default_title', 'message_fcm'),
        get_string('default_title_desc', 'message_fcm'),
        'Bassthalk',
        PARAM_NOTAGS
    ));

    // Test Diagnostics Page Link
    $testUrl = new moodle_url('/message/output/fcm/test.php');
    $testLink = html_writer::link($testUrl, '👉 ' . get_string('test_page_title', 'message_fcm'), ['class' => 'btn btn-outline-primary mt-2']);
    $settings->add(new admin_setting_heading(
        'message_fcm/test_heading',
        get_string('test_push', 'message_fcm'),
        $testLink
    ));
}
