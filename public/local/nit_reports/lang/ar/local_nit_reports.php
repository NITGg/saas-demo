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
$string['report_teacher_dues'] = 'مستحقات المدرسين';

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
$string['card_codesales'] = 'أكواد اتصرفت';
$string['card_offlinepackages'] = 'مدفوعات باقات يدوية';
$string['card_assignedsubs'] = 'اشتراكات ضافها الأدمن';
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
$string['col_valuemade'] = 'قيمة المعمول';
$string['col_redeemed'] = 'قيمة المصروف';
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
$string['code_active'] = 'ماتستخدمش';
$string['code_used'] = 'مستخدم';
$string['code_disabled'] = 'موقوف';
$string['code_expired'] = 'منتهي';
$string['state_active'] = 'شغال';
$string['state_inactive'] = 'موقوف';

// Privacy.
$string['privacy:metadata'] = 'التقارير بتعرض بيانات متخزنة في plugins تانية، ومش بتخزّن بيانات شخصية بنفسها.';
