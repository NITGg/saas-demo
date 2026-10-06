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
 * Strings for local_nit_notifications.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'NIT Notifications';
$string['nit_notifications:send'] = 'Send notifications and read the notification log';
$string['messageprovider:announcement'] = 'Notifications from the platform administration';
$string['notifications'] = 'Notifications';
$string['sendnotification'] = 'Send a notification';
$string['notificationlog'] = 'Notification log';
$string['task_deliver'] = 'Deliver a notification to a large group';
$string['eventnotificationsent'] = 'Notification sent';

// Compose.
$string['audience'] = 'Send to';
$string['audience_course_students'] = 'Students of a course';
$string['audience_course_teachers'] = 'Teachers of a course';
$string['audience_all_students'] = 'All students';
$string['audience_all_teachers'] = 'All teachers';
$string['audience_managers'] = 'Managers';
$string['audience_admins'] = 'Administrators';
$string['audience_users'] = 'Selected users (automatic)';
$string['allmycourses'] = 'All the courses I manage';
$string['allcourses'] = 'All courses';
$string['type'] = 'Type';
$string['type_general'] = 'General';
$string['type_courses'] = 'Courses';
$string['type_subscriptions'] = 'Subscriptions';
$string['type_offers'] = 'Offers and coupons';
$string['title'] = 'Title';
$string['body'] = 'Text';
$string['link'] = 'Link (optional)';
$string['link_help'] = 'A page the notification opens when tapped, e.g. a course or an offer page.';
$string['alsoemail'] = 'Also send by email';
$string['next'] = 'Next';
$string['back'] = 'Back';
$string['confirmcount'] = 'This notification will be sent to {$a} user(s).';
$string['bigsendnote'] = 'A large group is delivered in batches in the background over the next minutes.';
$string['sendnow'] = 'Send now to {$a} user(s)';
$string['sentdone'] = 'Notification sent: {$a->sent} delivered, {$a->failed} failed.';
$string['sentqueued'] = 'Notification saved; it is being delivered to {$a} user(s) in the background.';
$string['scopednote'] = 'You can notify the students and teachers of the courses you manage.';

// Log.
$string['sender'] = 'Sender';
$string['system'] = 'System';
$string['source'] = 'Source';
$string['allsources'] = 'Manual and automatic';
$string['source_manual'] = 'Sent by hand';
$string['source_auto'] = 'Automatic';
$string['alltypes'] = 'All types';
$string['searchplaceholder'] = 'Title or text';
$string['nonotifications'] = 'No notifications yet.';
$string['norecipients'] = 'No recipients here.';
$string['recipients'] = 'Recipients';
$string['state_queued'] = 'Waiting';
$string['state_sent'] = 'Delivered';
$string['state_failed'] = 'Failed';
$string['state_read'] = 'Read';
$string['state_unread'] = 'Not read';
$string['timesent'] = 'Sent at';
$string['timeread'] = 'Read at';
$string['status_draft'] = 'Draft';
$string['status_queued'] = 'Waiting';
$string['status_sending'] = 'Sending';
$string['status_done'] = 'Sent';

// Errors (pages and mobile API).
$string['err_audience'] = 'You cannot send to this group.';
$string['err_course'] = 'You can only notify the courses you manage.';
$string['err_choosecourse'] = 'Choose a course.';
$string['err_type'] = 'Choose a notification type.';
$string['err_required'] = 'Required.';
$string['err_titletoolong'] = 'The title is too long (at most {$a} characters).';
$string['err_bodytoolong'] = 'The text is too long (at most {$a} characters).';
$string['err_url'] = 'Enter a full link starting with https://';
$string['err_norecipients'] = 'Nobody matches this group, so nothing was sent.';
$string['err_notificationnotfound'] = 'This notification was not found.';

// Privacy.
$string['privacy:metadata:local_nit_notif'] = 'Notifications a user sent to a group.';
$string['privacy:metadata:local_nit_notif:senderid'] = 'Who sent the notification.';
$string['privacy:metadata:local_nit_notif:title'] = 'The notification title.';
$string['privacy:metadata:local_nit_notif:body'] = 'The notification text.';
$string['privacy:metadata:local_nit_notif:timecreated'] = 'When it was sent.';
$string['privacy:metadata:local_nit_notif_rcpt'] = 'Who received each notification.';
$string['privacy:metadata:local_nit_notif_rcpt:userid'] = 'The recipient.';
$string['privacy:metadata:local_nit_notif_rcpt:status'] = 'Whether it was delivered.';
$string['privacy:metadata:local_nit_notif_rcpt:timesent'] = 'When it was delivered.';
$string['privacy:metadata:core_message'] = 'The notifications themselves are delivered as Moodle notifications.';
