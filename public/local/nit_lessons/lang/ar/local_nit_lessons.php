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
 * Arabic strings for local_nit_lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'الحصص المباشرة';
$string['local_nit_lessons:request'] = 'طلب حصص مباشرة';
$string['local_nit_lessons:teach'] = 'تقديم حصص مباشرة';
$string['local_nit_lessons:managesettings'] = 'إدارة الحصص المباشرة وإعداداتها';
$string['messageprovider:lessonupdate'] = 'تحديثات الحصص المباشرة (الطلبات والردود والبدء والإلغاء)';

// Pages.
$string['studenthub'] = 'حصصي والفلكس';
$string['mylessons'] = 'حصصي المباشرة';
$string['teacherprofile'] = 'ملف المدرس';
$string['managelessons'] = 'الحصص المباشرة';
$string['notateacher'] = 'هذه الصفحة للمدرسين.';

// Settings.
$string['lessonsettings'] = 'إعدادات الحصص المباشرة';
$string['lessonscourse'] = 'كورس غرف الحصص';
$string['lessonscourse_desc'] = 'عندما يبدأ المدرس حصة مباشرة تُنشأ غرفة Jitsi في هذا الكورس، ولا يراها إلا المدرس والطالب. استخدم كورسًا مخفيًا مخصصًا لذلك.';
$string['set_min_booking_minutes'] = 'الحجز قبل الموعد بـ (دقيقة) على الأقل';
$string['set_min_booking_minutes_desc'] = 'أقل مدة قبل بداية الحصة يمكن فيها طلبها أو تغيير موعدها.';
$string['set_cancel_deadline_minutes'] = 'الإلغاء المجاني حتى (دقيقة قبل الحصة)';
$string['set_cancel_deadline_minutes_desc'] = 'الطالب الذي يلغي قبل ذلك يسترد الفلكس، وبعده يُستهلك الفلكس.';
$string['set_update_deadline_minutes'] = 'تغيير الموعد حتى (دقيقة قبل الحصة)';
$string['set_update_deadline_minutes_desc'] = 'يمكن طلب موعد جديد حتى هذا العدد من الدقائق قبل الحصة.';
$string['set_start_allowed_minutes'] = 'البدء المبكر (دقيقة قبل الموعد)';
$string['set_start_allowed_minutes_desc'] = 'أقصى مدة قبل الموعد يمكن للمدرس فيها بدء الحصة.';
$string['set_complete_allowed_minutes'] = 'الإنهاء بعد (دقيقة)';
$string['set_complete_allowed_minutes_desc'] = 'عدد الدقائق بعد البدء قبل أن يستطيع المدرس إنهاء الحصة.';
$string['set_absence_report_minutes'] = 'الإبلاغ عن الغياب بعد (دقيقة)';
$string['set_absence_report_minutes_desc'] = 'عدد الدقائق بعد موعد البدء قبل أن يستطيع أي طرف الإبلاغ عن غياب الآخر.';

// Tabs and hub.
$string['tab_book'] = 'احجز حصة';
$string['tab_lessons'] = 'حصصي';
$string['tab_packages'] = 'الباقات والفلكس';
$string['tab_subavailable'] = 'الاشتراكات المتاحة';
$string['tab_mysubs'] = 'اشتراكاتي';
$string['flexavailable'] = 'فلكس متاح';
$string['flexbooked'] = '{$a} محجوز لحصص قادمة';
$string['nopackage'] = 'ليس لديك باقة نشطة.';
$string['needflex'] = 'تحتاج إلى فلكس لحجز حصة.';
$string['searchsubject'] = 'ابحث بالمادة';
$string['requestlesson'] = 'اطلب حصة';
$string['requestwith'] = 'حصة مع {$a}';
$string['noteachers'] = 'لا يوجد مدرس يستقبل حجوزات بعد.';
$string['nolessons'] = 'لا توجد حصص هنا بعد.';
$string['nolessons_teacher'] = 'لا توجد حصص هنا بعد. ستظهر طلبات الطلاب هنا.';
$string['nolessons_admin'] = 'لا توجد حصص مباشرة بعد.';
$string['mypackages'] = 'باقاتي';
$string['flexusage'] = 'متبقٍ {$a->remaining} · محجوز {$a->reserved} · مستهلك {$a->used} / {$a->total}';
$string['activated'] = 'البداية';
$string['paymenthistory'] = 'المدفوعات';
$string['reference'] = 'المرجع';
$string['nopayments'] = 'لا توجد مدفوعات بعد.';
$string['flexhistory'] = 'سجل الفلكس';
$string['type'] = 'النوع';
$string['change'] = 'التغيير';
$string['lesson'] = 'الحصة';
$string['lessonnum'] = 'حصة رقم {$a}';
$string['lessongroup'] = 'حصة مباشرة رقم {$a}';
$string['noflexhistory'] = 'لا توجد حركات فلكس بعد.';
$string['subdays'] = '{$a} يوم';
$string['subscribe'] = 'اشترك';
$string['subalreadyactive'] = 'لديك اشتراك نشط بالفعل.';
$string['subnoonline'] = 'الدفع الأونلاين غير متاح الآن.';
$string['nosubs'] = 'لا توجد اشتراكات متاحة حاليًا.';
$string['subscription'] = 'الاشتراك';
$string['daysleft'] = 'الأيام المتبقية';
$string['nomysubs'] = 'ليس لديك اشتراكات بعد.';
$string['subpayments'] = 'مدفوعات الاشتراكات';
$string['sstat_active'] = 'نشط';
$string['sstat_expired'] = 'منتهٍ';
$string['sstat_cancelled'] = 'ملغى';
$string['sstat_pending'] = 'قيد الانتظار';
$string['sstat_payment_failed'] = 'فشل الدفع';
$string['pay_completed'] = 'مدفوع';
$string['pay_pending'] = 'قيد الانتظار';
$string['pay_failed'] = 'فشل';
$string['pay_cancelled'] = 'ملغى';
$string['pay_refunded'] = 'مُسترد';

// Lesson cards.
$string['withteacher'] = 'مع {$a}';
$string['withstudent'] = 'الطالب: {$a}';
$string['lesson_at'] = 'الموعد:';
$string['lesson_requestedfor'] = 'الموعد المطلوب:';
$string['rejectreason'] = 'السبب:';
$string['cancelreason'] = 'سبب الإلغاء:';
$string['suggestedtime'] = 'الموعد المقترح: {$a}';
$string['reschedulepending'] = '{$a->who} طلب نقل الحصة إلى {$a->time}.';
$string['you'] = 'أنت';
$string['theteacher'] = 'المدرس';
$string['thestudent'] = 'الطالب';
$string['lstat_pending'] = 'في انتظار المدرس';
$string['lstat_waiting_student'] = 'في انتظار الطالب';
$string['lstat_waiting_teacher'] = 'في انتظار المدرس';
$string['lstat_confirmed'] = 'مؤكدة';
$string['lstat_in_progress'] = 'مباشرة الآن';
$string['lstat_completed'] = 'مكتملة';
$string['lstat_student_absent'] = 'غياب الطالب';
$string['lstat_teacher_absent'] = 'غياب المدرس';
$string['lstat_cancelled'] = 'ملغاة';
$string['lstat_cancelled_teacher'] = 'ألغاها المدرس';
$string['lstat_rejected'] = 'مرفوضة';
$string['allstatuses'] = 'كل الحصص';
$string['flexstate_reserved'] = 'فلكس محجوز';
$string['flexstate_consumed'] = 'فلكس مستهلك';
$string['flexstate_returned'] = 'فلكس مُعاد';
$string['act_accept'] = 'قبول';
$string['act_reject'] = 'رفض';
$string['act_suggest'] = 'اقترح موعدًا آخر';
$string['act_cancel_request'] = 'سحب الطلب';
$string['act_cancel'] = 'إلغاء الحصة';
$string['act_teacher_absent'] = 'المدرس لم يحضر';
$string['act_student_absent'] = 'الطالب لم يحضر';
$string['act_reschedule'] = 'تغيير الموعد';
$string['act_start'] = 'ابدأ الحصة';
$string['act_complete'] = 'تم إنهاء الحصة';
$string['act_join'] = 'ادخل الحصة';
$string['acceptnewtime'] = 'قبول الموعد الجديد';
$string['rejectnewtime'] = 'الإبقاء على الموعد القديم';
$string['confirm_teacher_absent'] = 'الإبلاغ عن عدم حضور المدرس؟ سيعود إليك الفلكس.';
$string['confirm_student_absent'] = 'الإبلاغ عن عدم حضور الطالب؟ سيُستهلك فلكس الطالب.';
$string['cancel_early'] = 'أنت تلغي في الوقت المسموح: سيعود إليك الفلكس.';
$string['cancel_late'] = 'فات وقت الإلغاء المجاني: سيُستهلك الفلكس.';

// Dialog.
$string['field_subject'] = 'المادة';
$string['field_note'] = 'ماذا تريد أن تذاكر؟ (مطلوب)';
$string['field_completenote'] = 'ملاحظة عن الحصة (اختياري)';
$string['field_reason'] = 'السبب (مطلوب)';
$string['field_reasonoptional'] = 'السبب (اختياري)';
$string['pickday'] = 'اليوم';
$string['picktime'] = 'الوقت (ساعة واحدة)';
$string['noslots'] = 'لا توجد مواعيد متاحة في الأسبوعين القادمين.';
$string['picktimefirst'] = 'اختر موعدًا أولًا.';

// Results.
$string['done_requested'] = 'تم إرسال الطلب. سيصلك إشعار عند رد المدرس.';
$string['done_saved'] = 'تم.';
$string['done_started'] = 'بدأت الحصة. ادخل الغرفة الآن.';
$string['done_completed'] = 'تم إنهاء الحصة وإضافة أرباحك.';
$string['done_cancelled'] = 'تم إلغاء الحصة.';
$string['done_reschedule'] = 'تم إرسال الموعد الجديد للطرف الآخر.';

// Teacher profile.
$string['teacherprofile_intro'] = 'يحجز الطلاب معك حصصًا مباشرة فردية باستخدام الفلكس. اختر موادك والساعات المتاحة لك؛ الحصة ساعة واحدة.';
$string['takebookings'] = 'يمكن للطلاب حجز حصص مباشرة معي';
$string['headline'] = 'نبذة قصيرة';
$string['headline_ph'] = 'مثال: مدرس فيزياء، خبرة 10 سنوات';
$string['subjects'] = 'المواد';
$string['subject_ph'] = 'المادة، مثال: فيزياء';
$string['addsubject'] = 'إضافة مادة';
$string['workinghours'] = 'المواعيد الأسبوعية';
$string['workinghours_help'] = 'المواعيد بتوقيت {$a}. بدون مواعيد يمكن للطلاب حجزك من 08:00 إلى 20:00 يوميًا.';
$string['to'] = 'إلى';
$string['addhours'] = 'إضافة مواعيد';
$string['profilesaved'] = 'تم حفظ ملف المدرس.';
$string['notbookable'] = 'لا يستطيع الطلاب حجزك بعد: فعّل الحجز وأضف مادة واحدة على الأقل في';
$string['day0'] = 'الأحد';
$string['day1'] = 'الاثنين';
$string['day2'] = 'الثلاثاء';
$string['day3'] = 'الأربعاء';
$string['day4'] = 'الخميس';
$string['day5'] = 'الجمعة';
$string['day6'] = 'السبت';

// Admin page.
$string['student'] = 'الطالب';
$string['teacher'] = 'المدرس';
$string['when'] = 'الموعد';
$string['earning'] = 'الأرباح';
$string['earningsplit'] = 'المدرس {$a->teacher} · المنصة {$a->platform}';
$string['reverseflex'] = 'إرجاع الفلكس';
$string['confirmreverse'] = 'إرجاع الفلكس للطالب واسترداد نصيبي المدرس والمنصة من محفظتيهما؟';
$string['reversed'] = 'تم إرجاع الفلكس للطالب وإلغاء الأرباح.';
$string['roomnotconfigured'] = 'غرف الحصص المباشرة غير مُعدّة: اختر كورس غرف الحصص من <a href="{$a}">إعدادات الحصص المباشرة</a>.';

// Notifications.
$string['notif_requested'] = 'طلب حصة جديد: {$a->subject} من {$a->student}';
$string['notif_accepted_by_teacher'] = 'تم تأكيد حصة {$a->subject}';
$string['notif_rejected_by_teacher'] = 'تم رفض طلب حصة {$a->subject}';
$string['notif_suggested_by_teacher'] = '{$a->teacher} اقترح موعدًا آخر لحصة {$a->subject}';
$string['notif_accepted_by_student'] = '{$a->student} أكد حصة {$a->subject}';
$string['notif_rejected_by_student'] = '{$a->student} رفض الموعد المقترح لحصة {$a->subject}';
$string['notif_suggested_by_student'] = '{$a->student} اقترح موعدًا آخر لحصة {$a->subject}';
$string['notif_started'] = 'بدأت حصة {$a->subject} — ادخل الآن';
$string['notif_completed'] = 'اكتملت حصة {$a->subject}';
$string['notif_student_absent'] = 'تم تسجيل غيابك عن حصة {$a->subject}';
$string['notif_teacher_absent'] = '{$a->student} أبلغ عن غيابك عن حصة {$a->subject}';
$string['notif_request_withdrawn'] = '{$a->student} سحب طلب حصة {$a->subject}';
$string['notif_cancelled_by_student'] = '{$a->student} ألغى حصة {$a->subject}';
$string['notif_cancelled_by_teacher'] = '{$a->teacher} ألغى حصة {$a->subject}، وعاد إليك الفلكس';
$string['notif_reschedule_requested'] = 'طُلب موعد جديد لحصة {$a->subject}';
$string['notif_reschedule_answered'] = 'تم الرد على طلب نقل حصة {$a->subject}';
$string['notif_details'] = 'المادة: {$a->subject} · الموعد: {$a->time}';

// Errors.
$string['err_subjectrequired'] = 'اختر المادة.';
$string['err_noterequired'] = 'اكتب ماذا تريد أن تذاكر.';
$string['err_selfbooking'] = 'لا يمكنك حجز حصة مع نفسك.';
$string['err_teachernotfound'] = 'المدرس غير موجود.';
$string['err_teachernotbookable'] = 'هذا المدرس لا يستقبل حجوزات الآن.';
$string['err_subjectnotoffered'] = 'هذا المدرس لا يدرّس هذه المادة.';
$string['err_outsidehours'] = 'هذا الموعد خارج مواعيد المدرس.';
$string['err_noflex'] = 'لا يوجد لديك فلكس. اشترِ باقة أولًا.';
$string['err_forbidden'] = 'لا يمكنك تنفيذ هذا على هذه الحصة.';
$string['err_badstate'] = 'تغيرت حالة الحصة. حدّث الصفحة.';
$string['err_badaction'] = 'إجراء غير معروف.';
$string['err_tooearlytostart'] = 'ما زال الوقت مبكرًا لبدء هذه الحصة.';
$string['err_reasonrequired'] = 'السبب مطلوب.';
$string['err_updatedeadline'] = 'فات وقت تغيير موعد هذه الحصة.';
$string['err_updatepending'] = 'يوجد طلب تغيير موعد ينتظر الرد بالفعل.';
$string['err_noupdaterequest'] = 'لا يوجد طلب تغيير موعد للرد عليه.';
$string['err_notime'] = 'اختر موعدًا.';
$string['err_minbooking'] = 'هذا الموعد قريب جدًا. اختر موعدًا لاحقًا.';
$string['err_timeconflict'] = 'المدرس غير متاح في هذا الموعد.';
$string['err_completetooearly'] = 'ما زال الوقت مبكرًا لإنهاء هذه الحصة.';
$string['err_absencetooearly'] = 'ما زال الوقت مبكرًا للإبلاغ عن الغياب.';
$string['err_lessonnotfound'] = 'الحصة غير موجودة.';
$string['err_settingnegative'] = 'الإعدادات لا يمكن أن تكون سالبة.';
$string['err_subjectsrequired'] = 'أضف مادة واحدة على الأقل لاستقبال الحجوزات.';
$string['err_badhours'] = 'كل فترة تحتاج يومًا ووقت بداية ونهاية بينهما ساعة على الأقل.';
$string['err_nolessonscourse'] = 'غرف الحصص المباشرة غير مُعدّة بعد. تواصل مع الإدارة.';
$string['err_roomnotready'] = 'غرفة الحصة لم تُفتح بعد. يجب أن يبدأ المدرس الحصة.';

// Privacy.
$string['privacy:metadata:nit_lesson'] = 'الحصص المباشرة بين الطالب والمدرس.';
$string['privacy:metadata:nit_lesson:studentid'] = 'الطالب.';
$string['privacy:metadata:nit_lesson:teacherid'] = 'المدرس.';
$string['privacy:metadata:nit_lesson:note'] = 'الملاحظة المكتوبة مع الطلب.';
$string['privacy:metadata:nit_lesson:timecreated'] = 'وقت طلب الحصة.';
$string['privacy:metadata:nit_lesson_proposal'] = 'المواعيد المقترحة أثناء الاتفاق على الحصة.';
$string['privacy:metadata:nit_lesson_proposal:proposedby'] = 'من اقترح الموعد.';
$string['privacy:metadata:nit_lesson_proposal:proposed_time'] = 'الموعد المقترح.';
$string['privacy:metadata:nit_lesson_proposal:timecreated'] = 'وقت الاقتراح.';
