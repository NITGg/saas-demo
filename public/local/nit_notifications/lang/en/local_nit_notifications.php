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
$string['messageprovider:announcement_email'] = 'Notifications from the platform administration, by email (when the administration sends them by email)';
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
$string['alsoemail_help'] = 'The email goes only to users who turned on "Notifications from the platform administration, by email" in their notification preferences. The others get the notification in the bell and the app only.';
$string['next'] = 'Next';
$string['back'] = 'Back';
$string['confirmcount'] = 'This notification will be sent to {$a} user(s).';
$string['bigsendnote'] = 'A large group is delivered in batches in the background over the next minutes.';
$string['sendnow'] = 'Send now to {$a} user(s)';
$string['sentdone'] = 'Notification sent: {$a->sent} delivered, {$a->failed} failed.';
$string['sentqueued'] = 'Notification saved; it is being delivered to {$a} user(s) in the background.';
$string['scopednote'] = 'You can notify the students and teachers of the courses you manage.';
$string['langnote'] = 'Write the other languages too if you can: a user whose language is left empty gets the site language.';

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
$string['emailcopy'] = 'Email copy';
$string['email_sent'] = 'Email sent';
$string['email_failed'] = 'Email failed';
$string['email_bymoodle'] = 'Emailed (user preferences)';
$string['email_off'] = 'Email off (user preferences)';
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

// Automatic notifications.
$string['messageprovider:subscription'] = 'Your subscriptions and purchases (started, renewed, cancelled)';
$string['messageprovider:newquiz'] = 'A new quiz in your courses';
$string['messageprovider:sessionreminder'] = 'Reminders before live sessions and private lessons';
$string['task_sessionreminders'] = 'Send live session and private lesson reminders';
$string['autosettings'] = 'Automatic notifications';
$string['autosettings_desc'] = 'Notifications the platform sends by itself. They are written in every installed language (each user gets theirs), appear in the notification log as "Automatic", and each user can turn each kind off in their notification preferences.';
$string['auto_subscription'] = 'Subscriptions and purchases';
$string['auto_subscription_desc'] = 'Tell the student when a subscription starts, is renewed or is cancelled, and when a course purchase is cancelled or refunded. (The reminder before a subscription ends is set in the subscription settings.)';
$string['auto_newquiz'] = 'New quiz';
$string['auto_newquiz_desc'] = 'Tell the students of a course when a quiz becomes visible in it (once per quiz).';
$string['auto_sessionreminder'] = 'Live session and lesson reminders';
$string['auto_sessionreminder_desc'] = 'Remind the students and the teacher before a live session or a private lesson starts.';
$string['session_reminder_minutes'] = 'Reminder times (minutes before)';
$string['session_reminder_minutes_desc'] = 'Comma separated, e.g. 60,10 = one hour before and ten minutes before. A session that starts sooner than one of them gets a single reminder.';
$string['source_subscription_started'] = 'Subscription started';
$string['source_subscription_renewed'] = 'Subscription renewed';
$string['source_subscription_cancelled'] = 'Subscription cancelled';
$string['source_course_cancelled'] = 'Course purchase cancelled';
$string['source_course_refunded'] = 'Course purchase refunded';
$string['source_subscription_expiry'] = 'Subscription ending';
$string['source_flex_expiry'] = 'Lesson package ending';
$string['source_newquiz'] = 'New quiz';
$string['source_session_reminder'] = 'Live session reminder';
$string['source_lesson_reminder'] = 'Private lesson reminder';
$string['auto_substarted_title'] = 'Your subscription "{$a->plan}" is active';
$string['auto_substarted_body'] = 'Welcome! Your subscription "{$a->plan}" is now active until {$a->expires}. Its courses are open to you.';
$string['auto_subrenewed_title'] = 'Your subscription "{$a->plan}" was renewed';
$string['auto_subrenewed_body'] = 'Your subscription "{$a->plan}" was renewed and is active until {$a->expires}.';
$string['auto_subcancelled_title'] = 'Your subscription "{$a->plan}" was cancelled';
$string['auto_subcancelled_body'] = 'Your subscription "{$a->plan}" was cancelled by the platform administration, so its courses are no longer open to you. Contact us if you have a question.';
$string['auto_coursecancelled_title'] = 'Your purchase of "{$a->course}" was cancelled';
$string['auto_coursecancelled_body'] = 'Your purchase of the course "{$a->course}" was cancelled by the platform administration, so the course is no longer open to you.';
$string['auto_courserefunded_title'] = 'Your purchase of "{$a->course}" was refunded';
$string['auto_courserefunded_body'] = 'Your purchase of the course "{$a->course}" was refunded, so the course is no longer open to you.';
$string['auto_newquiz_title'] = 'New quiz: {$a->quiz}';
$string['auto_newquiz_body'] = 'A new quiz, "{$a->quiz}", was added to the course "{$a->course}".';
$string['auto_session_title'] = 'Reminder: "{$a->title}" starts in {$a->minutes} minutes';
$string['auto_session_body'] = 'The live session "{$a->title}" of the course "{$a->course}" starts in {$a->minutes} minutes.';
$string['auto_lesson_title'] = 'Reminder: your private lesson starts in {$a->minutes} minutes';
$string['auto_lesson_body'] = 'Your {$a->subject} lesson with {$a->with} starts in {$a->minutes} minutes.';
$string['offer_notify_title'] = 'New offer: {$a->name}';
$string['offer_notify_body'] = '{$a->name}: {$a->discount} off {$a->items}. {$a->dates}';
$string['coupon_notify_title'] = 'Discount coupon: {$a->code}';
$string['coupon_notify_body'] = 'Use the coupon {$a->code} for {$a->discount} off {$a->items}. {$a->dates}';
$string['notify_until'] = 'Until {$a}.';
$string['notify_allitems'] = 'all courses';
