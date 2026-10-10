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
 * Arabic strings for local_nit_reports.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'التقارير';
$string['nit_reports:view'] = 'عرض تقارير الكورسات اللي في النطاق';
$string['nit_reports:viewteaching'] = 'عرض تقارير التدريس لكورساتي';
$string['reports'] = 'التقارير';
$string['currency_egp'] = 'ج.م';
$string['minutes'] = '{$a} دقيقة';

// Reports.
$string['report_students'] = 'الطلاب';
$string['report_teachers'] = 'المدرسين';
$string['report_courses'] = 'أداء الكورسات';
$string['report_student_results'] = 'نتايج الطلاب';
$string['report_videos'] = 'مشاهدة الفيديوهات';
$string['report_sales'] = 'المبيعات والإيرادات';
$string['report_subscriptions'] = 'الاشتراكات';
$string['report_packages'] = 'باقات الحصص';
$string['report_lessons'] = 'الحصص والحضور';
$string['report_discounts'] = 'الكوبونات والعروض';
$string['report_codes'] = 'الأكواد';
$string['report_teacher_dues'] = 'أرباح المدرسين والسحب';

// Filters and page.
$string['filter_from'] = 'من';
$string['filter_to'] = 'إلى';
$string['filter_user'] = 'المستخدم';
$string['filter_view'] = 'العرض';
$string['filter_period'] = 'الفترة';
$string['filter_none'] = 'من غير فلتر';
$string['allcourses'] = 'كل الكورسات';
$string['allusers'] = 'الكل';
$string['allstatuses'] = 'الكل';
$string['clearfilters'] = 'مسح الفلاتر';
$string['export'] = 'تصدير';
$string['norows'] = 'مفيش بيانات للفلاتر دي.';
$string['nrows'] = '{$a} صف';
$string['generatedat'] = 'اتعمل في {$a}';
$string['pdftruncated'] = 'الـPDF ده فيه أول {$a} صف بس؛ نزّل Excel علشان كل الصفوف.';
$string['search_students'] = 'الاسم أو الإيميل أو الموبايل';
$string['search_teachers'] = 'الاسم أو الإيميل';
$string['search_courses'] = 'اسم الكورس';
$string['search_student_results'] = 'اسم الطالب أو إيميله';
$string['search_videos'] = 'اسم الفيديو';
$string['search_sales'] = 'رقم الطلب أو الطالب أو المنتج';
$string['search_subscriptions'] = 'الطالب أو الخطة';
$string['search_packages'] = 'الطالب أو الباقة';
$string['search_lessons'] = 'الطالب أو عنوان الحصة';
$string['search_discounts'] = 'كود الكوبون أو اسم العرض';
$string['search_codes'] = 'الكود أو الدفعة';
$string['search_teacher_dues'] = 'اسم المدرس';

// Views.
$string['view_transactions'] = 'كل المدفوعات';
$string['view_byitem'] = 'حسب المنتج';
$string['view_byprovider'] = 'حسب وسيلة الدفع';
$string['view_bymonth'] = 'حسب الشهر';
$string['view_subscriptions'] = 'كل الاشتراكات';
$string['view_byplan'] = 'حسب الخطة';
$string['view_packages'] = 'كل الباقات';
$string['view_bystudent'] = 'حسب الطالب';
$string['view_privatelessons'] = 'الحصص الخاصة';
$string['view_livesessions'] = 'الحصص المباشرة';
$string['view_bybatch'] = 'حسب الدفعة';
$string['view_codes'] = 'كل الأكواد';

// Cards.
$string['card_students'] = 'الطلاب';
$string['card_activeweek'] = 'نشطين آخر 7 أيام';
$string['card_withsubscription'] = 'معاهم اشتراك نشط';
$string['card_teachers'] = 'المدرسين';
$string['card_earned'] = 'المكسب في الفترة';
$string['card_paidout'] = 'المصروف في الفترة';
$string['card_courses'] = 'الكورسات';
$string['card_avgrating'] = 'متوسط التقييم';
$string['card_enrolments'] = 'اشتراكات الطلاب في الكورسات';
$string['card_quizavg'] = 'متوسط نتايج الاختبارات';
$string['card_videos'] = 'دروس الفيديو';
$string['card_viewers'] = 'طلاب اتفرجوا';
$string['card_avgwatched'] = 'متوسط المشاهدة';
$string['card_paidcount'] = 'مدفوعات ناجحة';
$string['card_revenue'] = 'الإيرادات';
$string['card_refunded'] = 'المسترد';
$string['card_failed'] = 'فاشلة / ملغاة';
$string['card_codesales'] = 'قيمة الأكواد المستخدمة';
$string['card_offlinepackages'] = 'باقات اتدفعت كاش أو تحويل';
$string['card_assignedsubs'] = 'قيمة اشتراكات أضافها الأدمن';
$string['card_activesubs'] = 'اشتراكات نشطة دلوقتي';
$string['card_endingweek'] = 'بتخلص خلال 7 أيام';
$string['card_subssold'] = 'اشتراكات في الفترة';
$string['card_packagessold'] = 'الباقات';
$string['card_refundedvalue'] = 'مسترد للمحافظ';
$string['card_sessions'] = 'الحصص';
$string['card_lessons'] = 'الحصص';
$string['card_cancelled'] = 'ملغاة / مرفوضة';
$string['card_attendance'] = 'نسبة الحضور';
$string['card_discounts'] = 'الكوبونات والعروض';
$string['card_discountsales'] = 'مبيعات بخصم';
$string['chart_revenue'] = 'الإيرادات على مدار الوقت';

// Columns.
$string['col_registered'] = 'تاريخ التسجيل';
$string['col_courses'] = 'الكورسات';
$string['col_subscription'] = 'الاشتراك النشط';
$string['col_lastaccess'] = 'آخر دخول';
$string['col_completion'] = 'نسبة الإكمال';
$string['col_quizavg'] = 'متوسط الاختبارات';
$string['col_videoavg'] = 'نسبة مشاهدة الفيديو';
$string['col_students'] = 'الطلاب';
$string['col_rating'] = 'التقييم';
$string['col_earned'] = 'المكسب';
$string['col_paidout'] = 'المصروف';
$string['col_teachers'] = 'المدرسين';
$string['col_completed'] = 'خلّصوا الكورس';
$string['col_sales'] = 'المبيعات';
$string['col_revenue'] = 'الإيراد';
$string['col_quizzestaken'] = 'الاختبارات اللي اتحلت';
$string['col_lastquiz'] = 'آخر اختبار';
$string['col_video'] = 'الفيديو';
$string['col_provider'] = 'المنصة';
$string['col_started'] = 'بدأوا';
$string['col_finished'] = 'خلّصوه (90% أو أكتر)';
$string['col_avgwatched'] = 'متوسط المشاهدة';
$string['col_lastwatched'] = 'آخر مشاهدة';
$string['col_order'] = 'رقم الطلب';
$string['col_student'] = 'الطالب';
$string['col_itemtype'] = 'النوع';
$string['col_item'] = 'المنتج';
$string['col_paymethod'] = 'وسيلة الدفع';
$string['col_amount'] = 'المبلغ';
$string['col_originalamount'] = 'قبل الخصم';
$string['col_coupon'] = 'الكوبون';
$string['col_refunded'] = 'المسترد';
$string['col_month'] = 'الشهر';
$string['col_plan'] = 'الخطة';
$string['col_price'] = 'السعر';
$string['col_sold'] = 'المباع';
$string['col_renewals'] = 'التجديدات';
$string['col_type'] = 'النوع';
$string['col_pricepaid'] = 'المدفوع';
$string['col_source'] = 'المصدر';
$string['col_activated'] = 'البداية';
$string['col_expires'] = 'النهاية';
$string['col_renewal'] = 'تجديد';
$string['col_flexbought'] = 'Flex مشترى';
$string['col_flexused'] = 'Flex مستخدم';
$string['col_flexreserved'] = 'Flex محجوز لحصص';
$string['col_flexleft'] = 'Flex متبقي';
$string['col_flexexpired'] = 'Flex منتهي';
$string['col_packages'] = 'الباقات';
$string['col_package'] = 'الباقة';
$string['col_session'] = 'الحصة';
$string['col_teacher'] = 'المدرس';
$string['col_start'] = 'الميعاد';
$string['col_duration'] = 'المدة';
$string['col_invited'] = 'المدعوين';
$string['col_attended'] = 'حضروا';
$string['col_teacherjoined'] = 'دخول المدرس';
$string['col_subject'] = 'المادة';
$string['col_flexstate'] = 'Flex';
$string['col_actualminutes'] = 'المدة الفعلية';
$string['col_codeorname'] = 'الكود / الاسم';
$string['col_discount'] = 'الخصم';
$string['col_validity'] = 'الصلاحية';
$string['col_uses'] = 'مرات الاستخدام';
$string['col_users'] = 'الطلاب';
$string['col_discountgiven'] = 'إجمالي الخصم';
$string['col_code'] = 'الكود';
$string['col_batch'] = 'الدفعة';
$string['col_value'] = 'القيمة';
$string['col_usedby'] = 'استخدمه';
$string['col_usedat'] = 'وقت الاستخدام';
$string['col_made'] = 'الأكواد المعمولة';
$string['col_valuemade'] = 'قيمة الأكواد المعمولة';
$string['col_redeemed'] = 'قيمة الأكواد المستخدمة';
$string['col_earnedactivities'] = 'من الدروس';
$string['col_earnedlessons'] = 'من الحصص الخاصة';
$string['col_reversed'] = 'اترجع';
$string['col_requested'] = 'مطلوب ولسه ماتصرفش';
$string['col_balance'] = 'رصيد المحفظة دلوقتي';

// Values.
$string['item_course'] = 'كورس';
$string['item_subscription'] = 'اشتراك';
$string['item_package'] = 'باقة حصص';
$string['item_wallet_topup'] = 'شحن محفظة';
$string['item_activity'] = 'درس';
$string['item_other'] = 'أخرى';
$string['item_coupon'] = 'كوبون';
$string['item_offer'] = 'عرض';
$string['pay_completed'] = 'ناجحة';
$string['pay_pending'] = 'معلّقة';
$string['pay_failed'] = 'فاشلة';
$string['pay_cancelled'] = 'ملغاة';
$string['pay_expired'] = 'منتهية';
$string['pay_timed_out'] = 'انتهى وقتها';
$string['pay_refunded'] = 'مستردة';
$string['pay_partially_refunded'] = 'مستردة جزئيًا';
$string['pay_voided'] = 'لاغية';
$string['pay_chargeback'] = 'استرجاع بنكي';
$string['pay_duplicate'] = 'مكررة';
$string['sub_active'] = 'نشط';
$string['sub_ending'] = 'بيخلص خلال 7 أيام';
$string['sub_expired'] = 'انتهى';
$string['sub_cancelled'] = 'ملغي';
$string['sub_normal'] = 'فردي';
$string['sub_b2b'] = 'جماعي ({$a} مقعد)';
$string['source_online'] = 'أونلاين';
$string['source_admin'] = 'ضافه الأدمن';
$string['pkg_active'] = 'نشطة';
$string['pkg_fully_used'] = 'اتستخدمت كلها';
$string['pkg_expired'] = 'منتهية';
$string['pkg_cancelled'] = 'ملغاة';
$string['pkg_pending'] = 'مستنية الدفع';
$string['lesson_pending'] = 'مطلوبة';
$string['lesson_waiting_student'] = 'مستنية الطالب';
$string['lesson_waiting_teacher'] = 'مستنية المدرس';
$string['lesson_confirmed'] = 'متأكدة';
$string['lesson_in_progress'] = 'شغالة';
$string['lesson_completed'] = 'تمت';
$string['lesson_student_absent'] = 'غياب الطالب';
$string['lesson_teacher_absent'] = 'غياب المدرس';
$string['lesson_cancelled'] = 'لغاها الطالب';
$string['lesson_cancelled_teacher'] = 'لغاها المدرس';
$string['lesson_rejected'] = 'مرفوضة';
$string['live_scheduled'] = 'مجدولة';
$string['live_live'] = 'شغالة دلوقتي';
$string['live_ended'] = 'خلصت';
$string['live_cancelled'] = 'ملغاة';
$string['flex_none'] = '—';
$string['flex_reserved'] = 'محجوز';
$string['flex_consumed'] = 'مستخدم';
$string['flex_returned'] = 'اترجع';
$string['code_active'] = 'لم تستخدم بعد';
$string['code_used'] = 'مستخدم';
$string['code_disabled'] = 'موقوف';
$string['code_expired'] = 'منتهي';
$string['state_active'] = 'شغال';
$string['state_inactive'] = 'موقوف';

// Added columns and values.
$string['col_leftcourses'] = 'كورسات خرج منها';
$string['col_subends'] = 'نهاية الاشتراك';
$string['col_videosfinished'] = 'الفيديوهات اللي خلّصها';
$string['col_totalpaid'] = 'إجمالي المدفوع';
$string['col_wallet'] = 'المحفظة';
$string['col_account'] = 'الحساب';
$string['col_activeweek'] = 'نشطين الأسبوع ده';
$string['col_leftstudents'] = 'خرجوا من الكورس';
$string['col_videocount'] = 'عدد الفيديوهات';
$string['col_quizcount'] = 'عدد الاختبارات';
$string['col_refunds'] = 'المرتجعات';
$string['col_lessonsdone'] = 'حصص خاصة اتعملت';
$string['col_sessionsheld'] = 'حصص مباشرة اتعملت';
$string['col_teacherabsences'] = 'مرات الغياب';
$string['col_paytype'] = 'دفع بـ';
$string['col_reference'] = 'رقم العملية عند البوابة';
$string['col_failreason'] = 'سبب الفشل';
$string['col_absent'] = 'غابوا';
$string['col_avgminutes'] = 'متوسط مدة الحضور';
$string['col_teacherlate'] = 'تأخير المدرس';
$string['col_requested_at'] = 'وقت الطلب';
$string['col_reason'] = 'السبب';
$string['col_actualstart'] = 'البداية الفعلية';
$string['col_actualend'] = 'النهاية الفعلية';
$string['col_teachershare'] = 'نصيب المدرس';
$string['col_operations'] = 'عدد العمليات';
$string['col_soldat'] = 'اتباع بكام';
$string['col_unpaidlessons'] = 'من غير أجر (غياب الطالب / إلغاء متأخر)';
$string['col_teacherpercent'] = 'نسبة المدرس';
$string['col_platformshare'] = 'نصيب المنصة';
$string['col_lastpaid'] = 'آخر تحويل';
$string['member_current'] = 'الطلاب الحاليين';
$string['member_left'] = 'الطلاب اللي خرجوا';
$string['account_active'] = 'نشط';
$string['account_suspended'] = 'موقوف';
$string['noend'] = 'من غير نهاية';
$string['free'] = 'مجاني';
$string['ontime'] = 'في الميعاد';
$string['method_card'] = 'كارت';
$string['method_wallet'] = 'محفظة موبايل';
$string['method_fawry'] = 'فوري';
$string['method_meeza'] = 'ميزة';
$string['method_valu'] = 'ڤاليو';
$string['method_aman'] = 'أمان';
$string['method_basata'] = 'بساطة';
$string['method_souhoola'] = 'سهولة';
$string['method_sympl'] = 'سيمبل';
$string['method_bank_installments'] = 'تقسيط بنكي';

// Table.
$string['perpage'] = 'عدد الصفوف';
$string['showingrows'] = 'عرض {$a->first}–{$a->last} من {$a->total}';
$string['sortby'] = 'رتّب حسب {$a}';
$string['sortedasc'] = 'مترتب تصاعدي';
$string['sorteddesc'] = 'مترتب تنازلي';
$string['columnmeanings'] = 'معاني الأعمدة';

// What each column means.
$string['help_name'] = 'الاسم بالكامل.';
$string['help_email'] = 'الإيميل المسجل على الحساب.';
$string['help_phone'] = 'رقم الموبايل المسجل على الحساب.';
$string['help_course'] = 'الكورس.';
$string['help_student'] = 'الطالب.';
$string['help_teacher'] = 'المدرس.';
$string['help_registered'] = 'اليوم اللي الحساب اتعمل فيه.';
$string['help_lastaccess'] = 'آخر مرة دخل فيها من الموقع أو من التطبيق.';
$string['help_rating'] = 'متوسط النجوم (من 1 لـ 5) في التقييمات المقبولة، وعدد التقييمات بين القوسين.';

$string['help_students_courses'] = 'الكورسات (في نطاقك) اللي هو طالب فيها دلوقتي: مسجل فيها، والتسجيل مش موقوف ولا خلص.';
$string['help_students_left'] = 'الكورسات (في نطاقك) اللي خرج منها: كان مسجل فيها أو دفع فيها أو حل اختبار أو شاف فيديو، بس مبقاش طالب فيها (اتشال، أو اتوقف، أو التسجيل خلص). بياناته محفوظة، وبترجع تتحسب لو رجع الكورس.';
$string['help_students_subscription'] = 'خطة الاشتراك النشط بتاعه.';
$string['help_students_subends'] = 'إمتى الاشتراك النشط بتاعه هيخلص.';
$string['help_students_completion'] = 'متوسط إنجازه في كورساته: نسبة الأنشطة اللي المدرس فعّل لها تتبع الإكمال وهو خلّصها. بيظهر "—" لو الكورسات مش مفعل فيها التتبع. لو بتعرض الطلاب اللي خرجوا، النسبة بتبقى على الكورسات اللي خرجوا منها.';
$string['help_students_quiz'] = 'متوسط درجاته في الاختبارات في كورساته (كل درجة كنسبة من الدرجة النهائية للاختبار). لو بتعرض الطلاب اللي خرجوا، المتوسط بيبقى على الكورسات اللي خرجوا منها.';
$string['help_students_video'] = 'عدد الفيديوهات اللي خلّصها (شاف 90% منها أو أكتر)، من إجمالي الفيديوهات في كورساته.';
$string['help_students_paid'] = 'كل اللي دفعه أونلاين عن طريق بوابة الدفع (كورسات، واشتراكات، وباقات، وشحن محفظة). العمليات اللي اترجعت بالكامل مش محسوبة.';
$string['help_students_wallet'] = 'رصيد محفظته دلوقتي.';
$string['help_students_flex'] = 'الـFlex اللي لسه يقدر يستخدمه من باقاته النشطة.';
$string['help_students_account'] = 'نشط، أو موقوف من الأدمن (مش هيقدر يدخل).';

$string['help_teachers_courses'] = 'الكورسات (في نطاقك) اللي بيدرّسها دلوقتي.';
$string['help_teachers_students'] = 'الطلاب الحاليين في كل كورساته، وكل طالب بيتحسب مرة واحدة.';
$string['help_teachers_video'] = 'متوسط نسبة مشاهدة الطلاب الحاليين لفيديوهات كورساته، على الفيديوهات اللي كل طالب فتحها.';
$string['help_teachers_lessons'] = 'الحصص الخاصة اللي خلّصها في الفترة.';
$string['help_teachers_sessions'] = 'الحصص المباشرة في الفترة اللي خلصت والمدرس كان حاضر.';
$string['help_teachers_absences'] = 'في الفترة: الحصص الخاصة اللي اتسجل فيها "المدرس غاب"، والحصص المباشرة اللي خلصت من غير ما المدرس يدخل.';
$string['help_teachers_earned'] = 'نصيب المدرس اللي كسبه في الفترة (من الدروس المدفوعة والحصص الخاصة)، من غير اللي اترجع منه.';
$string['help_teachers_paid'] = 'الفلوس اللي اتحولت للمدرس في الفترة (طلبات سحب اتعلّمت "مدفوع").';
$string['help_teachers_wallet'] = 'رصيد محفظته دلوقتي: اللي لسه يقدر يطلب يسحبه.';
$string['help_teachers_pending'] = 'طلبات سحب مستنية الموافقة أو الدفع.';

$string['help_courses_category'] = 'تصنيف الكورس.';
$string['help_courses_teachers'] = 'مدرسين الكورس دلوقتي.';
$string['help_courses_students'] = 'الطلاب اللي في الكورس دلوقتي: مسجلين، والتسجيل مش موقوف ولا خلص.';
$string['help_courses_active'] = 'الطلاب الحاليين اللي فتحوا الكورس في آخر 7 أيام.';
$string['help_courses_left'] = 'طلاب كانوا في الكورس (اتسجلوا، أو دفعوا، أو حلوا اختبار، أو شافوا فيديو) ومبقوش فيه دلوقتي. مش داخلين في باقي أرقام الطلاب.';
$string['help_courses_completed'] = 'الطلاب الحاليين اللي خلّصوا الكورس، ونسبتهم. محتاج تتبع الإكمال يكون مفعل في الكورس، وإلا بيظهر "—".';
$string['help_courses_quiz'] = 'متوسط درجات الطلاب الحاليين في الاختبارات (كل درجة كنسبة من الدرجة النهائية للاختبار).';
$string['help_courses_video'] = 'متوسط نسبة مشاهدة الطلاب الحاليين، على الفيديوهات اللي كل واحد فيهم فتحها.';
$string['help_courses_videos'] = 'عدد الفيديوهات في الكورس.';
$string['help_courses_quizzes'] = 'عدد الاختبارات في الكورس.';
$string['help_courses_price'] = 'الأسعار اللي الكورس اتشرى بيها فعلاً في الفترة: المبلغ والعملة اللي كل طالب دفعهم يوم الشراء (بعد أي خصم)، وجنبها عدد المرات لو أكتر من مرة. لو السعر اتغير بعد كده، الأرقام دي مش بتتغير.';
$string['help_courses_sales'] = 'عمليات شراء الكورس اللي اتدفعت أونلاين في الفترة. كل عملية دفع بتتحسب، حتى لو الطالب خرج أو اشترى تاني، علشان كده ممكن تبقى أكتر من عدد الطلاب.';
$string['help_courses_revenue'] = 'الفلوس اللي الطلاب دفعوها فعلاً في عمليات الشراء دي، بسعر يوم الشراء وبالعملة اللي دفعوا بيها (كل عملة لوحدها).';
$string['help_courses_refunds'] = 'عمليات شراء الكورس اللي اترجعت فلوسها في الفترة.';

$string['help_student_results_course'] = 'الكورس بتاع الصف ده.';
$string['help_student_results_quizzes'] = 'الاختبارات اللي ليه درجة فيها، من إجمالي اختبارات الكورس.';
$string['help_student_results_quiz'] = 'متوسط درجاته في اختبارات الكورس (كل درجة كنسبة من الدرجة النهائية للاختبار).';
$string['help_student_results_lastquiz'] = 'آخر مرة خلّص فيها محاولة اختبار في الكورس.';
$string['help_student_results_completion'] = 'إنجازه في الكورس: نسبة الأنشطة المتتبعة اللي خلّصها.';
$string['help_student_results_video'] = 'متوسط نسبة مشاهدته للفيديوهات اللي فتحها في الكورس.';

$string['help_videos_video'] = 'الفيديو.';
$string['help_videos_provider'] = 'المنصة اللي الفيديو مرفوع عليها.';
$string['help_videos_students'] = 'الطلاب الحاليين في الكورس.';
$string['help_videos_started'] = 'الطلاب الحاليين اللي شغّلوا أي جزء منه، ونسبتهم من الطلاب.';
$string['help_videos_finished'] = 'الطلاب الحاليين اللي شافوا 90% منه أو أكتر.';
$string['help_videos_avg'] = 'متوسط النسبة اللي اتشافت منه، عند الطلاب اللي بدأوه.';
$string['help_videos_last'] = 'آخر مرة طالب شافه.';

$string['help_sales_date'] = 'إمتى عملية الدفع بدأت.';
$string['help_sales_order'] = 'رقم الطلب عندنا.';
$string['help_sales_type'] = 'اتشرى إيه: كورس، أو اشتراك، أو باقة حصص، أو شحن محفظة.';
$string['help_sales_item'] = 'اسم اللي اتشرى.';
$string['help_sales_provider'] = 'بوابة الدفع.';
$string['help_sales_method'] = 'الطالب دفع إزاي في البوابة: كارت، أو محفظة موبايل، أو فوري…';
$string['help_sales_amount'] = 'المبلغ اللي الطالب دفعه.';
$string['help_sales_original'] = 'السعر قبل الخصم، و"—" لو مكانش فيه خصم.';
$string['help_sales_discount'] = 'قيمة الخصم من الكوبون والعرض.';
$string['help_sales_coupon'] = 'كود الكوبون اللي الطالب استخدمه.';
$string['help_sales_offer'] = 'العرض اللي اتطبق تلقائي.';
$string['help_sales_status'] = 'حالة الدفع: تم، أو منتظر، أو فشل، أو اترجع…';
$string['help_sales_reference'] = 'رقم العملية عند بوابة الدفع، علشان تدور عليها في لوحة البوابة.';
$string['help_sales_country'] = 'البلد اللي اتحدد على أساسها السعر.';
$string['help_sales_reason'] = 'سبب فشل أو إلغاء الدفع، زي ما البوابة قالت.';
$string['help_sales_count'] = 'عمليات الشراء المدفوعة في الفترة.';
$string['help_sales_revenue'] = 'الفلوس اللي جت منها، لكل عملة.';
$string['help_sales_refunded'] = 'عمليات الشراء اللي اترجعت فلوسها.';
$string['help_sales_month'] = 'الشهر.';

$string['help_subscriptions_plan'] = 'خطة الاشتراك.';
$string['help_subscriptions_type'] = 'شخصي، أو اشتراك مجموعة بعدد مقاعد.';
$string['help_subscriptions_paid'] = 'المبلغ المدفوع.';
$string['help_subscriptions_source'] = 'اتشرى أونلاين، أو الأدمن عمله.';
$string['help_subscriptions_activated'] = 'إمتى بدأ.';
$string['help_subscriptions_expires'] = 'إمتى هيخلص.';
$string['help_subscriptions_status'] = 'نشط، أو هيخلص خلال 7 أيام، أو خلص، أو اتلغى.';
$string['help_subscriptions_renewal'] = '"نعم" لو الطالب كان عنده نفس الخطة قبل كده.';
$string['help_subscriptions_plan_price'] = 'سعر الخطة دلوقتي.';
$string['help_subscriptions_plan_active'] = 'اشتراكات الخطة النشطة دلوقتي.';
$string['help_subscriptions_plan_sold'] = 'اشتراكات الخطة في الفترة.';
$string['help_subscriptions_plan_renewals'] = 'منها، كام واحد تجديد.';
$string['help_subscriptions_plan_revenue'] = 'الفلوس اللي اتدفعت فيها.';

$string['help_packages_package'] = 'باقة الحصص.';
$string['help_packages_packages'] = 'عدد الباقات اللي عند الطالب.';
$string['help_packages_bought'] = 'الـFlex اللي في الباقة (1 Flex = حصة خاصة واحدة).';
$string['help_packages_used'] = 'الـFlex اللي اتصرف على حصص اتعملت.';
$string['help_packages_reserved'] = 'الـFlex المحجوز لحصص اتحجزت ولسه ماتعملتش.';
$string['help_packages_left'] = 'الـFlex اللي الطالب لسه يقدر يستخدمه (في الباقات النشطة بس).';
$string['help_packages_expired'] = 'الـFlex اللي فضل لما الباقة خلصت، ومينفعش يتستخدم تاني.';
$string['help_packages_paid'] = 'المبلغ المدفوع.';
$string['help_packages_source'] = 'اتشرت أونلاين، أو الأدمن عملها.';
$string['help_packages_activated'] = 'إمتى بدأت.';
$string['help_packages_expires'] = 'إمتى هتخلص.';
$string['help_packages_status'] = 'نشطة، أو اتستخدمت بالكامل، أو خلصت، أو اتلغت، أو مستنية الدفع.';

$string['help_lessons_private_requested'] = 'إمتى الطالب طلب الحصة.';
$string['help_lessons_private_time'] = 'ميعاد الحصة: الميعاد المتفق عليه، أو الميعاد المطلوب لو لسه ماتأكدتش.';
$string['help_lessons_private_subject'] = 'الحصة عن إيه.';
$string['help_lessons_private_package'] = 'الباقة اللي الـFlex اتاخد منها.';
$string['help_lessons_private_duration'] = 'المدة المخططة.';
$string['help_lessons_private_status'] = 'مطلوبة، أو متأكدة، أو اتعملت، أو غياب، أو اتلغت، أو اترفضت.';
$string['help_lessons_private_reason'] = 'سبب الإلغاء أو الرفض.';
$string['help_lessons_private_flex'] = 'الـFlex حصله إيه: محجوز، أو اتصرف، أو رجع للطالب.';
$string['help_lessons_private_actualstart'] = 'الحصة بدأت فعلاً إمتى.';
$string['help_lessons_private_actualend'] = 'الحصة خلصت فعلاً إمتى.';
$string['help_lessons_private_actual'] = 'الحصة أخدت قد إيه فعلاً.';
$string['help_lessons_private_share'] = 'المدرس كسب كام من الحصة دي.';
$string['help_lessons_live_session'] = 'عنوان الحصة.';
$string['help_lessons_live_start'] = 'ميعاد بداية الحصة.';
$string['help_lessons_live_duration'] = 'المدة المخططة.';
$string['help_lessons_live_status'] = 'مجدولة، أو شغالة دلوقتي، أو خلصت، أو اتلغت.';
$string['help_lessons_live_invited'] = 'الطلاب المفروض يحضروا الحصة.';
$string['help_lessons_live_attended'] = 'الطلاب اللي دخلوا، ونسبتهم من المفروض يحضروا.';
$string['help_lessons_live_absent'] = 'الطلاب اللي كان المفروض يحضروا وما دخلوش خالص (في الحصص اللي خلصت بس).';
$string['help_lessons_live_avgminutes'] = 'متوسط المدة اللي الطلاب الحاضرين قعدوها.';
$string['help_lessons_live_teacherjoined'] = 'إمتى المدرس دخل، و"لا" لو مدخلش خالص.';
$string['help_lessons_live_late'] = 'المدرس دخل متأخر قد إيه عن ميعاد البداية.';

$string['help_discounts_type'] = 'كوبون (كود الطالب بيكتبه) أو عرض (بيتطبق تلقائي).';
$string['help_discounts_name'] = 'كود الكوبون أو اسم العرض.';
$string['help_discounts_discount'] = 'بيخصم قد إيه: مبلغ أو نسبة.';
$string['help_discounts_state'] = 'شغال ولا مقفول.';
$string['help_discounts_dates'] = 'ينفع يتستخدم من إمتى لإمتى.';
$string['help_discounts_uses'] = 'عدد مرات استخدامه في عمليات شراء مدفوعة في الفترة.';
$string['help_discounts_users'] = 'عدد الطلاب المختلفين اللي استخدموه.';
$string['help_discounts_given'] = 'إجمالي الخصم اللي اتعمل.';
$string['help_discounts_sales'] = 'اللي الطلاب دفعوه بعد الخصم.';

$string['help_codes_code'] = 'الكود.';
$string['help_codes_batch'] = 'الدفعة اللي الكود اتعمل فيها.';
$string['help_codes_item'] = 'الكود بيفتح إيه: كورس، أو درس، أو رصيد في المحفظة.';
$string['help_codes_value'] = 'قيمة الكود.';
$string['help_codes_status'] = 'مش مستخدم، أو مستخدم، أو مقفول، أو منتهي.';
$string['help_codes_usedby'] = 'الطالب اللي استخدمه.';
$string['help_codes_usedat'] = 'إمتى اتستخدم.';
$string['help_codes_expires'] = 'آخر يوم ينفع يتستخدم فيه.';
$string['help_codes_made'] = 'عدد الأكواد اللي اتعملت في الدفعة.';
$string['help_codes_used'] = 'الأكواد اللي الطلاب استخدموها.';
$string['help_codes_open'] = 'الأكواد اللي لسه ماتستخدمتش ولسه صالحة.';
$string['help_codes_disabled'] = 'الأكواد اللي الأدمن قفلها.';
$string['help_codes_expired'] = 'الأكواد اللي انتهت من غير ما تتستخدم.';
$string['help_codes_batch_value'] = 'قيمة كل الأكواد اللي اتعملت في الدفعة (عدد الأكواد × قيمة الكود).';
$string['help_codes_redeemed'] = 'قيمة الأكواد اللي الطلاب استخدموها فعلاً.';

$string['help_teacher_dues_operations'] = 'عدد العمليات في الفترة: كل درس مدفوع أو حصة خاصة المدرس أخد منها نصيب.';
$string['help_teacher_dues_unpaid'] = 'حصص خاصة الطالب غاب عنها أو لغاها متأخر: الـFlex بتاع الطالب بيتصرف، والمدرس مبياخدش حاجة، والمنصة بتاخد القيمة كلها (ده النظام المتفق عليه). الحصص دي محسوبة في "عدد العمليات" و"نصيب المنصة".';
$string['help_teacher_dues_percent'] = 'نسبة المدرس في الحصص والدروس اللي اتدفعله فيها (بتظهر من–إلى لو اتغيرت في الفترة). بتظهر "—" لو كل العمليات كانت من غير أجر.';
$string['help_teacher_dues_activities'] = 'اللي كسبه من الدروس المدفوعة في الفترة.';
$string['help_teacher_dues_lessons'] = 'اللي كسبه من الحصص الخاصة في الفترة.';
$string['help_teacher_dues_earned'] = 'كل اللي كسبه في الفترة، ومنه اللي اترجع بعد كده.';
$string['help_teacher_dues_platform'] = 'نصيب المنصة من نفس العمليات، ومنه القيمة كلها في الحصص اللي من غير أجر.';
$string['help_teacher_dues_reversed'] = 'أرباح اترجعت في الفترة (مثلاً بعد استرداد فلوس طالب).';
$string['help_teacher_dues_paid'] = 'الفلوس اللي اتحولت للمدرس في الفترة.';
$string['help_teacher_dues_lastpaid'] = 'آخر مرة اتحول فيها فلوس للمدرس.';
$string['help_teacher_dues_requested'] = 'طلبات سحب مستنية الموافقة أو الدفع.';
$string['help_teacher_dues_balance'] = 'رصيد محفظة المدرس دلوقتي.';

// What each number card means.
$string['cardmeanings'] = 'معاني الكروت';
$string['help_card_students_card_students'] = 'الطلاب اللي مطابقين للفلاتر: كل حسابات الطلاب لو مفيش فلتر، وإلا الطلاب الحاليين في الكورسات (أو اللي خرجوا منها).';
$string['help_card_students_card_activeweek'] = 'منهم، اللي دخلوا من الموقع أو التطبيق في آخر 7 أيام.';
$string['help_card_students_card_withsubscription'] = 'منهم، اللي عندهم اشتراك نشط دلوقتي.';
$string['help_card_teachers_card_teachers'] = 'مدرسين الكورسات اللي في نطاقك.';
$string['help_card_teachers_card_earned'] = 'اللي المدرسين دول كسبوه في الفترة، من غير اللي اترجع.';
$string['help_card_teachers_card_paidout'] = 'الفلوس اللي اتحولت للمدرسين دول في الفترة.';
$string['help_card_courses_card_courses'] = 'الكورسات المطابقة للفلاتر.';
$string['help_card_courses_card_students'] = 'الطلاب الحاليين في الكورسات دي، وكل طالب بيتحسب مرة واحدة.';
$string['help_card_courses_card_avgrating'] = 'متوسط النجوم في تقييمات الكورسات المقبولة، وعدد التقييمات بين القوسين.';
$string['help_card_student_results_card_enrolments'] = 'عدد صفوف الجدول: صف لكل طالب في كل كورس.';
$string['help_card_student_results_card_quizavg'] = 'متوسط متوسطات الاختبارات في الكورسات.';
$string['help_card_videos_card_videos'] = 'عدد الفيديوهات في الكورسات.';
$string['help_card_videos_card_viewers'] = 'الطلاب الحاليين اللي شغّلوا فيديو واحد على الأقل (في الفترة، لو اخترت فترة).';
$string['help_card_videos_card_avgwatched'] = 'متوسط نسبة المشاهدة، على الفيديوهات اللي الطلاب بدأوها.';
$string['help_card_sales_card_paidcount'] = 'عمليات الدفع الأونلاين اللي تمت في الفترة (ومنها اللي اترجع جزء منها).';
$string['help_card_sales_card_revenue'] = 'الفلوس اللي جت من العمليات دي، لكل عملة.';
$string['help_card_sales_card_refunded'] = 'عمليات اترجعت فلوسها بالكامل: عددها وقيمتها.';
$string['help_card_sales_card_failed'] = 'عمليات دفع فشلت، أو اتلغت، أو انتهت مدتها من غير ما تتدفع.';
$string['help_card_sales_card_codesales'] = 'قيمة أكواد الدخول اللي الطلاب استخدموها في الفترة. الأكواد بتتباع برا الموقع (مثلاً في سنتر)، فالفلوس دي ماعدتش على بوابة الدفع.';
$string['help_card_sales_card_offlinepackages'] = 'باقات حصص اتدفعت كاش أو تحويل بنكي والأدمن سجّلها، في الفترة.';
$string['help_card_sales_card_assignedsubs'] = 'إجمالي سعر الاشتراكات اللي الأدمن ضافها للطلاب بإيده في الفترة (السعر اللي اتكتب وقت الإضافة).';
$string['help_card_subscriptions_card_activesubs'] = 'الاشتراكات النشطة دلوقتي (مهما كانت الفلاتر).';
$string['help_card_subscriptions_card_endingweek'] = 'اشتراكات نشطة هتخلص خلال 7 أيام.';
$string['help_card_subscriptions_card_subssold'] = 'الاشتراكات اللي بدأت في الفترة.';
$string['help_card_subscriptions_col_renewals'] = 'منها، التجديدات: الطالب كان عنده نفس الخطة قبل كده.';
$string['help_card_subscriptions_card_revenue'] = 'الفلوس اللي اتدفعت في الاشتراكات دي.';
$string['help_card_packages_card_packagessold'] = 'باقات الحصص في الفترة (من غير اللي مستنية الدفع).';
$string['help_card_packages_col_flexbought'] = 'الـFlex اللي في الباقات دي (1 Flex = حصة خاصة واحدة).';
$string['help_card_packages_col_flexused'] = 'الـFlex اللي اتصرف على حصص اتعملت.';
$string['help_card_packages_col_flexleft'] = 'الـFlex اللي الطلاب لسه يقدروا يستخدموه (في الباقات النشطة بس).';
$string['help_card_packages_col_flexexpired'] = 'الـFlex اللي فضل في باقات خلصت، ومينفعش يتستخدم تاني.';
$string['help_card_packages_card_revenue'] = 'الفلوس اللي اتدفعت في الباقات دي.';
$string['help_card_packages_card_refundedvalue'] = 'قيمة الـFlex اللي رجعت لمحافظ الطلاب في الفترة.';
$string['help_card_lessons_private_card_lessons'] = 'الحصص الخاصة في الفترة، بكل حالاتها.';
$string['help_card_lessons_private_lesson_completed'] = 'الحصص اللي اتعملت.';
$string['help_card_lessons_private_lesson_student_absent'] = 'الحصص اللي الطالب غاب عنها.';
$string['help_card_lessons_private_lesson_teacher_absent'] = 'الحصص اللي المدرس غاب عنها.';
$string['help_card_lessons_private_card_cancelled'] = 'الحصص اللي الطالب أو المدرس لغاها، أو اترفضت.';
$string['help_card_lessons_private_card_attendance'] = 'الحصص اللي اتعملت، من مجموع اللي اتعملت واللي الطالب غاب عنها.';
$string['help_card_lessons_live_card_sessions'] = 'الحصص المباشرة في الفترة، بكل حالاتها.';
$string['help_card_lessons_live_live_ended'] = 'الحصص اللي خلصت.';
$string['help_card_lessons_live_live_cancelled'] = 'الحصص اللي اتلغت.';
$string['help_card_lessons_live_card_attendance'] = 'الطلاب اللي دخلوا، من كل الطلاب اللي كان المفروض يحضروا.';
$string['help_card_discounts_card_discounts'] = 'الكوبونات والعروض المطابقة للفلاتر.';
$string['help_card_discounts_col_uses'] = 'عدد مرات استخدامها في عمليات شراء مدفوعة في الفترة.';
$string['help_card_discounts_col_discountgiven'] = 'إجمالي الخصم اللي اتعمل.';
$string['help_card_discounts_card_discountsales'] = 'اللي الطلاب دفعوه بعد الخصم.';
$string['help_card_codes_col_made'] = 'الأكواد اللي اتعملت في الفترة.';
$string['help_card_codes_code_used'] = 'منها، اللي الطلاب استخدموها.';
$string['help_card_codes_code_active'] = 'منها، اللي لسه ماتستخدمتش ولسه صالحة.';
$string['help_card_codes_col_redeemed'] = 'قيمة الأكواد اللي الطلاب استخدموها.';
$string['help_card_teacher_dues_col_earned'] = 'كل اللي المدرسين كسبوه في الفترة، ومنه اللي اترجع بعد كده.';
$string['help_card_teacher_dues_col_paidout'] = 'الفلوس اللي اتحولت للمدرسين في الفترة.';
$string['help_card_teacher_dues_col_requested'] = 'طلبات سحب مستنية الموافقة أو الدفع.';
$string['help_card_teacher_dues_col_balance'] = 'رصيد محافظ المدرسين دلوقتي.';

// Privacy.
$string['privacy:metadata'] = 'التقارير بتعرض بيانات متخزنة في plugins تانية، ومش بتخزّن بيانات شخصية بنفسها.';

// Mobile API.
$string['err_reportnotfound'] = 'التقرير ده مش متاح ليك.';
