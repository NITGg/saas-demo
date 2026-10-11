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
 * Message processor class for Firebase Cloud Messaging (FCM).
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/message/output/lib.php');

/**
 * Message processor class for FCM.
 */
class message_output_fcm extends message_output {

    /**
     * Processes the message and sends push notifications to all recipient's mobile devices.
     *
     * @param stdClass $eventdata The event data submitted by the message sender.
     * @return bool True on success or handled error.
     */
    public function send_message($eventdata) {
        global $CFG, $DB;

        if (!empty($CFG->noemailever)) {
            debugging('$CFG->noemailever active, no FCM push notification sent.', DEBUG_MINIMAL);
            return true;
        }

        // Skip suspended or deleted users.
        if (!empty($eventdata->userto->suspended) ||
            !empty($eventdata->userto->deleted) ||
            ($eventdata->userto->auth ?? '') === 'nologin') {
            return true;
        }

        if (!$this->is_system_configured()) {
            debugging('message_fcm: Service is not configured. Skipping push notification.', DEBUG_DEVELOPER);
            return true;
        }

        $recipientId = (int)$eventdata->userto->id;
        if ($recipientId <= 0) {
            return true;
        }

        // Fetch registered devices for this recipient.
        $params = ['userid' => $recipientId];
        $sql = "SELECT * FROM {user_devices} WHERE userid = :userid AND pushid IS NOT NULL AND pushid <> ''";

        $appIdFilter = get_config('message_fcm', 'appid');
        if (!empty($appIdFilter)) {
            $sql .= " AND appid = :appid";
            $params['appid'] = trim($appIdFilter);
        }

        $devices = $DB->get_records_sql($sql, $params);
        if (empty($devices)) {
            return true;
        }

        // Prepare Title
        $title = '';
        if (!empty($eventdata->subject)) {
            $title = clean_param($eventdata->subject, PARAM_NOTAGS);
        }
        if (empty($title)) {
            $title = get_config('message_fcm', 'default_title');
        }
        if (empty($title)) {
            $site = get_site();
            $title = !empty($site->fullname) ? format_string($site->fullname) : 'Bassthalk';
        }
        $title = clean_param($title, PARAM_NOTAGS);

        // Prepare Body
        $body = '';
        if (!empty($eventdata->smallmessage)) {
            $body = clean_param($eventdata->smallmessage, PARAM_NOTAGS);
        } else if (!empty($eventdata->fullmessage)) {
            $body = clean_param($eventdata->fullmessage, PARAM_NOTAGS);
        } else if (!empty($eventdata->fullmessagehtml)) {
            $body = clean_param($eventdata->fullmessagehtml, PARAM_NOTAGS);
        }

        // Truncate if excessively long
        if (\core_text::strlen($body) > 1000) {
            $body = \core_text::substr($body, 0, 997) . '...';
        }

        // Custom data payload for mobile app routing/handling
        $customData = [
            'notification_id' => $eventdata->savedmessageid ?? 0,
            'eventtype'       => $eventdata->eventtype ?? 'message',
            'component'       => $eventdata->component ?? 'moodle',
            'contexturl'      => $eventdata->contexturl ?? '',
            'timecreated'     => $eventdata->timecreated ?? time(),
        ];

        if (!empty($eventdata->userfrom)) {
            $customData['userfromid'] = $eventdata->userfrom->id;
            $customData['userfromname'] = fullname($eventdata->userfrom);
        }

        if (!empty($eventdata->customdata)) {
            if (is_array($eventdata->customdata) || is_object($eventdata->customdata)) {
                $customData['customdata'] = json_encode($eventdata->customdata, JSON_UNESCAPED_UNICODE);
            } else {
                $customData['customdata'] = (string)$eventdata->customdata;
            }
        }

        // Send to each registered device
        foreach ($devices as $device) {
            \message_fcm\fcm_client::send_to_device($device, $title, $body, $customData);
        }

        return true;
    }

    /**
     * Tests whether the FCM settings are properly configured.
     *
     * @return bool
     */
    public function is_system_configured() {
        return \message_fcm\fcm_client::is_configured();
    }

    /**
     * Creates necessary fields in the messaging config form for the user preferences.
     *
     * @param array $preferences
     * @return string HTML output
     */
    public function config_form($preferences) {
        global $USER, $DB;

        if (!$this->is_system_configured()) {
            return get_string('notconfigured', 'message_fcm');
        }

        $devices = $DB->get_records('user_devices', ['userid' => $USER->id]);
        if (empty($devices)) {
            return get_string('nodevices', 'message_fcm');
        }

        $output = html_writer::tag('strong', get_string('devicesregistered', 'message_fcm')) . '<ul class="list-unstyled mt-2">';
        foreach ($devices as $device) {
            $name = s("{$device->name} ({$device->platform} {$device->version}) - {$device->model}");
            $output .= html_writer::tag('li', '📱 ' . $name);
        }
        $output .= '</ul>';

        return $output;
    }

    /**
     * Parses the submitted form data and saves it into preferences array.
     */
    public function process_form($form, &$preferences) {
        return true;
    }

    /**
     * Loads the config data from database to put on the form during initial form display.
     */
    public function load_data(&$preferences, $userid) {
        return true;
    }

    /**
     * Returns true if this processor has configurable message preferences.
     *
     * @return bool
     */
    public function has_message_preferences() {
        return true;
    }
}
