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
$string['set_auto_complete_minutes'] = 'Auto-complete after (minutes)';
$string['set_auto_complete_minutes_desc'] = 'A started lesson that the student joined and the teacher did not mark completed is completed automatically this many minutes after its planned end: the Flex is used and the teacher is paid. Until then the student can still report the teacher absent. Lessons that never started, or that the student never joined, are left for support. 0 turns this off.';
$string['task_autocomplete'] = 'Auto-complete started lessons';
$string['lessonflex'] = 'Flex per lesson';
$string['lessonflex_help'] = 'How many Flex a student pays for one lesson with you. Lessons already booked keep the price they were booked at.';
$string['flexperlesson'] = '{$a} Flex per lesson';
$string['lessonflexcost'] = 'Price: {$a} Flex';
$string['err_badlessonflex'] = 'Flex per lesson must be a whole number, 1 or more.';
$string['err_notenoughflex'] = 'A lesson with this teacher costs {$a} Flex and you do not have enough. Buy a package first.';

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
$string['err_roomgone'] = 'The lesson room is no longer available. The teacher has to join the lesson again to open a new room.';

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

// Instant lessons.
$string['messageprovider:instantoffer'] = 'Instant lesson requests (a student wants a tutor now)';
$string['notif_instantoffer'] = 'A student wants a tutor now: {$a}';
$string['task_expireinstant'] = 'Expire instant lesson requests';
$string['set_instant_enabled'] = 'Instant lessons (1 = on, 0 = off)';
$string['set_instant_enabled_desc'] = 'Get a tutor now: students ask for a subject and the first online teacher who accepts starts the lesson at once.';
$string['set_instant_wait_seconds'] = 'Instant request waiting time (seconds)';
$string['set_instant_wait_seconds_desc'] = 'How long a student waits for an online teacher to accept before the request ends.';
$string['set_instant_minutes'] = 'Instant lesson length (minutes)';
$string['set_instant_minutes_desc'] = 'How long an instant lesson lasts.';
$string['err_instantoff'] = 'Instant lessons are switched off.';
$string['err_instantopen'] = 'You already have a request waiting for a tutor.';
$string['err_noinstanttutor'] = 'No tutor for this subject is online right now. Try again later or book a lesson.';
$string['err_instanttaken'] = 'Another tutor took this request, or it has ended.';
$string['err_instantbusy'] = 'Please try again in a moment.';
$string['err_instantnotbookable'] = 'Turn on bookings and add at least one subject in your teacher profile first (and be approved).';
$string['instant_title'] = 'Get a tutor now';
$string['instant_intro'] = 'Pick a subject and start a live lesson with the first available tutor, usually within a minute.';
$string['instant_noteph'] = 'What do you need help with? (optional)';
$string['instant_go'] = 'Find a tutor now';
$string['instant_online'] = '{$a} online';
$string['instant_nobodyonline'] = 'No tutor is online right now. Book a lesson below, or check back later.';
$string['instant_searching'] = 'Finding a tutor…';
$string['instant_asking'] = 'asking {$a} tutor(s)';
$string['instant_cancel'] = 'Cancel';
$string['instant_matched'] = 'A tutor accepted — opening the lesson…';
$string['instant_expired'] = 'No tutor accepted this time';
$string['instant_cancelled'] = 'Request cancelled';
$string['instant_expiredhelp'] = 'Nothing was charged. Try again, or book a lesson with a tutor below.';
$string['instant_again'] = 'Try again';
$string['instant_available'] = 'Available now for instant lessons';
$string['instant_availablehelp'] = 'While this is on and the site or app is open, students can ask you for a lesson right now. You will hear a sound and see a prompt to accept.';
$string['instant_prompttitle'] = 'A student wants a lesson now';
$string['instant_minutes'] = '{$a} min';
$string['instant_flex'] = '{$a} Flex';
$string['instant_accept'] = 'Accept & start';
$string['instant_decline'] = 'Decline';
$string['privacy:metadata:nit_instant_request'] = 'Requests for a tutor now.';
$string['privacy:metadata:nit_instant_request:studentid'] = 'The student asking.';
$string['privacy:metadata:nit_instant_request:subject'] = 'The subject asked for.';
$string['privacy:metadata:nit_instant_request:note'] = 'The note written with the request.';
$string['privacy:metadata:nit_instant_request:timecreated'] = 'When it was asked.';
$string['privacy:metadata:nit_instant_offer'] = 'Prompts sent to teachers for a request.';
$string['privacy:metadata:nit_instant_offer:teacherid'] = 'The teacher prompted.';
$string['privacy:metadata:nit_instant_offer:status'] = 'Whether they accepted, declined or missed it.';
$string['privacy:metadata:nit_instant_offer:timeanswered'] = 'When they answered.';
$string['privacy:metadata:nit_teacher_presence'] = 'Whether a teacher is online for instant lessons.';
$string['privacy:metadata:nit_teacher_presence:teacherid'] = 'The teacher.';
$string['privacy:metadata:nit_teacher_presence:lastseen'] = 'When their page or app last checked in.';
$string['set_instant_wave_size'] = 'Teachers asked at once (instant)';
$string['set_instant_wave_size_desc'] = 'How many online teachers get the prompt together. The best matches go first: the student\'s previous tutors, then rating, then how often they accept.';
$string['set_instant_ring_seconds'] = 'Ring time per wave (seconds)';
$string['set_instant_ring_seconds_desc'] = 'If none of them accepts in this time, the next teachers are asked.';
$string['set_instant_noshow_minutes'] = 'Teacher no-show after (minutes)';
$string['set_instant_noshow_minutes_desc'] = 'A teacher who accepted an instant lesson but did not open the room in this time is replaced: the Flex goes back to the student and the request is sent to other teachers once.';
$string['instant_noshow_retry'] = 'Your tutor did not join the {$a} lesson. Your Flex is back and we are finding you another tutor now.';
$string['instant_noshow_refund'] = 'Your tutor did not join the {$a} lesson. Your Flex is back; please try again.';
$string['instantlive'] = 'Instant lessons live';
$string['live_requests'] = 'Requests';
$string['live_matchrate'] = 'Matched';
$string['live_avgwait'] = 'Average time to match';
$string['live_noshows'] = 'Teacher no-shows';
$string['live_online'] = 'Teachers online';
$string['live_waiting'] = 'Requests waiting';
$string['live_last24'] = 'Figures cover the last 24 hours. The page refreshes every 15 seconds.';
$string['live_seconds'] = '{$a} s';
$string['live_secondsago'] = '{$a} s ago';
$string['live_student'] = 'Student';
$string['live_subject'] = 'Subject';
$string['live_subjects'] = 'Subjects';
$string['live_waited'] = 'Waiting';
$string['live_wave'] = 'Wave';
$string['live_ringing'] = 'Ringing';
$string['live_left'] = 'Time left';
$string['live_teacher'] = 'Teacher';
$string['live_state'] = 'State';
$string['live_busy'] = 'In a lesson';
$string['live_free'] = 'Free';
$string['live_acceptance'] = 'Accepts';
$string['live_seen'] = 'Last seen';
$string['live_bysubject'] = 'By subject';
$string['live_matched'] = 'Matched';
$string['live_unmatched'] = 'Nobody accepted';
$string['live_norequests'] = 'No student is waiting.';
$string['live_noteachers'] = 'No teacher is online.';
$string['live_nodata'] = 'No requests yet.';
$string['set_instant_extra_flex'] = 'Extra Flex for an instant lesson';
$string['set_instant_extra_flex_desc'] = 'Added to the teacher\'s price when a lesson starts at once through Get a tutor now (0 = same price).';
$string['err_instantphoto'] = 'The photo must be an image (JPG, PNG, WebP or GIF) of at most 5 MB.';
$string['instant_tutoronline'] = 'A tutor for {$a} is online now. Open Book a lesson to start an instant lesson.';
$string['instant_photo'] = 'Add a photo';
$string['instant_notifyme'] = 'Notify me when a tutor is online';
$string['instant_notifyset'] = 'Done — we will notify you when a tutor of this subject goes online.';
$string['instant_booklater'] = 'Book a lesson instead';
$string['privacy:metadata:nit_instant_watch'] = 'Students waiting to hear when a tutor of a subject is online.';
$string['privacy:metadata:nit_instant_watch:studentid'] = 'The student.';
$string['privacy:metadata:nit_instant_watch:subject'] = 'The subject.';
$string['privacy:metadata:nit_instant_watch:timecreated'] = 'When they asked.';
$string['privacy:metadata:core_files'] = 'Photos students attach to an instant-lesson request (deleted after 30 days).';
$string['set_instant_peak_extra_flex'] = 'Extra Flex in peak hours';
$string['set_instant_peak_extra_flex_desc'] = 'Added to instant lessons during the peak hours below. It raises the lesson value, so the teacher\'s share grows with it (a peak-hour bonus).';
$string['set_instant_quick_minutes'] = 'Quick question length (minutes)';
$string['set_instant_quick_minutes_desc'] = 'Students can ask a short quick question instead of a full lesson. 0 turns quick questions off.';
$string['set_instant_quick_flex'] = 'Quick question price (Flex)';
$string['set_instant_quick_flex_desc'] = 'Flat price of a quick question, whoever the teacher is (plus the peak-hour extra).';
$string['set_instant_trial_enabled'] = 'Trial lesson (1 = on, 0 = off)';
$string['set_instant_trial_enabled_desc'] = 'A student who never had a lesson gets their first instant lesson as a paid trial: the trial price below, and 100% of it goes to the academy (teacher share 0%).';
$string['set_instant_trial_flex'] = 'Trial lesson price (Flex)';
$string['set_instant_trial_flex_desc'] = 'What the trial lesson costs the student.';
$string['set_instant_ai_summary'] = 'AI summary for teachers (1 = on, 0 = off)';
$string['set_instant_ai_summary_desc'] = 'A one-line AI summary of the student\'s note shows in the teacher\'s prompt. Needs the AI assistant\'s key (local_nit_assistant).';
$string['set_instant_parent_alert'] = 'WhatsApp guardians when an instant lesson starts (1 = on, 0 = off)';
$string['set_instant_parent_alert_desc'] = 'Needs the academy WhatsApp number (AI assistant settings) and the template name below.';
$string['set_instant_peak_hours'] = 'Peak hours (server time)';
$string['set_instant_peak_hours_desc'] = 'Hours when the peak-hour extra applies, e.g. 18-22 or 8-10,18-22 (from the first hour up to the second). Empty = no peak hours.';
$string['set_instant_parent_template'] = 'WhatsApp template for guardians';
$string['set_instant_parent_template_desc'] = 'Name of an approved WhatsApp template with 4 parameters: {{1}} student first name, {{2}} subject, {{3}} teacher, {{4}} start time. Empty = no alert.';
$string['set_instant_parent_template_lang'] = 'Template language code';
$string['instant_modenormal'] = 'Full lesson ({$a} min)';
$string['instant_modequick'] = 'Quick question ({$a} min)';
$string['instant_trialnotice'] = 'Your first lesson is a trial: {$a} Flex.';
$string['instant_trialbadge'] = 'Trial lesson — paid to the academy';
$string['instant_quickbadge'] = 'Quick question';
$string['instant_peaknotice'] = 'Peak hours: instant lessons cost a little more now.';
$string['reliability'] = 'Answers {$a}% of instant requests';
$string['reliability_help'] = 'Share of instant lesson requests this tutor accepted and attended in the last 90 days.';
$string['export_heading'] = 'Download requests (CSV)';
$string['export_days'] = 'Last {$a} days';
$string['export_id'] = 'Request';
$string['export_created'] = 'Asked at';
$string['export_student'] = 'Student';
$string['export_subject'] = 'Subject';
$string['export_mode'] = 'Mode';
$string['export_trial'] = 'Trial';
$string['export_status'] = 'Status';
$string['export_waves'] = 'Waves';
$string['export_asked'] = 'Teachers asked';
$string['export_declined'] = 'Declined';
$string['export_missed'] = 'Missed';
$string['export_noshows'] = 'No-shows';
$string['export_teacher'] = 'Teacher';
$string['export_secondstomatch'] = 'Seconds to match';
$string['export_lessonid'] = 'Lesson';
$string['privacy:metadata:nit_instant_request:aisummary'] = 'The AI one-line summary of the note shown to teachers.';
$string['set_instant_target_seconds'] = 'Ask-this-tutor ring time (seconds)';
$string['set_instant_target_seconds_desc'] = 'When a student asks one tutor, only that tutor is asked, for this long.';
$string['set_instant_confirm_seconds'] = 'Student confirmation time (seconds)';
$string['set_instant_confirm_seconds_desc'] = 'When the student did not choose to start right away, they have this long to confirm the tutor who accepted.';
$string['set_instant_noshow_block'] = 'No-shows that suspend On call (in 30 days)';
$string['set_instant_noshow_block_desc'] = 'A tutor who accepted and did not join this many times in 30 days loses On call for a while. 0 = never.';
$string['set_instant_noshow_block_days'] = 'On call suspension (days)';
$string['set_instant_noshow_block_days_desc'] = 'How long On call stays off after repeated no-shows.';
$string['err_tutornotavailable'] = 'This tutor cannot take an instant lesson right now (offline, busy, or above your price).';
$string['err_oncallblocked'] = 'On call is suspended until {$a} after repeated no-shows.';
$string['instant_favouriteonline'] = '{$a} is online now — you can ask them for an instant lesson.';
$string['instant_usually'] = 'Usually online: {$a}';
$string['instant_norating'] = 'No ratings yet';
$string['instant_anyyear'] = 'Any year';
$string['instant_recenttitle'] = 'How was your lesson with {$a}?';
$string['instant_rate'] = 'Rate the tutor';
$string['instant_bookagain'] = 'Book again with this tutor';
$string['instant_favourite'] = 'Favourite';
$string['instant_year'] = 'School year';
$string['instant_subject'] = 'Subject';
$string['instant_picksubject'] = 'Choose a subject';
$string['instant_kind'] = 'Lesson';
$string['instant_maxprice'] = 'Max price (Flex)';
$string['instant_any'] = 'Any';
$string['instant_autostart'] = 'Start right away with the tutor who accepts (no confirmation step)';
$string['instant_askall'] = 'Ask all {$a} available tutors (fastest)';
$string['instant_notutors'] = 'No tutor matches these choices. Try another year, a higher max price, or book a lesson.';
$string['instant_availablenow'] = 'Available now';
$string['instant_inlesson'] = 'In a lesson';
$string['instant_offline'] = 'Offline';
$string['instant_askthis'] = 'Ask this tutor now';
$string['instant_profile'] = 'View profile';
$string['instant_reliability'] = 'answers {$a}%';
$string['instant_acceptedtitle'] = '{$a} accepted your request';
$string['instant_confirmin'] = 'Confirm within {$a} seconds, or we keep looking for another tutor.';
$string['instant_startlesson'] = 'Start lesson';
$string['instant_targetexpired'] = 'This tutor did not answer. Nothing was charged.';
$string['instant_askallinstead'] = 'Ask all available tutors instead';
$string['instant_askingone'] = 'Asking the tutor…';
$string['instant_oncall'] = 'On call: notify me of instant requests even when the site is closed';
$string['instant_oncallhelp'] = 'You get a push on your phone; open it to see the request. Suspended after repeated no-shows.';
$string['instant_waitingstudent'] = 'Accepted — waiting for the student to confirm…';
$string['privacy:metadata:nit_favourite_tutor'] = 'Tutors a student starred.';
$string['privacy:metadata:nit_favourite_tutor:studentid'] = 'The student.';
$string['privacy:metadata:nit_favourite_tutor:teacherid'] = 'The starred tutor.';
$string['privacy:metadata:nit_teacher_presence_log'] = 'When a tutor was online for instant lessons (for usually online).';
$string['privacy:metadata:nit_teacher_presence_log:teacherid'] = 'The tutor.';
$string['privacy:metadata:nit_teacher_presence_log:timestart'] = 'Went online.';
$string['privacy:metadata:nit_teacher_presence_log:timeend'] = 'Last seen online.';
$string['instant_onlinestatus'] = 'You are online — students can ask you now';
$string['instant_offlinestatus'] = 'You are offline';
$string['instant_joinnow'] = 'Join the lesson';
$string['instant_backtorequest'] = 'Open request';
$string['instant_close'] = 'Close';
$string['instant_matchedhere'] = 'Your lesson has started.';
