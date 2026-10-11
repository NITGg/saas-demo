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
 * Diagnostic and test page for Firebase Cloud Messaging (FCM).
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('messagesettingfcm');
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/message/output/fcm/test.php'));
$PAGE->set_title(get_string('test_page_title', 'message_fcm'));
$PAGE->set_heading(get_string('test_page_title', 'message_fcm'));

$action = optional_param('action', '', PARAM_ALPHA);
$notice = '';
$noticeType = \core\output\notification::NOTIFY_INFO;

if ($action === 'send' && confirm_sesskey()) {
    $targetUser = optional_param('target_user', '', PARAM_RAW_TRIMMED);
    $testTitle = optional_param('title', 'Bassthalk Test', PARAM_NOTAGS);
    $testBody = optional_param('body', 'This is a test notification from Bassthalk Moodle platform.', PARAM_NOTAGS);

    $recipient = null;
    if (is_numeric($targetUser)) {
        $recipient = $DB->get_record('user', ['id' => (int)$targetUser]);
    } else if (!empty($targetUser)) {
        $recipient = $DB->get_record('user', ['username' => $targetUser]);
    } else {
        $recipient = $USER;
    }

    if (!$recipient) {
        $notice = 'User not found.';
        $noticeType = \core\output\notification::NOTIFY_ERROR;
    } else {
        $devices = $DB->get_records('user_devices', ['userid' => $recipient->id]);
        if (empty($devices)) {
            $notice = get_string('test_no_devices', 'message_fcm') . ' (User: ' . s(fullname($recipient)) . ')';
            $noticeType = \core\output\notification::NOTIFY_WARNING;
        } else {
            $successCount = 0;
            $failCount = 0;
            $errors = [];

            foreach ($devices as $device) {
                $res = \message_fcm\fcm_client::send_to_device($device, $testTitle, $testBody, [
                    'type' => 'test',
                    'timestamp' => time(),
                ]);
                if (!empty($res['success'])) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = "Device #{$device->id} ({$device->platform}): " . ($res['response'] ?? 'Unknown error');
                }
            }

            if ($successCount > 0) {
                $notice = get_string('test_success', 'message_fcm', $successCount);
                if ($failCount > 0) {
                    $notice .= ' (Failed for ' . $failCount . ' device(s): ' . implode('; ', $errors) . ')';
                }
                $noticeType = \core\output\notification::NOTIFY_SUCCESS;
            } else {
                $notice = 'Failed to send to all devices: ' . implode('; ', $errors);
                $noticeType = \core\output\notification::NOTIFY_ERROR;
            }
        }
    }
}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('test_page_title', 'message_fcm'));

if (!empty($notice)) {
    echo $OUTPUT->render(new \core\output\notification($notice, $noticeType));
}

// Diagnostics Summary Card
$isConfigured = \message_fcm\fcm_client::is_configured();
$credPath = \message_fcm\fcm_client::get_credentials_path();
$projectId = \message_fcm\fcm_client::get_project_id();
$token = $isConfigured ? \message_fcm\fcm_client::get_access_token() : null;
$totalDevices = $DB->count_records('user_devices');

echo '<div class="card mb-4">';
echo '<div class="card-header font-weight-bold">Status & Credentials</div>';
echo '<div class="card-body">';
echo '<table class="table table-bordered mb-0">';
echo '<tr><th style="width:250px">Configured & Ready</th><td>' . ($isConfigured ? '<span class="badge badge-success bg-success text-white">YES</span>' : '<span class="badge badge-danger bg-danger text-white">NO</span>') . '</td></tr>';
echo '<tr><th>Project ID</th><td><code>' . s($projectId ?? 'Not Found') . '</code></td></tr>';
echo '<tr><th>Credentials File</th><td><code>' . s($credPath ?? 'Not Found') . '</code></td></tr>';
echo '<tr><th>Google OAuth2 Access Token</th><td>' . (!empty($token) ? '<span class="badge badge-success bg-success text-white">Valid & Cached</span>' : '<span class="badge badge-danger bg-danger text-white">Failed</span>') . '</td></tr>';
echo '<tr><th>Total Registered Devices (Site-wide)</th><td><strong>' . $totalDevices . '</strong></td></tr>';
echo '</table>';
echo '</div></div>';

// Test Notification Form
echo '<div class="card">';
echo '<div class="card-header font-weight-bold">' . get_string('test_push', 'message_fcm') . '</div>';
echo '<div class="card-body">';
echo '<form method="post" action="' . $PAGE->url->out(false) . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="send">';

echo '<div class="form-group row mb-3">';
echo '<label class="col-sm-3 col-form-label">' . get_string('test_user', 'message_fcm') . '</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" name="target_user" class="form-control" value="' . s($USER->username) . '" placeholder="Username or User ID (default: current user)">';
echo '<small class="form-text text-muted">Enter a username or user ID whose registered device will receive the test notification.</small>';
echo '</div></div>';

echo '<div class="form-group row mb-3">';
echo '<label class="col-sm-3 col-form-label">' . get_string('test_title', 'message_fcm') . '</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" name="title" class="form-control" value="Bassthalk Test">';
echo '</div></div>';

echo '<div class="form-group row mb-3">';
echo '<label class="col-sm-3 col-form-label">' . get_string('test_body', 'message_fcm') . '</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" name="body" class="form-control" value="اختبار إشعار فوري من منصة بسطتهالك">';
echo '</div></div>';

echo '<div class="form-group row mb-0">';
echo '<div class="col-sm-9 offset-sm-3">';
echo '<button type="submit" class="btn btn-primary">' . get_string('test_send_btn', 'message_fcm') . '</button> ';
echo '<a href="' . (new moodle_url('/admin/settings.php?section=messagesettingfcm'))->out() . '" class="btn btn-secondary">Settings</a>';
echo '</div></div>';

echo '</form>';
echo '</div></div>';

echo $OUTPUT->footer();
