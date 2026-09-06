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
 * Arabic strings for local_nit_commerce. Mirrors lang/en key-for-key so the coupons
 * and offers pages, the navbar entries and the checkout modal read Arabic on ar academies.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'متجر NIT';
$string['feature_unavailable'] = 'غير متاح في باقتك';
$string['feature_unavailable_desc'] = 'الكوبونات والعروض غير مضمّنة في باقتك الحالية. رقِّ أكاديميتك لتفعيلها.';
$string['managecoupons'] = 'إدارة الكوبونات';
$string['manageoffers']  = 'إدارة العروض';
$string['privacy:metadata'] = 'يخزّن ملحق متجر NIT كوبونات الخصم والعروض التي يحدّدها المشرفون؛ ولا يخزّن بيانات شخصية بذاته.';

// Shared UI.
$string['ui_refresh']      = 'تحديث';
$string['ui_loading']      = 'جارٍ التحميل…';
$string['ui_save']         = 'حفظ';
$string['ui_cancel']       = 'إلغاء';
$string['ui_active']       = 'نشط';
$string['ui_activate']     = 'تفعيل';
$string['ui_deactivate']   = 'إلغاء التفعيل';
$string['ui_edit']         = 'تعديل';
$string['ui_delete']       = 'حذف';
$string['ui_never']        = 'بلا انتهاء';
$string['ui_optional']     = '(اختياري)';
$string['ui_pager_info']   = 'عرض {from}–{to} من {total}';
$string['pkg_col_status']  = 'الحالة';
$string['pkg_col_actions'] = 'الإجراءات';
$string['pkg_field_name_en'] = 'الاسم (بالإنجليزية)';
$string['pkg_field_name_ar'] = 'الاسم (بالعربية)';
$string['sub_inactive']    = 'غير نشط';

// Discount scope "all of type" labels.
$string['scope_all_course']       = 'كل الكورسات';
$string['scope_all_package']      = 'كل الباقات';
$string['scope_all_subscription'] = 'كل الاشتراكات';
$string['scope_all_program']      = 'كل البرامج';

// Coupons.
$string['cpn_new']         = 'إنشاء كوبون';
$string['cpn_none']        = 'لا توجد كوبونات بعد.';
$string['cpn_col_code']    = 'الكود';
$string['cpn_col_type']    = 'النوع';
$string['cpn_col_value']   = 'القيمة';
$string['cpn_col_scope']   = 'يُطبَّق على';
$string['cpn_col_usage']   = 'الاستخدام';
$string['cpn_col_dates']   = 'مدة الصلاحية';
$string['cpn_col_max']     = 'أقصى خصم';
$string['cpn_field_code']  = 'كود الكوبون';
$string['cpn_field_dtype'] = 'نوع الخصم';
$string['cpn_field_value'] = 'قيمة الخصم';
$string['cpn_field_max']   = 'أقصى مبلغ خصم';
$string['cpn_field_utype'] = 'نوع الاستخدام';
$string['cpn_field_limit'] = 'حد الاستخدام';
$string['cpn_field_start'] = 'تاريخ البداية';
$string['cpn_field_end']   = 'تاريخ النهاية';
$string['cpn_field_scope'] = 'العناصر المشمولة';
$string['cpn_type_percent'] = 'نسبة مئوية';
$string['cpn_type_fixed']   = 'مبلغ ثابت';
$string['cpn_usage_once']     = 'مرة واحدة';
$string['cpn_usage_multiple'] = 'متعدد الاستخدام';
$string['cpn_scope_courses']       = 'الكورسات';
$string['cpn_scope_packages']      = 'الباقات';
$string['cpn_scope_subscriptions'] = 'الاشتراكات';
$string['cpn_scope_programs']      = 'البرامج';
$string['cpn_scope_all']      = 'الكل';
$string['cpn_scope_specific'] = 'محدَّد';
$string['cpn_created']     = 'تم إنشاء الكوبون';
$string['cpn_updated']     = 'تم تحديث الكوبون';
$string['cpn_activated']   = 'تم تفعيل الكوبون';
$string['cpn_deactivated'] = 'تم إلغاء تفعيل الكوبون';
$string['cpn_deleted']     = 'تم حذف الكوبون';
$string['cpn_confirm_delete'] = 'حذف هذا الكوبون؟ لا يمكن التراجع عن ذلك.';
$string['cpn_edit_titled']    = 'تعديل الكوبون {$a}';
$string['cpn_scope_required'] = 'اختر عنصرًا واحدًا على الأقل ليُطبَّق عليه الكوبون.';
$string['cpn_unlimited']      = 'بلا حدود';
$string['cpn_used_count']     = 'استُخدم {$a}';

// Offers.
$string['ofr_new']        = 'إنشاء عرض';
$string['ofr_none']       = 'لا توجد عروض بعد.';
$string['ofr_col_name']   = 'الاسم';
$string['ofr_field_name'] = 'اسم العرض';
$string['ofr_created']     = 'تم إنشاء العرض';
$string['ofr_updated']     = 'تم تحديث العرض';
$string['ofr_activated']   = 'تم تفعيل العرض';
$string['ofr_deactivated'] = 'تم إلغاء تفعيل العرض';
$string['ofr_deleted']     = 'تم حذف العرض';
$string['ofr_confirm_delete'] = 'حذف هذا العرض؟ لا يمكن التراجع عن ذلك.';
$string['ofr_edit_titled']    = 'تعديل العرض {$a}';
$string['ofr_delete_title']   = 'حذف العرض';

// Errors.
$string['err_itemtype']            = 'نوع العنصر غير صالح.';
$string['err_itemnotfound']        = 'العنصر المطلوب غير موجود.';
$string['err_discounttype']        = 'نوع الخصم يجب أن يكون نسبة مئوية أو مبلغًا ثابتًا.';
$string['err_discountvalue']       = 'لا يمكن أن تكون قيمة الخصم بالسالب.';
$string['err_discountpercent']     = 'الخصم بالنسبة المئوية يجب أن يكون بين 0 و100.';
$string['err_maxdiscount']         = 'لا يمكن أن يكون أقصى خصم بالسالب.';
$string['err_daterange']           = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.';
$string['err_usagetype']           = 'نوع الاستخدام يجب أن يكون مرة واحدة أو متعدد الاستخدام.';
$string['err_status']              = 'الحالة يجب أن تكون "active" أو "inactive"';
$string['err_couponcoderequired']  = 'كود الكوبون مطلوب.';
$string['err_couponcodetaken']     = 'كود الكوبون هذا مستخدم بالفعل.';
$string['err_couponnotfound']      = 'الكوبون غير موجود.';
$string['err_couponinactive']      = 'هذا الكوبون غير نشط.';
$string['err_couponnotstarted']    = 'لم تبدأ صلاحية هذا الكوبون بعد.';
$string['err_couponexpired']       = 'انتهت صلاحية هذا الكوبون.';
$string['err_couponnotapplicable'] = 'هذا الكوبون لا يُطبَّق على هذا العنصر.';
$string['err_couponusedup']        = 'وصل هذا الكوبون إلى حد الاستخدام المسموح.';
$string['err_couponalreadyusedbyuser'] = 'لقد استخدمت هذا الكوبون من قبل.';
$string['err_couponbusy'] = 'هذا الكوبون قيد المعالجة في طلب آخر. من فضلك حاول بعد لحظات.';
$string['cleanupreservations'] = 'تحرير حجوزات الكوبونات المهجورة';
$string['err_couponhasusages']     = 'هذا الكوبون تم استخدامه، ويمكن إلغاء تفعيله فقط.';
$string['err_offernamerequired']   = 'اسم العرض مطلوب.';
$string['err_offernotfound']       = 'العرض غير موجود.';
$string['err_offerhasusages']      = 'هذا العرض تم استخدامه، ويمكن إلغاء تفعيله فقط.';
$string['err_postrequired']        = 'هذا الإجراء يتطلب POST';
$string['err_permissiondenied']    = 'غير مصرّح لك بهذا الإجراء';
$string['err_unknownfunction']     = 'دالة غير معروفة';
$string['err_requestfailed']       = 'فشل الطلب';
$string['err_sessionexpired']      = 'انتهت الجلسة — من فضلك أعد تحميل الصفحة وسجّل الدخول مرة أخرى.';

// Shared checkout modal (course/subscription buy).
$string['co_title']         = 'تأكيد عملية الشراء';
$string['co_intro']         = 'سيتم تحويلك إلى صفحة دفع آمنة لإتمام العملية.';
$string['co_total']         = 'الإجمالي';
$string['co_offer']         = 'العرض';
$string['co_coupon']        = 'كوبون';
$string['co_apply']         = 'تطبيق';
$string['co_discount']      = 'الخصم';
$string['co_secure']        = 'دفع آمن عبر Kashier';
$string['co_proceed']       = 'المتابعة إلى الدفع';
$string['co_cancel']        = 'إلغاء';
$string['co_loading']       = 'جارٍ التحميل…';
$string['co_coupon_failed'] = 'تعذّر تطبيق الكوبون.';
$string['co_currency']      = 'ج.م';
$string['co_buy']           = 'اشترِ الآن';
