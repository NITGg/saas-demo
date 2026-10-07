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
 * Strings for local_nit_reports.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'NIT Reports';
$string['nit_reports:view'] = 'View the reports of the courses in scope';
$string['nit_reports:viewteaching'] = 'View the teaching reports of one\'s own courses';
$string['reports'] = 'Reports';
$string['currency_egp'] = 'EGP';
$string['minutes'] = '{$a} min';

// Reports.
$string['report_students'] = 'Students';
$string['report_teachers'] = 'Teachers';
$string['report_courses'] = 'Course performance';
$string['report_student_results'] = 'Student results';
$string['report_videos'] = 'Video watching';
$string['report_sales'] = 'Sales & revenue';
$string['report_subscriptions'] = 'Subscriptions';
$string['report_packages'] = 'Lesson packages';
$string['report_lessons'] = 'Live lessons & attendance';
$string['report_discounts'] = 'Coupons & offers';
$string['report_codes'] = 'Codes';
$string['report_teacher_dues'] = 'Teacher earnings & payouts';

// Filters and page.
$string['filter_from'] = 'From';
$string['filter_to'] = 'To';
$string['filter_user'] = 'User';
$string['filter_view'] = 'Show';
$string['filter_period'] = 'Period';
$string['filter_none'] = 'No filter';
$string['allcourses'] = 'All courses';
$string['allusers'] = 'All';
$string['allstatuses'] = 'All';
$string['clearfilters'] = 'Clear filters';
$string['export'] = 'Export';
$string['norows'] = 'Nothing to show for these filters.';
$string['nrows'] = '{$a} row(s)';
$string['generatedat'] = 'Generated {$a}';
$string['pdftruncated'] = 'Only the first {$a} rows are in this PDF; download Excel for every row.';
$string['search_students'] = 'Name, email or phone';
$string['search_teachers'] = 'Name or email';
$string['search_courses'] = 'Course name';
$string['search_student_results'] = 'Student name or email';
$string['search_videos'] = 'Video name';
$string['search_sales'] = 'Order, student or item';
$string['search_subscriptions'] = 'Student or plan';
$string['search_packages'] = 'Student or package';
$string['search_lessons'] = 'Student or session title';
$string['search_discounts'] = 'Coupon code or offer name';
$string['search_codes'] = 'Code or batch';
$string['search_teacher_dues'] = 'Teacher name';

// Views.
$string['view_transactions'] = 'Every payment';
$string['view_byitem'] = 'By item';
$string['view_byprovider'] = 'By payment method';
$string['view_bymonth'] = 'By month';
$string['view_subscriptions'] = 'Every subscription';
$string['view_byplan'] = 'By plan';
$string['view_packages'] = 'Every package';
$string['view_bystudent'] = 'By student';
$string['view_privatelessons'] = 'Private lessons';
$string['view_livesessions'] = 'Live sessions';
$string['view_bybatch'] = 'By batch';
$string['view_codes'] = 'Every code';

// Cards.
$string['card_students'] = 'Students';
$string['card_activeweek'] = 'Active in the last 7 days';
$string['card_withsubscription'] = 'With an active subscription';
$string['card_teachers'] = 'Teachers';
$string['card_earned'] = 'Earned in the period';
$string['card_paidout'] = 'Paid out in the period';
$string['card_courses'] = 'Courses';
$string['card_avgrating'] = 'Average rating';
$string['card_enrolments'] = 'Student enrolments';
$string['card_quizavg'] = 'Average quiz result';
$string['card_videos'] = 'Video lessons';
$string['card_viewers'] = 'Students who watched';
$string['card_avgwatched'] = 'Average watched';
$string['card_paidcount'] = 'Paid payments';
$string['card_revenue'] = 'Revenue';
$string['card_refunded'] = 'Refunded';
$string['card_failed'] = 'Failed / cancelled';
$string['card_codesales'] = 'Value of used codes';
$string['card_offlinepackages'] = 'Packages paid in cash or by transfer';
$string['card_assignedsubs'] = 'Value of subscriptions added by admin';
$string['card_activesubs'] = 'Active subscriptions now';
$string['card_endingweek'] = 'Ending within 7 days';
$string['card_subssold'] = 'Subscriptions in the period';
$string['card_packagessold'] = 'Packages';
$string['card_refundedvalue'] = 'Refunded to wallets';
$string['card_sessions'] = 'Sessions';
$string['card_lessons'] = 'Lessons';
$string['card_cancelled'] = 'Cancelled / rejected';
$string['card_attendance'] = 'Attendance rate';
$string['card_discounts'] = 'Coupons & offers';
$string['card_discountsales'] = 'Sales with a discount';
$string['chart_revenue'] = 'Revenue over time';

// Columns.
$string['col_registered'] = 'Registered';
$string['col_courses'] = 'Courses';
$string['col_subscription'] = 'Active subscription';
$string['col_lastaccess'] = 'Last access';
$string['col_completion'] = 'Completion';
$string['col_quizavg'] = 'Quiz average';
$string['col_videoavg'] = 'Video watched';
$string['col_students'] = 'Students';
$string['col_rating'] = 'Rating';
$string['col_earned'] = 'Earned';
$string['col_paidout'] = 'Paid out';
$string['col_teachers'] = 'Teachers';
$string['col_completed'] = 'Finished the course';
$string['col_sales'] = 'Sales';
$string['col_revenue'] = 'Revenue';
$string['col_quizzestaken'] = 'Quizzes taken';
$string['col_lastquiz'] = 'Last quiz';
$string['col_video'] = 'Video';
$string['col_provider'] = 'Provider';
$string['col_started'] = 'Started';
$string['col_finished'] = 'Finished (90%+)';
$string['col_avgwatched'] = 'Average watched';
$string['col_lastwatched'] = 'Last watched';
$string['col_order'] = 'Order';
$string['col_student'] = 'Student';
$string['col_itemtype'] = 'Type';
$string['col_item'] = 'Item';
$string['col_paymethod'] = 'Payment method';
$string['col_amount'] = 'Amount';
$string['col_originalamount'] = 'Before discount';
$string['col_coupon'] = 'Coupon';
$string['col_refunded'] = 'Refunded';
$string['col_month'] = 'Month';
$string['col_plan'] = 'Plan';
$string['col_price'] = 'Price';
$string['col_sold'] = 'Sold';
$string['col_renewals'] = 'Renewals';
$string['col_type'] = 'Type';
$string['col_pricepaid'] = 'Paid';
$string['col_source'] = 'Source';
$string['col_activated'] = 'Started';
$string['col_expires'] = 'Ends';
$string['col_renewal'] = 'Renewal';
$string['col_flexbought'] = 'Flex bought';
$string['col_flexused'] = 'Flex used';
$string['col_flexreserved'] = 'Flex held for booked lessons';
$string['col_flexleft'] = 'Flex left';
$string['col_flexexpired'] = 'Flex expired';
$string['col_packages'] = 'Packages';
$string['col_package'] = 'Package';
$string['col_session'] = 'Session';
$string['col_teacher'] = 'Teacher';
$string['col_start'] = 'Time';
$string['col_duration'] = 'Duration';
$string['col_invited'] = 'Invited';
$string['col_attended'] = 'Attended';
$string['col_teacherjoined'] = 'Teacher joined';
$string['col_subject'] = 'Subject';
$string['col_flexstate'] = 'Flex';
$string['col_actualminutes'] = 'Actual length';
$string['col_codeorname'] = 'Code / name';
$string['col_discount'] = 'Discount';
$string['col_validity'] = 'Valid';
$string['col_uses'] = 'Uses';
$string['col_users'] = 'Students';
$string['col_discountgiven'] = 'Discount given';
$string['col_code'] = 'Code';
$string['col_batch'] = 'Batch';
$string['col_value'] = 'Value';
$string['col_usedby'] = 'Used by';
$string['col_usedat'] = 'Used at';
$string['col_made'] = 'Codes made';
$string['col_valuemade'] = 'Value made';
$string['col_redeemed'] = 'Value redeemed';
$string['col_earnedactivities'] = 'From activities';
$string['col_earnedlessons'] = 'From private lessons';
$string['col_reversed'] = 'Taken back';
$string['col_requested'] = 'Requested, not paid yet';
$string['col_balance'] = 'Wallet balance now';

// Values.
$string['item_course'] = 'Course';
$string['item_subscription'] = 'Subscription';
$string['item_package'] = 'Lesson package';
$string['item_wallet_topup'] = 'Wallet top-up';
$string['item_activity'] = 'Lesson';
$string['item_other'] = 'Other';
$string['item_coupon'] = 'Coupon';
$string['item_offer'] = 'Offer';
$string['pay_completed'] = 'Completed';
$string['pay_pending'] = 'Pending';
$string['pay_failed'] = 'Failed';
$string['pay_cancelled'] = 'Cancelled';
$string['pay_expired'] = 'Expired';
$string['pay_timed_out'] = 'Timed out';
$string['pay_refunded'] = 'Refunded';
$string['pay_partially_refunded'] = 'Partly refunded';
$string['pay_voided'] = 'Voided';
$string['pay_chargeback'] = 'Chargeback';
$string['pay_duplicate'] = 'Duplicate';
$string['sub_active'] = 'Active';
$string['sub_ending'] = 'Ending within 7 days';
$string['sub_expired'] = 'Ended';
$string['sub_cancelled'] = 'Cancelled';
$string['sub_normal'] = 'Personal';
$string['sub_b2b'] = 'Group ({$a} seats)';
$string['source_online'] = 'Online';
$string['source_admin'] = 'Assigned by admin';
$string['pkg_active'] = 'Active';
$string['pkg_fully_used'] = 'Fully used';
$string['pkg_expired'] = 'Expired';
$string['pkg_cancelled'] = 'Cancelled';
$string['pkg_pending'] = 'Pending payment';
$string['lesson_pending'] = 'Requested';
$string['lesson_waiting_student'] = 'Waiting for the student';
$string['lesson_waiting_teacher'] = 'Waiting for the teacher';
$string['lesson_confirmed'] = 'Confirmed';
$string['lesson_in_progress'] = 'In progress';
$string['lesson_completed'] = 'Completed';
$string['lesson_student_absent'] = 'Student absent';
$string['lesson_teacher_absent'] = 'Teacher absent';
$string['lesson_cancelled'] = 'Cancelled by the student';
$string['lesson_cancelled_teacher'] = 'Cancelled by the teacher';
$string['lesson_rejected'] = 'Rejected';
$string['live_scheduled'] = 'Scheduled';
$string['live_live'] = 'Live now';
$string['live_ended'] = 'Ended';
$string['live_cancelled'] = 'Cancelled';
$string['flex_none'] = '—';
$string['flex_reserved'] = 'Held';
$string['flex_consumed'] = 'Used';
$string['flex_returned'] = 'Returned';
$string['code_active'] = 'Not used yet';
$string['code_used'] = 'Used';
$string['code_disabled'] = 'Disabled';
$string['code_expired'] = 'Expired';
$string['state_active'] = 'Active';
$string['state_inactive'] = 'Inactive';

// Added columns and values.
$string['col_leftcourses'] = 'Left courses';
$string['col_subends'] = 'Subscription ends';
$string['col_videosfinished'] = 'Videos finished';
$string['col_totalpaid'] = 'Total paid';
$string['col_wallet'] = 'Wallet';
$string['col_account'] = 'Account';
$string['col_activeweek'] = 'Active this week';
$string['col_leftstudents'] = 'Left the course';
$string['col_videocount'] = 'Videos';
$string['col_quizcount'] = 'Quizzes';
$string['col_refunds'] = 'Refunds';
$string['col_lessonsdone'] = 'Private lessons done';
$string['col_sessionsheld'] = 'Live sessions held';
$string['col_teacherabsences'] = 'Missed';
$string['col_paytype'] = 'Paid with';
$string['col_reference'] = 'Gateway reference';
$string['col_failreason'] = 'Failure reason';
$string['col_absent'] = 'Absent';
$string['col_avgminutes'] = 'Average stay';
$string['col_teacherlate'] = 'Teacher late';
$string['col_requested_at'] = 'Requested';
$string['col_reason'] = 'Reason';
$string['col_actualstart'] = 'Started';
$string['col_actualend'] = 'Ended';
$string['col_teachershare'] = 'Teacher share';
$string['col_operations'] = 'Earnings';
$string['col_soldat'] = 'Sold at';
$string['col_unpaidlessons'] = 'Not paid (student absent / late cancel)';
$string['col_teacherpercent'] = 'Teacher %';
$string['col_platformshare'] = 'Platform share';
$string['col_lastpaid'] = 'Last payout';
$string['member_current'] = 'Current students';
$string['member_left'] = 'Students who left';
$string['account_active'] = 'Active';
$string['account_suspended'] = 'Suspended';
$string['noend'] = 'No end';
$string['free'] = 'Free';
$string['ontime'] = 'On time';
$string['method_card'] = 'Card';
$string['method_wallet'] = 'Mobile wallet';
$string['method_fawry'] = 'Fawry';
$string['method_meeza'] = 'Meeza';
$string['method_valu'] = 'valU';
$string['method_aman'] = 'Aman';
$string['method_basata'] = 'Basata';
$string['method_souhoola'] = 'Souhoola';
$string['method_sympl'] = 'Sympl';
$string['method_bank_installments'] = 'Bank instalments';

// Table.
$string['perpage'] = 'Rows per page';
$string['showingrows'] = 'Showing {$a->first}–{$a->last} of {$a->total}';
$string['sortby'] = 'Sort by {$a}';
$string['sortedasc'] = 'Sorted ascending';
$string['sorteddesc'] = 'Sorted descending';
$string['columnmeanings'] = 'What the columns mean';

// What each column means (help_<report>_<view>_<column>, help_<report>_<column>, help_<column>).
$string['help_name'] = 'The person\'s full name.';
$string['help_email'] = 'The email address on the account.';
$string['help_phone'] = 'The mobile number on the account.';
$string['help_course'] = 'The course.';
$string['help_student'] = 'The student.';
$string['help_teacher'] = 'The teacher.';
$string['help_registered'] = 'The day the account was created.';
$string['help_lastaccess'] = 'The last time they used the website or the app.';
$string['help_rating'] = 'Average stars (1 to 5) of the approved reviews; the number of reviews is in brackets.';

$string['help_students_courses'] = 'Courses (in your scope) where they are a student now: enrolled, and the enrolment is not suspended or ended.';
$string['help_students_left'] = 'Courses (in your scope) they left: they were enrolled, paid, took a quiz or watched a video there, but are not a student there now (removed, suspended or the enrolment ended). Their data is kept and counts again if they come back.';
$string['help_students_subscription'] = 'The plan of their active subscription.';
$string['help_students_subends'] = 'When their active subscription ends.';
$string['help_students_completion'] = 'Average course completion over their courses: the share of the activities the teacher set to track that they finished. "—" when the courses do not track completion. When listing students who left, it covers the courses they left.';
$string['help_students_quiz'] = 'Average of their quiz grades (each as a percent of the quiz\'s full mark) in their courses. When listing students who left, it covers the courses they left.';
$string['help_students_video'] = 'Video lessons they finished (watched 90% or more), out of all the video lessons in their courses.';
$string['help_students_paid'] = 'Everything they paid online through the payment gateway (courses, subscriptions, packages, wallet top-ups). Fully refunded payments are not counted.';
$string['help_students_wallet'] = 'Their wallet balance now.';
$string['help_students_flex'] = 'Flex they can still use from their active lesson packages.';
$string['help_students_account'] = 'Active, or suspended by an admin (cannot sign in).';

$string['help_teachers_courses'] = 'Courses (in your scope) they teach now.';
$string['help_teachers_students'] = 'Current students across their courses, each student counted once.';
$string['help_teachers_video'] = 'Average share watched of the videos in their courses, by the current students, over the videos each student started.';
$string['help_teachers_lessons'] = 'Private lessons they completed in the period.';
$string['help_teachers_sessions'] = 'Live sessions in the period that ended with the teacher present.';
$string['help_teachers_absences'] = 'In the period: private lessons marked "teacher absent", plus live sessions that ended without the teacher joining.';
$string['help_teachers_earned'] = 'The teacher\'s share earned in the period (paid activities and private lessons), not counting what was taken back.';
$string['help_teachers_paid'] = 'Money transferred to the teacher in the period (withdrawal requests marked as paid).';
$string['help_teachers_wallet'] = 'Their wallet balance now: what they can still ask to withdraw.';
$string['help_teachers_pending'] = 'Withdrawal requests waiting to be approved or paid.';

$string['help_courses_category'] = 'The course category.';
$string['help_courses_teachers'] = 'The teachers of the course now.';
$string['help_courses_students'] = 'Students in the course now: enrolled, and the enrolment is not suspended or ended.';
$string['help_courses_active'] = 'Current students who opened the course in the last 7 days.';
$string['help_courses_left'] = 'Students who were in the course (enrolled, paid, took a quiz or watched a video) but are not now. They are left out of the other student figures.';
$string['help_courses_completed'] = 'Current students who completed the course, and their share. Needs completion tracking on the course, otherwise "—".';
$string['help_courses_quiz'] = 'Average quiz grade of the current students (each grade as a percent of the quiz\'s full mark).';
$string['help_courses_video'] = 'Average share watched by the current students, over the videos each one started.';
$string['help_courses_videos'] = 'Video lessons in the course.';
$string['help_courses_quizzes'] = 'Quizzes in the course.';
$string['help_courses_price'] = 'The prices the course was really bought at in the period: the amount and currency each student paid on the day they bought (after any discount), with how many times when more than once. A price change later does not change these.';
$string['help_courses_sales'] = 'Online purchases of the course paid in the period. Every payment counts, also by students who left or bought it again, so it can be more than the students.';
$string['help_courses_revenue'] = 'What students really paid in those purchases, at the price of the day they bought, in the currency they paid with (each currency on its own).';
$string['help_courses_refunds'] = 'Purchases of the course refunded in the period.';

$string['help_student_results_course'] = 'The course of this row.';
$string['help_student_results_quizzes'] = 'Quizzes they have a grade in, out of the quizzes in the course.';
$string['help_student_results_quiz'] = 'Their average quiz grade in the course (each grade as a percent of the quiz\'s full mark).';
$string['help_student_results_lastquiz'] = 'When they last finished a quiz attempt in the course.';
$string['help_student_results_completion'] = 'Their completion of the course: the share of the tracked activities they finished.';
$string['help_student_results_video'] = 'Average share watched of the videos they started in the course.';

$string['help_videos_video'] = 'The video lesson.';
$string['help_videos_provider'] = 'Where the video is hosted.';
$string['help_videos_students'] = 'Current students of the course.';
$string['help_videos_started'] = 'Current students who played any part of it, and their share of the students.';
$string['help_videos_finished'] = 'Current students who watched 90% or more of it.';
$string['help_videos_avg'] = 'Average share watched by the students who started it.';
$string['help_videos_last'] = 'The last time a student watched it.';

$string['help_sales_date'] = 'When the payment was started.';
$string['help_sales_order'] = 'Our order number.';
$string['help_sales_type'] = 'What was bought: a course, a subscription, a lesson package or a wallet top-up.';
$string['help_sales_item'] = 'The name of what was bought.';
$string['help_sales_provider'] = 'The payment gateway.';
$string['help_sales_method'] = 'How the student paid at the gateway: card, mobile wallet, Fawry…';
$string['help_sales_amount'] = 'What the student paid.';
$string['help_sales_original'] = 'The price before the discount; "—" when there was no discount.';
$string['help_sales_discount'] = 'How much the coupon and the offer took off.';
$string['help_sales_coupon'] = 'The coupon code the student used.';
$string['help_sales_offer'] = 'The offer applied automatically.';
$string['help_sales_status'] = 'The state of the payment: completed, pending, failed, refunded…';
$string['help_sales_reference'] = 'The gateway\'s number for this payment, to look it up in the gateway\'s dashboard.';
$string['help_sales_country'] = 'The country used to pick the price.';
$string['help_sales_reason'] = 'Why the payment failed or was cancelled, as the gateway reported it.';
$string['help_sales_count'] = 'Paid purchases in the period.';
$string['help_sales_revenue'] = 'Money from those purchases, per currency.';
$string['help_sales_refunded'] = 'Purchases refunded.';
$string['help_sales_month'] = 'The month.';

$string['help_subscriptions_plan'] = 'The subscription plan.';
$string['help_subscriptions_type'] = 'Personal, or a group subscription with a number of seats.';
$string['help_subscriptions_paid'] = 'The price paid.';
$string['help_subscriptions_source'] = 'Bought online, or assigned by an admin.';
$string['help_subscriptions_activated'] = 'When it started.';
$string['help_subscriptions_expires'] = 'When it ends.';
$string['help_subscriptions_status'] = 'Active, ending within 7 days, ended or cancelled.';
$string['help_subscriptions_renewal'] = '"Yes" when the student had the same plan before.';
$string['help_subscriptions_plan_price'] = 'The plan\'s price now.';
$string['help_subscriptions_plan_active'] = 'Subscriptions of the plan active now.';
$string['help_subscriptions_plan_sold'] = 'Subscriptions of the plan in the period.';
$string['help_subscriptions_plan_renewals'] = 'Of those, the renewals.';
$string['help_subscriptions_plan_revenue'] = 'Money paid for them.';

$string['help_packages_package'] = 'The lesson package.';
$string['help_packages_packages'] = 'Packages the student has.';
$string['help_packages_bought'] = 'Flex in the package (one Flex = one private lesson).';
$string['help_packages_used'] = 'Flex spent on lessons that took place.';
$string['help_packages_reserved'] = 'Flex held for lessons booked but not done yet.';
$string['help_packages_left'] = 'Flex the student can still use (active packages only).';
$string['help_packages_expired'] = 'Flex left when the package ended; it cannot be used any more.';
$string['help_packages_paid'] = 'The price paid.';
$string['help_packages_source'] = 'Bought online, or assigned by an admin.';
$string['help_packages_activated'] = 'When it started.';
$string['help_packages_expires'] = 'When it ends.';
$string['help_packages_status'] = 'Active, fully used, expired, cancelled or waiting for payment.';

$string['help_lessons_private_requested'] = 'When the student asked for the lesson.';
$string['help_lessons_private_time'] = 'The lesson time: the agreed time, or the asked time while not confirmed.';
$string['help_lessons_private_subject'] = 'What the lesson is about.';
$string['help_lessons_private_package'] = 'The lesson package the Flex came from.';
$string['help_lessons_private_duration'] = 'The planned length.';
$string['help_lessons_private_status'] = 'Requested, confirmed, completed, absent, cancelled or rejected.';
$string['help_lessons_private_reason'] = 'Why it was cancelled or rejected.';
$string['help_lessons_private_flex'] = 'What happened to the Flex: held, used or returned to the student.';
$string['help_lessons_private_actualstart'] = 'When the lesson really started.';
$string['help_lessons_private_actualend'] = 'When the lesson really ended.';
$string['help_lessons_private_actual'] = 'How long the lesson really took.';
$string['help_lessons_private_share'] = 'What the teacher earned from this lesson.';
$string['help_lessons_live_session'] = 'The session title.';
$string['help_lessons_live_start'] = 'When the session starts.';
$string['help_lessons_live_duration'] = 'The planned length.';
$string['help_lessons_live_status'] = 'Scheduled, live now, ended or cancelled.';
$string['help_lessons_live_invited'] = 'Students expected in the session.';
$string['help_lessons_live_attended'] = 'Students who joined, and their share of the expected ones.';
$string['help_lessons_live_absent'] = 'Expected students who never joined (ended sessions only).';
$string['help_lessons_live_avgminutes'] = 'How long the students who joined stayed, on average.';
$string['help_lessons_live_teacherjoined'] = 'When the teacher joined; "No" when they never did.';
$string['help_lessons_live_late'] = 'How long after the start time the teacher joined.';

$string['help_discounts_type'] = 'Coupon (a code the student types) or offer (applied automatically).';
$string['help_discounts_name'] = 'The coupon code or the offer name.';
$string['help_discounts_discount'] = 'How much it takes off: an amount or a percent.';
$string['help_discounts_state'] = 'Switched on or off.';
$string['help_discounts_dates'] = 'From when to when it can be used.';
$string['help_discounts_uses'] = 'Times it was used on paid purchases in the period.';
$string['help_discounts_users'] = 'Different students who used it.';
$string['help_discounts_given'] = 'Total discount given.';
$string['help_discounts_sales'] = 'What the students paid after the discount.';

$string['help_codes_code'] = 'The code.';
$string['help_codes_batch'] = 'The batch the code was made in.';
$string['help_codes_item'] = 'What the code opens: a course, a lesson, or credit in the wallet.';
$string['help_codes_value'] = 'The code\'s value.';
$string['help_codes_status'] = 'Not used, used, disabled or expired.';
$string['help_codes_usedby'] = 'The student who used it.';
$string['help_codes_usedat'] = 'When it was used.';
$string['help_codes_expires'] = 'The last day it can be used.';
$string['help_codes_made'] = 'Codes made in the batch.';
$string['help_codes_used'] = 'Codes students used.';
$string['help_codes_open'] = 'Codes not used yet and still valid.';
$string['help_codes_disabled'] = 'Codes switched off by an admin.';
$string['help_codes_expired'] = 'Codes that ended without being used.';
$string['help_codes_batch_value'] = 'Total value of all the codes made in the batch (codes × value).';
$string['help_codes_redeemed'] = 'Value of the codes students used.';

$string['help_teacher_dues_operations'] = 'Earnings in the period: each paid activity or private lesson the teacher got a share of.';
$string['help_teacher_dues_unpaid'] = 'Private lessons where the student was absent or cancelled late: the student\'s Flex is used, the teacher gets nothing and the platform keeps its whole value (by design). They are in "Earnings" and "Platform share".';
$string['help_teacher_dues_percent'] = 'The teacher\'s share in percent of the lessons and activities they were paid for (a range when it changed during the period). "—" when every one was unpaid.';
$string['help_teacher_dues_activities'] = 'Earned from paid activities in the period.';
$string['help_teacher_dues_lessons'] = 'Earned from private lessons in the period.';
$string['help_teacher_dues_earned'] = 'Everything earned in the period, including what was later taken back.';
$string['help_teacher_dues_platform'] = 'The platform\'s part of the same earnings, including the whole value of the unpaid lessons.';
$string['help_teacher_dues_reversed'] = 'Earnings taken back in the period (for example after a refund).';
$string['help_teacher_dues_paid'] = 'Money transferred to the teacher in the period.';
$string['help_teacher_dues_lastpaid'] = 'The last time money was transferred to the teacher.';
$string['help_teacher_dues_requested'] = 'Withdrawal requests waiting to be approved or paid.';
$string['help_teacher_dues_balance'] = 'The teacher\'s wallet balance now.';

// What each number card means (help_card_<report>_<view>_<id>, help_card_<report>_<id>, help_card_<id>).
$string['cardmeanings'] = 'What the cards mean';
$string['help_card_students_card_students'] = 'Students matching the filters: every student account when nothing is picked, otherwise the current students (or the ones who left) of the courses.';
$string['help_card_students_card_activeweek'] = 'Of them, the ones who used the website or the app in the last 7 days.';
$string['help_card_students_card_withsubscription'] = 'Of them, the ones with an active subscription now.';
$string['help_card_teachers_card_teachers'] = 'Teachers of the courses in your scope.';
$string['help_card_teachers_card_earned'] = 'What these teachers earned in the period, not counting what was taken back.';
$string['help_card_teachers_card_paidout'] = 'Money transferred to these teachers in the period.';
$string['help_card_courses_card_courses'] = 'Courses matching the filters.';
$string['help_card_courses_card_students'] = 'Current students in these courses, each student counted once.';
$string['help_card_courses_card_avgrating'] = 'Average stars of the approved course reviews; the number of reviews is in brackets.';
$string['help_card_student_results_card_enrolments'] = 'Rows in the table: one per student per course.';
$string['help_card_student_results_card_quizavg'] = 'The average of the courses\' quiz averages.';
$string['help_card_videos_card_videos'] = 'Video lessons in the courses.';
$string['help_card_videos_card_viewers'] = 'Current students who played at least one of them (in the period, when picked).';
$string['help_card_videos_card_avgwatched'] = 'Average share watched, over the videos the students started.';
$string['help_card_sales_card_paidcount'] = 'Online payments completed in the period (partly refunded ones included).';
$string['help_card_sales_card_revenue'] = 'Money from those payments, per currency.';
$string['help_card_sales_card_refunded'] = 'Payments refunded in full: how many, and how much.';
$string['help_card_sales_card_failed'] = 'Payments that failed, were cancelled, or expired before being paid.';
$string['help_card_sales_card_codesales'] = 'Value of the access codes students used in the period. Codes are sold outside the website (for example at a centre), so this money did not go through the payment gateway.';
$string['help_card_sales_card_offlinepackages'] = 'Lesson packages paid in cash or by bank transfer and recorded by an admin, in the period.';
$string['help_card_sales_card_assignedsubs'] = 'Total price of the subscriptions an admin added to students by hand in the period (the price entered when adding them).';
$string['help_card_subscriptions_card_activesubs'] = 'Subscriptions active now (whatever the filters).';
$string['help_card_subscriptions_card_endingweek'] = 'Active subscriptions that end within 7 days.';
$string['help_card_subscriptions_card_subssold'] = 'Subscriptions that started in the period.';
$string['help_card_subscriptions_col_renewals'] = 'Of those, renewals: the student had the same plan before.';
$string['help_card_subscriptions_card_revenue'] = 'Money paid for those subscriptions.';
$string['help_card_packages_card_packagessold'] = 'Lesson packages in the period (waiting-for-payment ones left out).';
$string['help_card_packages_col_flexbought'] = 'Flex in those packages (one Flex = one private lesson).';
$string['help_card_packages_col_flexused'] = 'Flex spent on lessons that took place.';
$string['help_card_packages_col_flexleft'] = 'Flex students can still use (active packages only).';
$string['help_card_packages_col_flexexpired'] = 'Flex left in packages that ended; it cannot be used any more.';
$string['help_card_packages_card_revenue'] = 'Money paid for those packages.';
$string['help_card_packages_card_refundedvalue'] = 'Flex value returned to students\' wallets in the period.';
$string['help_card_lessons_private_card_lessons'] = 'Private lessons in the period, in every state.';
$string['help_card_lessons_private_lesson_completed'] = 'Lessons that took place.';
$string['help_card_lessons_private_lesson_student_absent'] = 'Lessons the student missed.';
$string['help_card_lessons_private_lesson_teacher_absent'] = 'Lessons the teacher missed.';
$string['help_card_lessons_private_card_cancelled'] = 'Lessons cancelled by the student or the teacher, or rejected.';
$string['help_card_lessons_private_card_attendance'] = 'Lessons that took place, out of the ones that took place plus the ones the student missed.';
$string['help_card_lessons_live_card_sessions'] = 'Live sessions in the period, in every state.';
$string['help_card_lessons_live_live_ended'] = 'Sessions that ended.';
$string['help_card_lessons_live_live_cancelled'] = 'Sessions cancelled.';
$string['help_card_lessons_live_card_attendance'] = 'Expected students who joined, out of all expected students.';
$string['help_card_discounts_card_discounts'] = 'Coupons and offers matching the filters.';
$string['help_card_discounts_col_uses'] = 'Times they were used on paid purchases in the period.';
$string['help_card_discounts_col_discountgiven'] = 'Total discount given.';
$string['help_card_discounts_card_discountsales'] = 'What students paid after the discount.';
$string['help_card_codes_col_made'] = 'Codes made in the period.';
$string['help_card_codes_code_used'] = 'Of them, the ones students used.';
$string['help_card_codes_code_active'] = 'Of them, the ones not used yet and still valid.';
$string['help_card_codes_col_redeemed'] = 'Value of the codes students used.';
$string['help_card_teacher_dues_col_earned'] = 'Everything the teachers earned in the period, including what was later taken back.';
$string['help_card_teacher_dues_col_paidout'] = 'Money transferred to the teachers in the period.';
$string['help_card_teacher_dues_col_requested'] = 'Withdrawal requests waiting to be approved or paid.';
$string['help_card_teacher_dues_col_balance'] = 'The teachers\' wallet balances now.';

// Privacy.
$string['privacy:metadata'] = 'The reports show data stored by other plugins and store no personal data themselves.';
