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
 * Notifications — token-authenticated JSON API for the mobile app (admin and
 * manager screens). Receiving notifications needs nothing here: they are Moodle
 * notifications (message_popup_get_popup_notifications, core_message_mark_notification_read,
 * and app push through airnotifier).
 *
 *   GET|POST /local/nit_notifications/api.php?function=<name>&token=<wstoken>&...
 *
 * Reads: get_send_options, count_recipients, get_notification_log, get_notification.
 * Writes (POST): send_notification.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_notifications\audience;
use local_nit_notifications\sender;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

/**
 * A log entry as the app sees it.
 *
 * @param stdClass $n a sender::log() item or a notification record
 * @return array
 */
function local_nit_notifications_api_notif(stdClass $n): array {
    global $DB;
    $sendername = $n->sendername ?? null;
    if ($sendername === null) {
        $sender = (int) $n->senderid ? core_user::get_user((int) $n->senderid) : null;
        $sendername = $sender ? fullname($sender) : '';
    }
    $coursename = $n->coursename ?? ((int) $n->courseid > 1 ? (string) $DB->get_field('course', 'fullname', ['id' => $n->courseid]) : '');
    return [
        'id' => (int) $n->id,
        'title' => $n->title,
        'body' => $n->body,
        'url' => (string) $n->url,
        'type' => $n->type,
        'source' => $n->source,
        'audience' => $n->audience,
        'courseid' => (int) $n->courseid,
        'coursename' => $coursename !== '' ? format_string($coursename) : '',
        'senderid' => (int) $n->senderid,
        'sendername' => $sendername,
        'email' => (bool) $n->email,
        'status' => $n->status,
        'total' => (int) $n->total,
        'sent' => (int) $n->sent,
        'failed' => (int) $n->failed,
        'read' => (int) ($n->readcount ?? (sender::read_counts([(int) $n->id])[(int) $n->id] ?? 0)),
        'timecreated' => (int) $n->timecreated,
    ];
}

/**
 * Fail unless the caller (a real user, not the shared visitor token) may send.
 */
function local_nit_notifications_api_require_sender(): void {
    if (api::is_shared_token() || !audience::can_send()) {
        api::fail('nopermissions', get_string('err_nopermission', 'local_academy'));
    }
}

api::run(function (string $function) use ($userid) {
    local_nit_notifications_api_require_sender();
    switch ($function) {
        // What the caller may choose on the compose screen.
        case 'get_send_options':
            $scope = audience::course_ids();
            $courses = [];
            if ($scope === null) {
                $rows = $GLOBALS['DB']->get_records_select('course', 'id <> ?', [SITEID], 'fullname', 'id, fullname');
            } else {
                $courses[] = ['id' => 0, 'name' => get_string('allmycourses', 'local_nit_notifications')];
                $rows = $scope ? $GLOBALS['DB']->get_records_list('course', 'id', $scope, 'fullname', 'id, fullname') : [];
            }
            foreach ($rows as $c) {
                $courses[] = ['id' => (int) $c->id,
                    'name' => format_string($c->fullname, true, ['context' => context_course::instance($c->id)])];
            }
            return [
                'sitewide' => $scope === null,
                'audiences' => array_map(fn($key) => ['key' => $key, 'label' => get_string('audience_' . $key,
                    'local_nit_notifications'), 'needscourse' => audience::is_course_audience($key)], audience::allowed()),
                'courses' => $courses,
                'types' => array_map(fn($t) => ['key' => $t, 'label' => get_string('type_' . $t, 'local_nit_notifications')],
                    sender::TYPES),
                'maxtitle' => sender::MAX_TITLE,
                'maxbody' => sender::MAX_BODY,
            ];

        // How many users a choice reaches (show before sending).
        case 'count_recipients':
            $aud = required_param('audience', PARAM_ALPHANUMEXT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            if ($err = audience::validate($aud, $courseid)) {
                throw new moodle_exception($err, 'local_nit_notifications');
            }
            return ['count' => audience::count($aud, audience::is_course_audience($aud) ? $courseid : 0, $userid)];

        // Send now.
        case 'send_notification':
            api::require_post();
            $notif = sender::send([
                'audience' => required_param('audience', PARAM_ALPHANUMEXT),
                'courseid' => optional_param('courseid', 0, PARAM_INT),
                'type' => optional_param('type', 'general', PARAM_ALPHA),
                'title' => required_param('title', PARAM_TEXT),
                'body' => required_param('body', PARAM_TEXT),
                'url' => optional_param('url', '', PARAM_RAW_TRIMMED),
                'email' => optional_param('email', 0, PARAM_BOOL),
            ]);
            return ['notification' => local_nit_notifications_api_notif($notif)];

        // The log, newest first. source = manual|auto, type, courseid, q.
        case 'get_notification_log':
            [$page, $perpage] = api::paging(30, 100);
            $list = sender::log([
                'type' => optional_param('type', '', PARAM_ALPHA),
                'source' => optional_param('source', '', PARAM_ALPHA),
                'courseid' => optional_param('courseid', 0, PARAM_INT),
                'q' => optional_param('q', '', PARAM_TEXT),
            ], $page, $perpage);
            return [
                'page' => $page,
                'perpage' => $perpage,
                'total' => $list['total'],
                'notifications' => array_map('local_nit_notifications_api_notif', $list['items']),
            ];

        // One notification with a page of its recipients.
        // state = sent|failed|queued|read|unread (empty = all).
        case 'get_notification':
            $notif = $GLOBALS['DB']->get_record('local_nit_notif', ['id' => required_param('id', PARAM_INT)]);
            if (!$notif || $notif->status === 'draft') {
                throw new moodle_exception('err_notificationnotfound', 'local_nit_notifications');
            }
            if (!sender::can_view($notif)) {
                return api::fail('nopermissions', get_string('err_nopermission', 'local_academy'));
            }
            [$page, $perpage] = api::paging(50, 200);
            $list = sender::recipients((int) $notif->id, optional_param('state', '', PARAM_ALPHA), $page, $perpage);
            $states = [sender::RCPT_QUEUED => 'queued', sender::RCPT_SENT => 'sent', sender::RCPT_FAILED => 'failed'];
            return [
                'notification' => local_nit_notifications_api_notif($notif),
                'page' => $page,
                'perpage' => $perpage,
                'total' => $list['total'],
                'recipients' => array_map(fn($r) => [
                    'userid' => (int) $r->userid,
                    'fullname' => $r->fullname,
                    'status' => $states[(int) $r->status] ?? 'queued',
                    'timesent' => (int) $r->timesent,
                    'timeread' => (int) $r->timeread,
                ], $list['items']),
            ];
    }
    return api::unknown();
});
