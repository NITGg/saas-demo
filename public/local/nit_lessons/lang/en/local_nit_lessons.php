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
 * English strings for local_nit_lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Live lessons';
$string['local_nit_lessons:request'] = 'Request live lessons';
$string['local_nit_lessons:teach'] = 'Give live lessons';
$string['local_nit_lessons:managesettings'] = 'Manage live lessons and their settings';
$string['messageprovider:lessonupdate'] = 'Live lesson updates (requests, answers, start, cancellations)';

// Pages.
$string['studenthub'] = 'My lessons & Flex';
$string['mylessons'] = 'My live lessons';
$string['teacherprofile'] = 'Teacher profile';
$string['managelessons'] = 'Live lessons';
$string['notateacher'] = 'This page is for teachers.';

// Settings.
$string['lessonsettings'] = 'Live lesson settings';
$string['lessonscourse'] = 'Course for the lesson rooms';
$string['lessonscourse_desc'] = 'When a teacher starts a live lesson, a Jitsi room is created in this course, visible only to that teacher and student. Use a hidden course made for this (its students can still open their rooms).';
$string['set_min_booking_minutes'] = 'Book at least (minutes ahead)';
$string['set_min_booking_minutes_desc'] = 'How long before its start a lesson can still be requested or moved.';
$string['set_cancel_deadline_minutes'] = 'Free cancellation until (minutes before)';
$string['set_cancel_deadline_minutes_desc'] = 'A student who cancels earlier gets the Flex back; later, the Flex is used.';
$string['set_update_deadline_minutes'] = 'Reschedule until (minutes before)';
$string['set_update_deadline_minutes_desc'] = 'A new time can be asked for until this many minutes before the lesson.';
$string['set_start_allowed_minutes'] = 'Start early (minutes before)';
$string['set_start_allowed_minutes_desc'] = 'How early the teacher can start the lesson.';
$string['set_complete_allowed_minutes'] = 'Complete after (minutes)';
$string['set_complete_allowed_minutes_desc'] = 'Minutes after the start before the teacher can mark the lesson completed.';
$string['set_absence_report_minutes'] = 'Report absence after (minutes)';
$string['set_absence_report_minutes_desc'] = 'Minutes after the start time before either side can report the other absent.';

// Tabs and hub.
$string['tab_book'] = 'Book a lesson';
$string['tab_lessons'] = 'My lessons';
$string['tab_flexavailable'] = 'Available Flex packages';
$string['tab_packages'] = 'My Flex';
$string['tab_subavailable'] = 'Available subscriptions';
$string['tab_mysubs'] = 'My subscriptions';
$string['tab_wallet'] = 'My wallet';
$string['tab_earnings'] = 'My earnings';
$string['tab_teaching'] = 'Student requests';
$string['tabgroup_lessons'] = 'Lessons';
$string['tabgroup_flex'] = 'Flex';
$string['tabgroup_subs'] = 'Subscriptions';
$string['tabgroup_money'] = 'Wallet';
$string['tabgroup_moneyteacher'] = 'Wallet & earnings';
$string['watchrecording'] = 'Watch the recording';
$string['watchrecordings'] = 'Watch the recordings ({$a})';
$string['recordingsof'] = 'Lesson recordings: {$a}';
$string['norecordings'] = 'This lesson has no recordings yet.';
$string['flexavailable'] = 'Flex available';
$string['flexbooked'] = '{$a} booked for upcoming lessons';
$string['nopackage'] = 'You have no active package.';
$string['needflex'] = 'You need Flex to book a lesson.';
$string['searchsubject'] = 'Search by subject';
$string['requestlesson'] = 'Request a lesson';
$string['requestwith'] = 'Lesson with {$a}';
$string['noteachers'] = 'No teacher takes bookings yet.';
$string['nolessons'] = 'No lessons here yet.';
$string['nolessons_teacher'] = 'No lessons here yet. Students\' requests will show up here.';
$string['nolessons_admin'] = 'No live lessons yet.';
$string['mypackages'] = 'My packages';
$string['flexusage'] = '{$a->remaining} left · {$a->reserved} booked · {$a->used} used / {$a->total}';
$string['activated'] = 'Started';
$string['paymenthistory'] = 'Payments';
$string['reference'] = 'Reference';
$string['nopayments'] = 'No payments yet.';
$string['flexhistory'] = 'Flex history';
$string['type'] = 'Type';
$string['change'] = 'Change';
$string['lesson'] = 'Lesson';
$string['lessonnum'] = 'Lesson #{$a}';
$string['lessongroup'] = 'Live lesson #{$a}';
$string['noflexhistory'] = 'No Flex movements yet.';
$string['subdays'] = '{$a} days';
$string['subscribe'] = 'Subscribe';
$string['subalreadyactive'] = 'You already have an active subscription.';
$string['subnoonline'] = 'Online payment is not available right now.';
$string['nosubs'] = 'No subscriptions are available right now.';
$string['subscription'] = 'Subscription';
$string['daysleft'] = 'Days left';
$string['nomysubs'] = 'You have no subscriptions yet.';
$string['subpayments'] = 'Subscription payments';
$string['sstat_active'] = 'Active';
$string['sstat_expired'] = 'Ended';
$string['sstat_cancelled'] = 'Cancelled';
$string['sstat_pending'] = 'Pending';
$string['sstat_payment_failed'] = 'Payment failed';
$string['pay_completed'] = 'Paid';
$string['pay_pending'] = 'Pending';
$string['pay_failed'] = 'Failed';
$string['pay_cancelled'] = 'Cancelled';
$string['pay_refunded'] = 'Refunded';

// Lesson cards.
$string['withteacher'] = 'With {$a}';
$string['withstudent'] = 'Student: {$a}';
$string['lesson_at'] = 'On:';
$string['lesson_requestedfor'] = 'Requested for:';
$string['rejectreason'] = 'Reason:';
$string['cancelreason'] = 'Cancel reason:';
$string['suggestedtime'] = 'Suggested time: {$a}';
$string['reschedulepending'] = '{$a->who} asked to move the lesson to {$a->time}.';
$string['you'] = 'You';
$string['theteacher'] = 'The teacher';
$string['thestudent'] = 'The student';
$string['lstat_pending'] = 'New request';
$string['lstat_waiting_student'] = 'Waiting for the student';
$string['lstat_waiting_teacher'] = 'Waiting for the teacher';
$string['lstat_confirmed'] = 'Confirmed';
$string['lstat_in_progress'] = 'Live now';
$string['lstat_completed'] = 'Completed';
$string['lstat_student_absent'] = 'Student absent';
$string['lstat_teacher_absent'] = 'Teacher absent';
$string['lstat_cancelled'] = 'Cancelled';
$string['lstat_cancelled_teacher'] = 'Cancelled by the teacher';
$string['lstat_rejected'] = 'Declined';
$string['allstatuses'] = 'All lessons';
$string['flexstate_reserved'] = 'Flex booked';
$string['flexstate_consumed'] = 'Flex used';
$string['flexstate_returned'] = 'Flex returned';
$string['act_accept'] = 'Accept';
$string['act_reject'] = 'Decline';
$string['act_suggest'] = 'Suggest another time';
$string['act_cancel_request'] = 'Withdraw request';
$string['act_cancel'] = 'Cancel lesson';
$string['act_teacher_absent'] = 'Teacher did not come';
$string['act_student_absent'] = 'Student did not come';
$string['act_reschedule'] = 'Change time';
$string['act_start'] = 'Start lesson';
$string['act_complete'] = 'Mark completed';
$string['act_join'] = 'Join lesson';
$string['acceptnewtime'] = 'Accept new time';
$string['rejectnewtime'] = 'Keep the old time';
$string['confirm_teacher_absent'] = 'Report that the teacher did not come? Your Flex comes back to you.';
$string['confirm_student_absent'] = 'Report that the student did not come? The student\'s Flex is used.';
$string['cancel_early'] = 'You are cancelling in time: your Flex comes back to you.';
$string['cancel_late'] = 'It is too late for a free cancellation: the Flex will be used.';

// Dialog.
$string['field_subject'] = 'Subject';
$string['field_note'] = 'What do you want to study? (required)';
$string['field_completenote'] = 'Note about the lesson (optional)';
$string['field_reason'] = 'Reason (required)';
$string['field_reasonoptional'] = 'Reason (optional)';
$string['pickday'] = 'Day';
$string['picktime'] = 'Time (one hour)';
$string['noslots'] = 'No free times in the next two weeks.';
$string['picktimefirst'] = 'Choose a time first.';

// Results.
$string['done_requested'] = 'Request sent. You will be told when the teacher answers.';
$string['done_saved'] = 'Done.';
$string['done_started'] = 'The lesson started. Join the room now.';
$string['done_completed'] = 'The lesson is completed and your earning was added.';
$string['done_cancelled'] = 'The lesson was cancelled.';
$string['done_reschedule'] = 'Your new time was sent to the other side.';

// Teacher profile.
$string['teacherprofile_intro'] = 'Students book live 1:1 lessons with you using Flex. Choose your subjects and the hours you can teach; each lesson is one hour.';
$string['takebookings'] = 'Students can book live lessons with me';
$string['headline'] = 'Short headline';
$string['headline_ph'] = 'e.g. Physics teacher, 10 years of experience';
$string['subjects'] = 'Subjects';
$string['subject_ph'] = 'Subject, e.g. Physics';
$string['addsubject'] = 'Add subject';
$string['workinghours'] = 'Weekly hours';
$string['workinghours_help'] = 'Times are in {$a}. Without hours, students can book you from 08:00 to 20:00 every day.';
$string['to'] = 'to';
$string['addhours'] = 'Add hours';
$string['choosesubject'] = 'Choose a subject…';
$string['nosubjectoptions'] = 'No subjects are set yet. Ask the admin to add them in Live lesson settings → Subjects.';
$string['set_subjects'] = 'Subjects';
$string['set_subjects_desc'] = 'The subjects teachers choose from on their profile, one per line. Arabic and English: <code>&#123;mlang ar&#125;الفيزياء&#123;mlang&#125;&#123;mlang en&#125;Physics&#123;mlang&#125;</code>. Leave it empty to offer each teacher the names of their own courses.';
$string['profilesaved'] = 'Your teacher profile was saved.';
$string['notbookable'] = 'Students cannot book you yet: turn on bookings and add at least one subject in your';
$string['day0'] = 'Sunday';
$string['day1'] = 'Monday';
$string['day2'] = 'Tuesday';
$string['day3'] = 'Wednesday';
$string['day4'] = 'Thursday';
$string['day5'] = 'Friday';
$string['day6'] = 'Saturday';

// Admin page.
$string['student'] = 'Student';
$string['teacher'] = 'Teacher';
$string['when'] = 'Time';
$string['earning'] = 'Earning';
$string['earningsplit'] = 'Teacher {$a->teacher} · Platform {$a->platform}';
$string['reverseflex'] = 'Return the Flex';
$string['confirmreverse'] = 'Return the Flex to the student and take the shares back from the teacher and the platform?';
$string['reversed'] = 'The Flex was returned to the student and the earning reversed.';
$string['roomnotconfigured'] = 'Live lesson rooms are not set up: choose the course for the lesson rooms in <a href="{$a}">Live lesson settings</a>.';

// Notifications.
$string['notif_requested'] = 'New lesson request: {$a->subject} from {$a->student}';
$string['notif_accepted_by_teacher'] = 'Your {$a->subject} lesson is confirmed';
$string['notif_rejected_by_teacher'] = 'Your {$a->subject} lesson request was declined';
$string['notif_suggested_by_teacher'] = '{$a->teacher} suggested another time for {$a->subject}';
$string['notif_accepted_by_student'] = '{$a->student} confirmed the {$a->subject} lesson';
$string['notif_rejected_by_student'] = '{$a->student} declined the suggested time for {$a->subject}';
$string['notif_suggested_by_student'] = '{$a->student} suggested another time for {$a->subject}';
$string['notif_started'] = 'Your {$a->subject} lesson has started — join now';
$string['notif_completed'] = 'Your {$a->subject} lesson is completed';
$string['notif_student_absent'] = 'You were marked absent from the {$a->subject} lesson';
$string['notif_teacher_absent'] = '{$a->student} reported you absent from the {$a->subject} lesson';
$string['notif_request_withdrawn'] = '{$a->student} withdrew the {$a->subject} lesson request';
$string['notif_cancelled_by_student'] = '{$a->student} cancelled the {$a->subject} lesson';
$string['notif_cancelled_by_teacher'] = '{$a->teacher} cancelled the {$a->subject} lesson; your Flex is back';
$string['notif_reschedule_requested'] = 'A new time was asked for the {$a->subject} lesson';
$string['notif_reschedule_answered'] = 'Your request to move the {$a->subject} lesson was answered';
$string['notif_details'] = 'Subject: {$a->subject} · Time: {$a->time}';

// Errors.
$string['err_subjectrequired'] = 'Choose a subject.';
$string['err_noterequired'] = 'Write what you want to study.';
$string['err_selfbooking'] = 'You cannot book a lesson with yourself.';
$string['err_teachernotfound'] = 'Teacher not found.';
$string['err_teachernotbookable'] = 'This teacher does not take bookings right now.';
$string['err_subjectnotoffered'] = 'This teacher does not teach that subject.';
$string['err_outsidehours'] = 'That time is outside the teacher\'s hours.';
$string['err_noflex'] = 'You have no Flex left. Buy a package first.';
$string['err_forbidden'] = 'You cannot do this on that lesson.';
$string['err_badstate'] = 'The lesson changed meanwhile. Refresh the page.';
$string['err_badaction'] = 'Unknown action.';
$string['err_tooearlytostart'] = 'It is too early to start this lesson.';
$string['err_reasonrequired'] = 'A reason is required.';
$string['err_updatedeadline'] = 'It is too late to change the time of this lesson.';
$string['err_updatepending'] = 'A time change is already waiting for an answer.';
$string['err_noupdaterequest'] = 'There is no time change to answer.';
$string['err_notime'] = 'Choose a time.';
$string['err_minbooking'] = 'That time is too soon. Choose a later time.';
$string['err_timeconflict'] = 'The teacher is not free at that time.';
$string['err_completetooearly'] = 'It is too early to mark this lesson completed.';
$string['err_absencetooearly'] = 'It is too early to report an absence.';
$string['err_lessonnotfound'] = 'Lesson not found.';
$string['err_settingnegative'] = 'Settings cannot be negative.';
$string['err_subjectsrequired'] = 'Add at least one subject to take bookings.';
$string['err_badhours'] = 'Each time range needs a day and a start and end at least one hour apart.';
$string['err_badsubject'] = 'Choose your subjects from the list.';
$string['err_nolessonscourse'] = 'Live lesson rooms are not set up yet. Ask the administrator.';
$string['err_roomnotready'] = 'The lesson room is not open yet. The teacher has to start the lesson.';

// Privacy.
$string['privacy:metadata:nit_lesson'] = 'Live lessons between a student and a teacher.';
$string['privacy:metadata:nit_lesson:studentid'] = 'The student.';
$string['privacy:metadata:nit_lesson:teacherid'] = 'The teacher.';
$string['privacy:metadata:nit_lesson:note'] = 'The note written with the request.';
$string['privacy:metadata:nit_lesson:timecreated'] = 'When the lesson was requested.';
$string['privacy:metadata:nit_lesson_proposal'] = 'Times suggested while agreeing on a lesson.';
$string['privacy:metadata:nit_lesson_proposal:proposedby'] = 'Who suggested the time.';
$string['privacy:metadata:nit_lesson_proposal:proposed_time'] = 'The suggested time.';
$string['privacy:metadata:nit_lesson_proposal:timecreated'] = 'When it was suggested.';
$string['err_notateacher'] = 'This is for teachers.';
