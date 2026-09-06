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
 * Arabic strings for local_nit_subscriptions. Mirrors lang/en key-for-key so the
 * manage pages, the navbar entries and the buy modal read Arabic on ar academies.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'اشتراكات NIT';
$string['feature_unavailable'] = 'غير متاح في باقتك';
$string['feature_unavailable_desc'] = 'الاشتراكات غير مضمّنة في باقتك الحالية. رقِّ أكاديميتك لتفعيلها.';
$string['managesubscriptions'] = 'إدارة الاشتراكات';
$string['managecourses']       = 'إدارة الكورسات';
$string['privacy:metadata'] = 'يخزّن ملحق اشتراكات NIT خطط الاشتراك وقواعد إتاحة الكورسات التي يحدّدها المشرفون؛ ولا يخزّن بيانات شخصية بذاته.';

// Manage courses (single-course purchases: list + "Unbuy").
$string['mc_desc']            = 'المستخدمون الذين اشتروا كورسًا منفردًا. استخدم «إلغاء الشراء» لإلغاء تسجيل المستخدم وسحب عملية الشراء.';
$string['mc_col_course']      = 'الكورس';
$string['mc_col_purchased']   = 'تاريخ الشراء';
$string['mc_none']            = 'لا توجد عمليات شراء كورسات بعد.';
$string['mc_status_enrolled'] = 'مُسجَّل';
$string['mc_status_norole']   = 'لا يوجد وصول';
$string['mc_unbuy']           = 'إلغاء الشراء';
$string['mc_unbuy_title']     = 'سحب شراء الكورس';
$string['mc_unbuy_confirm']   = 'إلغاء تسجيل <b>{$a->user}</b> من <b>{$a->course}</b> وسحب عملية الشراء؟';
$string['mc_unbuy_refund']    = 'وسم عملية الشراء كمُستردّة';
$string['mc_unbuy_success']   = 'تم سحب شراء الكورس.';
$string['mc_course_deleted']  = '(كورس محذوف)';
$string['mc_txn_notfound']    = 'عملية الشراء غير موجودة.';
$string['mc_not_active']      = 'عملية الشراء هذه غير نشطة ولا يمكن سحبها.';

// Shared UI.
$string['ui_refresh']      = 'تحديث';
$string['ui_loading']      = 'جارٍ التحميل…';
$string['ui_save']         = 'حفظ';
$string['ui_cancel']       = 'إلغاء';
$string['ui_edit']         = 'تعديل';
$string['ui_delete']       = 'حذف';
$string['ui_activate']     = 'تفعيل';
$string['ui_deactivate']   = 'إلغاء التفعيل';
$string['ui_active']       = 'نشط';
$string['ui_never']        = 'بلا انتهاء';
$string['ui_optional']     = '(اختياري)';
$string['ui_remove']       = 'إزالة';
$string['ui_search']       = 'بحث';
$string['ui_pager_info']   = 'عرض {from}–{to} من {total}';

// Package-shared column/field labels reused by the subscriptions page.
$string['pkg_col_id']        = 'المعرّف';
$string['pkg_col_name']      = 'الاسم';
$string['pkg_col_price']     = 'السعر';
$string['pkg_col_status']    = 'الحالة';
$string['pkg_col_actions']   = 'الإجراءات';
$string['pkg_col_user']      = 'المستخدم';
$string['pkg_col_pricepaid'] = 'المبلغ المدفوع';
$string['pkg_col_expiresat'] = 'ينتهي في';
$string['pkg_field_name']    = 'الاسم';
$string['pkg_field_price']   = 'السعر (ج.م)';
$string['pkg_field_name_en'] = 'الاسم (بالإنجليزية)';
$string['pkg_field_name_ar'] = 'الاسم (بالعربية)';
$string['pkg_field_desc_en'] = 'الوصف (بالإنجليزية)';
$string['pkg_field_desc_ar'] = 'الوصف (بالعربية)';
$string['pkg_unassign_paid'] = ' — مدفوع <strong>{$a}</strong>';

// Subscription plans.
$string['sub_plans_heading']  = 'خطط الاشتراك';
$string['sub_new']            = 'اشتراك جديد';
$string['sub_col_days']       = 'عدد الأيام';
$string['sub_col_courses']    = 'الكورسات';
$string['sub_col_subscription'] = 'الاشتراك';
$string['sub_field_desc']     = 'الوصف (اختياري)';
$string['sub_field_days']     = 'عدد الأيام';
$string['sub_field_b2b']      = 'متاح للشراء للشركات (B2B)';
$string['sub_seat_options']   = 'خيارات المقاعد';
$string['sub_seat_options_help'] = 'أضف خيارًا أو أكثر لسعة المستخدمين، لكل خيار نسبة خصم خاصة به. يُحسب سعر الشركات هكذا: (السعر العادي × عدد المقاعد) − الخصم.';
$string['sub_col_seats']      = 'المقاعد';
$string['sub_col_discount']   = 'نسبة الخصم %';
$string['sub_col_b2bprice']   = 'سعر الشركات';
$string['sub_seat_add']       = 'إضافة خيار مقاعد';
$string['sub_b2b_badge']      = 'شركات';

// Course availability.
$string['sub_courseavail_heading'] = 'إتاحة الكورسات في الاشتراكات';
$string['sub_courseavail_desc']    = 'اختر الكورسات وأضفها إلى اشتراك محدّد.';
$string['sub_target']              = 'الاشتراك المستهدف:';
$string['sub_select_placeholder']  = 'اختر اشتراكًا...';
$string['sub_save_courses']        = 'حفظ الكورسات في الاشتراك';
$string['sub_courses_search']      = 'ابحث في الكورسات…';
$string['sub_selectall']           = 'تحديد الكل';
$string['sub_clear']               = 'مسح التحديد';

// User subscriptions.
$string['sub_usersubs_heading']    = 'اشتراكات المستخدمين';
$string['sub_usersubs_desc']       = 'إدارة اشتراكات المستخدمين النشطة والمنتهية.';
$string['sub_unsub_title']         = 'إلغاء اشتراك مستخدم';
$string['sub_unsub_refund']        = 'استرداد المبلغ للطالب';
$string['sub_unsubscribe']         = 'إلغاء الاشتراك';
$string['sub_none_admin']          = 'لا توجد اشتراكات بعد.';
$string['sub_inactive']            = 'غير نشط';
$string['sub_edit_titled']         = 'تعديل الاشتراك رقم {$a}';
$string['sub_updated']             = 'تم تحديث الاشتراك.';
$string['sub_created']             = 'تم إنشاء الاشتراك.';
$string['sub_activated']           = 'تم التفعيل.';
$string['sub_deactivated']         = 'تم إلغاء التفعيل.';
$string['sub_deleted']             = 'تم الحذف.';
$string['sub_confirm_delete']      = 'حذف هذا الاشتراك؟ الحذف ممكن فقط إذا لم يُشترَ من قبل، ولا يمكن التراجع عنه.';
$string['sub_no_categories']       = 'لا توجد تصنيفات تحتوي على كورسات.';
$string['sub_select_target']       = 'من فضلك اختر الاشتراك المستهدف.';
$string['sub_courses_assigned']    = 'تم إسناد الكورسات بنجاح.';
$string['sub_no_usersubs']         = 'لا توجد اشتراكات مستخدمين.';
$string['sub_unsub_confirm']       = 'إلغاء اشتراك <strong>{$a->user}</strong> في <strong>{$a->name}</strong>{$a->price}؟ لا يمكن التراجع عن ذلك.';
$string['sub_unsub_success']       = 'تم إلغاء اشتراك المستخدم بنجاح.';

// Subscription statuses.
$string['sstat_active']         = 'نشط';
$string['sstat_expired']        = 'منتهٍ';
$string['sstat_cancelled']      = 'ملغى';
$string['sstat_pending']        = 'قيد الانتظار';
$string['sstat_payment_failed'] = 'فشل الدفع';

// Errors.
$string['err_subnamerequired']  = 'اسم الاشتراك مطلوب';
$string['err_subnameempty']     = 'اسم الاشتراك لا يمكن أن يكون فارغًا';
$string['err_pricenegative']    = 'لا يمكن أن يكون السعر بالسالب';
$string['err_durationpositive'] = 'عدد الأيام يجب أن يكون أكبر من صفر';
$string['err_subnotfound']      = 'الاشتراك غير موجود';
$string['err_subhaspurchases']  = 'هذا الاشتراك عليه عمليات شراء ولا يمكن حذفه. ألغِ تفعيله بدلًا من حذفه.';
$string['err_coursenotfound']   = 'الكورس غير موجود';
$string['err_seatspositive']    = 'عدد المقاعد يجب أن يكون أكبر من صفر';
$string['err_discountrange']    = 'نسبة الخصم يجب أن تكون بين 0 و100';
$string['err_status']           = 'الحالة يجب أن تكون "active" أو "inactive"';
$string['err_postrequired']     = 'هذا الإجراء يتطلب POST';
$string['err_permissiondenied'] = 'غير مصرّح لك بهذا الإجراء';
$string['err_unknownfunction']  = 'دالة غير معروفة';
$string['err_requestfailed']    = 'فشل الطلب';
$string['err_sessionexpired']   = 'انتهت الجلسة — من فضلك أعد تحميل الصفحة وسجّل الدخول مرة أخرى.';
$string['err_paymentsunavailable'] = 'بوابة الدفع غير متاحة على هذا الموقع.';
$string['err_alreadyhassubscription'] = 'لديك اشتراك نشط بالفعل.';
$string['err_checkoutfailed']   = '{$a}';

// Buy modal (home-page block checkout).
$string['sub_confirm_title']   = 'تأكيد اشتراكك';
$string['sub_confirm_intro']   = 'أنت على وشك الاشتراك في هذه الخطة. سيتم تحويلك إلى صفحة دفع آمنة لإتمام العملية.';
$string['sub_duration_label']  = 'المدة';
$string['sub_total_label']     = 'الإجمالي';
$string['sub_coupon_label']    = 'كوبون';
$string['sub_coupon_apply']    = 'تطبيق';
$string['sub_discount_label']  = 'الخصم';
$string['sub_secure_kashier']  = 'دفع آمن عبر Kashier';
$string['sub_proceed_payment'] = 'المتابعة إلى الدفع';
$string['sub_buy']             = 'اشترك';
$string['enrolled']            = 'تم تسجيلك في هذا الكورس.';

// Scheduled task + notification channel names (Site administration screens).
$string['task_send_subscription_reminders'] = 'إرسال تذكيرات قرب انتهاء الاشتراك';
$string['messageprovider:subscriptionreminder'] = 'قرب انتهاء الاشتراك';

// Tabs on the manage-subscriptions page.
$string['tab_plans']     = 'الخطط والأسعار';
$string['tab_courses']   = 'إتاحة الكورسات';
$string['tab_users']     = 'اشتراكات المستخدمين';
$string['tab_reminders'] = 'تذكيرات التجديد';

// Renewal reminders tab.
$string['rem_heading']   = 'تذكيرات التجديد';
$string['rem_desc']      = 'نبّه المشتركين قبل انتهاء خطتهم حتى يجدّدوا مبكرًا. يُرسل تذكير واحد لكل مهلة من المهل أدناه.';
$string['rem_enabled']   = 'إرسال تذكيرات قرب الانتهاء';
$string['rem_enabled_help'] = 'أوقف هذا الخيار لإيقاف كل التذكيرات. لن تفقد شيئًا — تبقى المهل محفوظة.';
$string['rem_days']      = 'أرسل تذكيرًا قبل انتهاء الخطة بهذا العدد من الأيام';
$string['rem_days_help'] = 'أضف مدخلًا لكل تنبيه، مثلًا 7 و3 و1. من يوم واحد حتى {$a} يومًا.';
$string['rem_days_add']  = 'إضافة مهلة';
$string['rem_days_none'] = 'لا توجد مهل بعد — أضف واحدة على الأقل.';
$string['rem_onexpiry']  = 'أرسل رسالة أيضًا يوم انتهاء الخطة';
$string['rem_onexpiry_help'] = 'تُرسل بعد انتهاء الخطة فعليًا، لتخبر المشترك بأن وصوله انتهى وأن تقدّمه محفوظ. مستقلة عن المهل أعلاه.';
$string['rem_day_unit']  = 'يوم قبل الانتهاء';
$string['rem_remove']    = 'حذف';
$string['rem_save']      = 'حفظ وتطبيق الآن';
$string['rem_applied']   = 'تم الحفظ. أُرسل {$a->sent} تذكير الآن، وأُزيل {$a->cleared} سجل تذكير قديم.';
$string['rem_preview']   = 'حاليًا سيصل التذكير إلى {$a->due} من {$a->active} مشترك نشط.';
$string['rem_window_note'] = 'تبدأ فترة التجديد عند أكبر مهلة: {$a} يوم قبل انتهاء الخطة.';
$string['rem_window_none'] = 'لا توجد مهلة قبل انتهاء الخطة، لذا لا يُنبَّه أحد مسبقًا — فقط الرسالة يوم الانتهاء.';
$string['rem_window_off']  = 'التذكيرات موقوفة، لذا لا يُرسل شيء.';
$string['rem_recalc_note'] = 'الحفظ يعيد فحص كل الاشتراكات النشطة فورًا: كل من تشمله المهلة الجديدة يصله التذكير الآن، وتُمسح سجلات التذكير للمهل التي حذفتها حتى يمكن إرسالها مجددًا إذا أعدتها.';
$string['rem_col_days']  = 'المهلة';
$string['err_reminderdaysrequired'] = 'أضف مهلة واحدة على الأقل، أو أوقف التذكيرات.';

// The reminder message itself, as the subscriber receives it.
$string['reminder_msg_subject'] = 'اشتراكك "{$a->plan}" ينتهي خلال {$a->days} يوم';
$string['reminder_msg_body']    = 'اشتراكك "{$a->plan}" ينتهي في {$a->expires} — أي بعد {$a->days} يوم من الآن. جدّد قبل ذلك وستبدأ الفترة الجديدة من يوم انتهاء الفترة الحالية، فلا تفقد أي وقت.';
$string['reminder_msg_small']   = 'اشتراكك ينتهي خلال {$a->days} يوم.';
$string['reminder_msg_action']  = 'جدّد اشتراكك';
$string['reminder_msg_subject_today'] = 'انتهى اشتراكك "{$a->plan}"';
$string['reminder_msg_body_today']    = 'انتهى اشتراكك "{$a->plan}" في {$a->expires}، لذا لم تعد الكورسات التي كان يغطيها متاحة لك. لم يضع شيء مما أنجزته — تقدّمك ودرجاتك وشهاداتك محفوظة، والتجديد يعيدك إلى ما توقفت عنده بالضبط.';
$string['reminder_msg_small_today']   = 'انتهى اشتراكك — جدّد لتتابع من حيث توقفت.';
