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
 * Arabic strings for local_nit_flex.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'باقات الحصص (فلكس)';
$string['local_nit_flex:managepackages'] = 'إدارة باقات الحصص';
$string['local_nit_flex:purchase'] = 'شراء باقة حصص';
$string['messageprovider:expiry'] = 'باقة الحصص الخاصة بك قاربت على الانتهاء';
$string['task_expiry'] = 'باقات الحصص: تذكير الانتهاء وإغلاق الباقات المنتهية';
$string['feature_unavailable'] = 'باقات الحصص غير متاحة في باقتك الحالية.';

// Admin.
$string['admincategory'] = 'باقات الحصص والحصص المباشرة';
$string['managepackages'] = 'إدارة باقات الحصص';
$string['packagesettings'] = 'إعدادات الباقات';
$string['expiryreminderdays'] = 'التذكير قبل الانتهاء (أيام)';
$string['expiryreminderdays_desc'] = 'كم يومًا قبل انتهاء الباقة نذكّر الطالب الذي ما زال لديه فلكس. صفر يوقف التذكير.';
$string['tab_packages'] = 'الباقات';
$string['tab_students'] = 'باقات الطلاب';
$string['newpackage'] = 'باقة جديدة';
$string['editpackage'] = 'تعديل الباقة';
$string['pkgname_ar'] = 'الاسم (عربي)';
$string['pkgname_en'] = 'الاسم (إنجليزي)';
$string['pkgdesc_ar'] = 'الوصف (عربي)';
$string['pkgdesc_en'] = 'الوصف (إنجليزي)';
$string['pkgflex'] = 'عدد الفلكس';
$string['pkgflex_help'] = 'الفلكس الواحد = حصة مباشرة فردية واحدة مع أي مدرس. قيمة الفلكس (السعر ÷ عدد الفلكس) تُقسَّم بين المدرس والمنصة عند إتمام الحصة.';
$string['pkgprice'] = 'السعر (ج.م)';
$string['pkgdays'] = 'مدة الصلاحية (أيام)';
$string['pkgdays_help'] = 'تُحسب من وقت الشراء. صفر = لا تنتهي. الفلكس المتبقي عند انتهاء الباقة ينتهي وتذهب قيمته للمنصة.';
$string['pkgactive'] = 'متاحة للبيع';
$string['package'] = 'الباقة';
$string['buyers'] = 'مرات الشراء';
$string['activate'] = 'تفعيل';
$string['deactivate'] = 'إيقاف';
$string['inactive'] = 'غير متاحة للبيع';
$string['confirmdelete'] = 'حذف هذه الباقة؟';
$string['assignpackage'] = 'إسناد باقة لطالب';
$string['assignintro'] = 'أعطِ باقة لطالب دفع خارج الموقع، أو اخصمها من محفظته. للطالب باقة واحدة نشطة فقط في نفس الوقت.';
$string['student'] = 'الطالب';
$string['assignamount'] = 'المبلغ المدفوع (ج.م)';
$string['assignamount_help'] = 'اتركه فارغًا لاستخدام سعر الباقة. هذا المبلغ يحدد قيمة كل فلكس في أرباح المدرسين.';
$string['assignmethod'] = 'طريقة الدفع';
$string['assignmethod_help'] = '"من محفظة الطالب" تخصم المبلغ من محفظة الطالب الآن. باقي الاختيارات تسجّل فقط دفعًا تم خارج الموقع.';
$string['paymethod_offline'] = 'دفع خارج الموقع';
$string['paymethod_bank'] = 'تحويل بنكي';
$string['paymethod_cash'] = 'نقدًا';
$string['paymethod_wallet'] = 'من محفظة الطالب';
$string['reference'] = 'مرجع / ملاحظة';
$string['assigned'] = 'تم إسناد الباقة إلى {$a->student} ({$a->flex} فلكس).';
$string['flexcolumn'] = 'الفلكس';
$string['flexleftshort'] = 'متبقٍ {$a->remaining} · محجوز {$a->reserved} / {$a->total}';
$string['paid'] = 'المدفوع';
$string['expires'] = 'تنتهي';
$string['refundunused'] = 'استرداد الفلكس غير المستخدم للمحفظة';
$string['unassign'] = 'إلغاء الإسناد';
$string['confirmunassign'] = 'إلغاء هذه الباقة؟ سيُحذف الفلكس المتبقي فيها.';
$string['unassigned'] = 'تم إلغاء الباقة.';
$string['unassignedrefund'] = 'تم إلغاء الباقة وإرجاع {$a} إلى محفظة الطالب.';
$string['nopurchases'] = 'لا يوجد طالب لديه باقة بعد.';
$string['refundnote'] = 'استرداد فلكس غير مستخدم';

// Student: available packages.
$string['availablepackages'] = 'الباقات المتاحة';
$string['packagesintro'] = 'الباقة تعطيك فلكس: كل فلكس يحجز لك حصة مباشرة فردية مع المدرس الذي تختاره.';
$string['walletbalance'] = 'رصيد المحفظة';
$string['topupwallet'] = 'اشحن المحفظة';
$string['activepackage'] = 'باقتك';
$string['flexleft'] = 'متبقٍ {$a->remaining} من {$a->total} فلكس';
$string['expireson'] = 'تنتهي: {$a}';
$string['onlyonepackage'] = 'يمكنك امتلاك باقة واحدة في نفس الوقت. اشترِ باقة جديدة عندما تنتهي هذه الباقة أو يُستهلك الفلكس فيها.';
$string['booklesson'] = 'احجز حصة';
$string['flexunit'] = 'فلكس';
$string['validdays'] = 'صالحة لمدة {$a} يوم';
$string['neverexpires'] = 'لا تنتهي';
$string['perflex'] = '{$a} للحصة';
$string['buypackage'] = 'اشترِ الباقة';
$string['nopackages'] = 'لا توجد باقات متاحة حاليًا.';
$string['buysummary'] = 'ستحصل على {$a} فلكس (حصة مباشرة لكل فلكس).';
$string['coupon'] = 'كود الخصم';
$string['applycoupon'] = 'تطبيق';
$string['couponapplied'] = 'تم تطبيق كود الخصم.';
$string['couponcheckfailed'] = 'تعذّر التحقق من كود الخصم. حاول مرة أخرى.';
$string['price'] = 'السعر';
$string['discount'] = 'الخصم';
$string['total'] = 'الإجمالي';
$string['paywallet'] = 'ادفع من محفظتي';
$string['notenoughwallet'] = 'رصيد محفظتك ({$a}) غير كافٍ.';
$string['payonline'] = 'ادفع أونلاين (كارت، محفظة، فوري)';
$string['msg_package_purchased'] = 'تم شراء الباقة. الفلكس جاهز — احجز أول حصة لك.';

// Statuses and history.
$string['pstat_active'] = 'نشطة';
$string['pstat_fully_used'] = 'مستهلكة بالكامل';
$string['pstat_expired'] = 'منتهية';
$string['pstat_cancelled'] = 'ملغاة';
$string['pstat_pending'] = 'قيد الانتظار';
$string['flx_reserve'] = 'محجوز';
$string['flx_consume'] = 'مستهلك';
$string['flx_return'] = 'مُعاد';
$string['flx_purchase'] = 'شراء';
$string['flx_assign'] = 'إسناد';
$string['flx_expire'] = 'منتهٍ';
$string['flx_adjust'] = 'محذوف';
$string['method_wallet'] = 'المحفظة';
$string['method_online'] = 'أونلاين';
$string['method_offline'] = 'خارج الموقع';
$string['method_bank'] = 'تحويل بنكي';
$string['method_cash'] = 'نقدًا';
$string['method_refund'] = 'استرداد';
$string['method_admin_assigned'] = 'إسناد';

// Messages.
$string['msg_expiry_subject'] = 'باقتك "{$a->package}" تنتهي يوم {$a->date}';
$string['msg_expiry_body'] = 'ما زال لديك {$a->flex} فلكس في "{$a->package}". الباقة تنتهي يوم {$a->date}: احجز حصصك قبلها، فالفلكس غير المستخدم ينتهي.';

// Errors.
$string['err_notfound'] = 'غير موجود.';
$string['err_packagenotavailable'] = 'هذه الباقة غير متاحة.';
$string['err_alreadyhaspackage'] = 'لديك باقة نشطة بالفعل. استخدمها أولًا قبل شراء باقة أخرى.';
$string['err_studentnotfound'] = 'الطالب غير موجود.';
$string['err_studenthaspackage'] = 'هذا الطالب لديه باقة نشطة بالفعل.';
$string['err_packageinuse'] = 'هذه الباقة اشتُريت من قبل ولا يمكن حذفها. أوقفها بدلًا من ذلك.';
$string['err_nameflexrequired'] = 'اكتب اسم الباقة.';
$string['err_flexpositive'] = 'اكتب عدد فلكس أكبر من صفر.';
$string['err_daysnegative'] = 'عدد الأيام لا يمكن أن يكون سالبًا.';
$string['err_noflex'] = 'لا يوجد لديك فلكس. اشترِ باقة أولًا.';
$string['err_noonline'] = 'الدفع الأونلاين غير متاح الآن. ادفع من محفظتك.';
$string['err_freeonwallet'] = 'هذه الباقة مجانية بالخصم: اختر "ادفع من محفظتي".';

// Privacy.
$string['privacy:metadata:nit_package_purchase'] = 'مشتريات الطالب من باقات الفلكس.';
$string['privacy:metadata:nit_package_purchase:userid'] = 'الطالب صاحب الشراء.';
$string['privacy:metadata:nit_package_purchase:price_paid_minor'] = 'المبلغ المدفوع بالقروش.';
$string['privacy:metadata:nit_package_purchase:timecreated'] = 'وقت الشراء.';
$string['privacy:metadata:nit_payment'] = 'مدفوعات شراء الباقات.';
$string['privacy:metadata:nit_payment:userid'] = 'المستخدم الدافع.';
$string['privacy:metadata:nit_payment:amount_minor'] = 'المبلغ المدفوع بالقروش.';
$string['privacy:metadata:nit_payment:timecreated'] = 'وقت تسجيل الدفع.';
$string['privacy:metadata:nit_flex_tx'] = 'سجل رصيد الفلكس للطالب.';
$string['privacy:metadata:nit_flex_tx:userid'] = 'الطالب الذي تغيّر رصيده.';
$string['privacy:metadata:nit_flex_tx:amount'] = 'التغيّر في رصيد الفلكس.';
$string['privacy:metadata:nit_flex_tx:timecreated'] = 'وقت التغيّر.';
