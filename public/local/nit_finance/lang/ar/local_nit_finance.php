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
 * Arabic language strings for local_nit_finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT للماليات';
$string['local_nit_finance:manage'] = 'إدارة ماليات منصة NIT وطلبات السحب';

// Admin page.
$string['financialreports'] = 'طلبات سحب المدرسين';
$string['platformwallet'] = 'محفظة المنصة';
$string['currentmoney'] = 'الرصيد الحالي';
$string['undistributedmoney'] = 'أموال الباقات غير الموزَّعة';
$string['teachersmoney'] = 'أموال المعلّمين';
$string['platformearnings'] = 'أرباح المنصة';
$string['totalpaidout'] = 'إجمالي المدفوع';
$string['withdrawals'] = 'طلبات السحب';
$string['nowithdrawals'] = 'لا توجد طلبات سحب.';
$string['teacher'] = 'المعلّم';
$string['amount'] = 'المبلغ';
$string['method'] = 'الطريقة';
$string['status'] = 'الحالة';
$string['actions'] = 'الإجراءات';
$string['approve'] = 'موافقة';
$string['reject'] = 'رفض';
$string['pay'] = 'تعليم كمدفوع';

// Statuses.
$string['status_pending'] = 'قيد الانتظار';
$string['status_approved'] = 'مُعتمَد';
$string['status_rejected'] = 'مرفوض';
$string['status_paid'] = 'مدفوع';

// Errors.
$string['err_amountpositive'] = 'يجب أن يكون المبلغ أكبر من صفر.';
$string['err_busy'] = 'يوجد طلب سحب آخر قيد المعالجة لهذا المعلّم. من فضلك حاول مرة أخرى بعد لحظات.';
$string['err_insufficientbalance'] = 'المبلغ المطلوب يتجاوز الرصيد المتاح.';
$string['err_withdrawalnotfound'] = 'لم يُعثَر على طلب السحب.';
$string['err_withdrawalstate'] = 'طلب السحب ليس في حالة تسمح بهذا الإجراء.';
$string['err_reasonrequired'] = 'السبب مطلوب.';
$string['err_badaction'] = 'إجراء غير معروف.';
$string['err_notdistributed'] = 'تعذّر توزيع الدرس (لا توجد عملية شراء صالحة).';
$string['err_lessonnotfound'] = 'لم يُعثَر على الدرس.';
$string['err_earningnotfound'] = 'لا يوجد ربح نشط لهذا الدرس.';
$string['err_alreadyreversed'] = 'تم عكس هذا الربح بالفعل.';

// Privacy.
$string['privacy:metadata:nit_earning'] = 'سجلّات توزيع الإيراد التي تُضاف لرصيد المعلّم مقابل درس مكتمل.';
$string['privacy:metadata:nit_earning:teacherid'] = 'المعلّم المُضاف لرصيده الربح.';
$string['privacy:metadata:nit_earning:teacher_amount_minor'] = 'حصة المعلّم، بوحدات العملة الصغرى.';
$string['privacy:metadata:nit_earning:timecreated'] = 'وقت تسجيل الربح.';
$string['privacy:metadata:nit_withdrawal'] = 'طلبات المعلّمين لسحب الأموال المكتسبة.';
$string['privacy:metadata:nit_withdrawal:teacherid'] = 'المعلّم مُقدِّم طلب السحب.';
$string['privacy:metadata:nit_withdrawal:amount_minor'] = 'المبلغ المطلوب، بوحدات العملة الصغرى.';
$string['privacy:metadata:nit_withdrawal:account'] = 'تفاصيل حساب الاستلام التي يوفّرها المعلّم.';
$string['privacy:metadata:nit_withdrawal:timecreated'] = 'وقت تقديم الطلب.';

// المحافظ وشراء الدروس والأكواد.
$string['local_nit_finance:setprice'] = 'تحديد سعر نشاط يُباع منفردًا';
$string['amountwithcurrency'] = '{$a} ج.م';
$string['financecategory'] = 'الماليات والمحافظ';
$string['earningsettings'] = 'تقسيم الأرباح';
$string['teacherpercent'] = 'نسبة المدرس الافتراضية (%)';
$string['teacherpercent_desc'] = 'النسبة التي تذهب لمحفظة المدرس من كل عملية شراء، والباقي للمنصة. المدرس الذي له نسبة خاصة في حقل الملف الشخصي "teacherpercent" تُطبَّق عليه نسبته.';
$string['walletsadmin'] = 'المحافظ';
$string['codesadmin'] = 'الأكواد';
$string['mywallet'] = 'محفظتي';
$string['saleheader'] = 'بيع الدرس منفردًا';
$string['lessonprice'] = 'سعر الدرس (ج.م)';
$string['lessonprice_help'] = 'يقدر الطالب يشتري النشاط ده لوحده بالسعر ده، من محفظته أو بكود. اتركه فارغًا ليكون مجانيًا للطلاب المشتركين في الكورس.';
$string['wallet_student'] = 'رصيد المحفظة';
$string['wallet_teacher'] = 'أرباح المدرس';
$string['wallet_platform'] = 'محفظة المنصة';
$string['redeemcode'] = 'معاك كود؟';
$string['redeemcode_desc'] = 'اكتب الكود اللي أخدته بعد الدفع علشان تفتح الدرس أو تشحن محفظتك.';
$string['codeplaceholder'] = 'XXXX-XXXX-XXXX';
$string['redeem'] = 'تفعيل الكود';
$string['mypurchases'] = 'مشترياتي';
$string['nopurchases'] = 'لسه ما اشتريتش أي حاجة.';
$string['transactions'] = 'سجل المحفظة';
$string['notransactions'] = 'لا توجد معاملات بعد.';
$string['date'] = 'التاريخ';
$string['details'] = 'التفاصيل';
$string['balanceafter'] = 'الرصيد';
$string['open'] = 'افتح';
$string['method_wallet'] = 'المحفظة';
$string['method_code'] = 'كود';
$string['kind_topup'] = 'شحن رصيد';
$string['kind_purchase'] = 'شراء';
$string['kind_earning'] = 'أرباح';
$string['kind_withdrawal'] = 'سحب';
$string['kind_adjustment'] = 'تسوية';
$string['buytitle'] = 'افتح الدرس ده';
$string['buyintro'] = 'الدرس ده بيتباع لوحده. ادفع من محفظتك أو استخدم كود.';
$string['lessonlockedcourse'] = 'الدرس ده جزء من الكورس كامل. اشترِ الكورس علشان تفتحه.';
$string['price'] = 'السعر';
$string['yourbalance'] = 'رصيدك';
$string['paywithwallet'] = 'ادفع {$a} من محفظتي';
$string['notenoughbalance'] = 'رصيدك مش كفاية. اشحن محفظتك بكود وارجع تاني.';
$string['or'] = 'أو';
$string['buycourse'] = 'اشترِ الكورس كامل';
$string['bought'] = 'تمام! "{$a}" اتفتح ليك.';
$string['toppedup'] = 'تم إضافة {$a} لمحفظتك.';
$string['backtocourse'] = 'رجوع للكورس';
$string['itemtype_cm'] = 'درس';
$string['itemtype_course'] = 'كورس';
$string['itemtype_wallet'] = 'رصيد محفظة';
$string['codes_generate'] = 'إنشاء أكواد';
$string['codes_type'] = 'الكود يفتح';
$string['codes_cm'] = 'الدرس';
$string['codes_course'] = 'الكورس';
$string['codes_amount'] = 'المبلغ المدفوع (ج.م)';
$string['codes_amount_help'] = 'المبلغ اللي الطالب دفعه لك في كل كود. في الدرس أو الكورس بيتقسم بين المدرس والمنصة وقت استخدام الكود، وفي رصيد المحفظة بيتضاف لمحفظة الطالب. لو سبته فاضي في الدرس هيتاخد سعر الدرس.';
$string['codes_count'] = 'عدد الأكواد';
$string['codes_expires'] = 'تاريخ الانتهاء';
$string['codes_note'] = 'ملاحظة';
$string['codes_created'] = 'تم إنشاء {$a} كود.';
$string['codes_download'] = 'تحميل CSV';
$string['codes_list'] = 'كل الأكواد';
$string['codes_none'] = 'لا توجد أكواد بعد.';
$string['code'] = 'الكود';
$string['item'] = 'العنصر';
$string['usedby'] = 'استخدمه';
$string['created'] = 'أُنشئ';
$string['disable'] = 'إيقاف';
$string['codestatus_active'] = 'غير مستخدم';
$string['codestatus_used'] = 'مستخدم';
$string['codestatus_disabled'] = 'موقوف';
$string['allstatuses'] = 'كل الحالات';
$string['filter'] = 'تصفية';
$string['wallets_topup'] = 'إضافة أو خصم رصيد';
$string['wallets_user'] = 'الطالب';
$string['wallets_amount'] = 'المبلغ (ج.م)';
$string['wallets_direction'] = 'العملية';
$string['wallets_add'] = 'إضافة رصيد';
$string['wallets_take'] = 'خصم رصيد';
$string['wallets_done'] = 'تم تحديث المحفظة. الرصيد الجديد: {$a}';
$string['wallets_teachers'] = 'المدرسين';
$string['wallets_students'] = 'محافظ الطلاب';
$string['wallets_ownpercent'] = 'النسبة';
$string['wallets_default'] = '{$a}% (افتراضي)';
$string['wallets_ledger'] = 'السجل';
$string['wallets_studentstotal'] = 'أرصدة الطلاب';
$string['wallets_teacherstotal'] = 'أرباح المدرسين';
$string['wallets_nostudents'] = 'لا توجد محافظ طلاب بعد.';
$string['wallets_noteachers'] = 'لا توجد أرباح مدرسين بعد.';
$string['name'] = 'الاسم';
$string['balance'] = 'الرصيد';
$string['note'] = 'ملاحظة';
$string['lessonsforsale'] = 'أو اشترِ دروس منفردة';
$string['free'] = 'مجاني';
$string['buy'] = 'شراء';
$string['owned'] = 'مفتوح';

$string['err_insufficientwallet'] = 'رصيد محفظتك مش كفاية.';
$string['err_walletbusy'] = 'في عملية دفع تانية ليك شغالة دلوقتي. جرّب كمان شوية.';
$string['err_itemnotfound'] = 'العنصر ده مش موجود.';
$string['err_notforsale'] = 'الدرس ده مش بيتباع لوحده.';
$string['err_alreadyowned'] = 'إنت معاك صلاحية على ده بالفعل.';
$string['err_noenrol'] = 'التسجيل اليدوي متوقف، فمش هينفع نفتح لك الكورس.';
$string['err_codeinvalid'] = 'الكود ده غير صحيح.';
$string['err_codeused'] = 'الكود ده اتستخدم قبل كده.';
$string['err_codeexpired'] = 'الكود ده انتهت صلاحيته.';
$string['err_codecount'] = 'تقدر تعمل من 1 لـ {$a} كود في المرة.';
$string['err_badprice'] = 'اكتب السعر بالجنيه، مثلًا 50 أو 49.50.';
$string['err_lessonlocked'] = 'الدرس ده مقفول. اشتريه علشان تفتحه.';
$string['err_chooseitem'] = 'اختار الكود هيفتح إيه.';

$string['privacy:metadata:nit_wallet'] = 'أرصدة محافظ الطلاب والمدرسين.';
$string['privacy:metadata:nit_wallet:userid'] = 'صاحب المحفظة.';
$string['privacy:metadata:nit_wallet:balance_minor'] = 'الرصيد بأصغر وحدة للعملة.';
$string['privacy:metadata:nit_purchase'] = 'ما اشتراه الطالب أو فتحه.';
$string['privacy:metadata:nit_purchase:userid'] = 'الطالب.';
$string['privacy:metadata:nit_purchase:amount_minor'] = 'المبلغ المدفوع بأصغر وحدة للعملة.';
$string['privacy:metadata:nit_access_code'] = 'الأكواد المباعة خارج المنصة ومن استخدمها.';
$string['privacy:metadata:nit_access_code:usedby'] = 'الطالب الذي استخدم الكود.';

// الشحن أونلاين.
$string['topuptitle'] = 'اشحن محفظتك أونلاين';
$string['topupdesc'] = 'ادفع بالكارت أو المحافظ الإلكترونية (فودافون كاش…) أو فوري، والمبلغ هيتضاف لمحفظتك أول ما الدفع يتم.';
$string['topupamount'] = 'المبلغ (ج.م)';
$string['topupbutton'] = 'اشحن';
$string['topupnow'] = 'اشحن محفظتك أونلاين';
$string['topupoff'] = 'الدفع أونلاين غير متاح حاليًا. جرّب لاحقًا أو استخدم كود.';
$string['topupoff_settings'] = 'إعدادات كاشير';
$string['topupoff_enable'] = 'تفعيل بوابة كاشير';

// Teacher "My earnings" page and live-lesson earnings.
$string['myearnings'] = 'أرباحي';
$string['notateacher'] = 'هذه الصفحة للمدرسين.';
$string['earn_available'] = 'الرصيد المتاح';
$string['earn_total'] = 'إجمالي الأرباح';
$string['earn_pending'] = 'طلبات سحب معلّقة';
$string['earn_withdrawn'] = 'إجمالي المسحوب';
$string['earn_request'] = 'سحب الأرباح';
$string['earn_nothingtowithdraw'] = 'لا يوجد رصيد متاح للسحب بعد.';
$string['earn_amount'] = 'المبلغ (ج.م)';
$string['earn_method'] = 'طريقة الاستلام';
$string['earn_method_bank'] = 'تحويل بنكي';
$string['earn_method_wallet'] = 'محفظة موبايل';
$string['earn_method_cash'] = 'نقدًا';
$string['earn_account'] = 'الحساب / بيانات الاستلام';
$string['earn_account_ph'] = 'رقم الحساب أو رقم المحفظة أو ملاحظة';
$string['earn_send'] = 'إرسال الطلب';
$string['earn_requested'] = 'تم إرسال طلب السحب، وستراجعه المنصة.';
$string['earn_withdrawals'] = 'طلبات السحب';
$string['earn_nowithdrawals'] = 'لا توجد طلبات سحب بعد.';
$string['earn_list'] = 'الأرباح';
$string['earn_none'] = 'لا توجد أرباح بعد.';
$string['earn_student'] = 'الطالب';
$string['earn_value'] = 'القيمة';
$string['earn_share'] = 'نصيبك';
$string['earn_sharevalue'] = '{$a->amount} ({$a->percent}%)';
$string['earn_source_lesson'] = 'حصة مباشرة رقم {$a}';
$string['earn_status_active'] = 'محسوبة';
$string['earn_status_reversed'] = 'مُلغاة';
$string['livelessonnote'] = 'حصة مباشرة رقم {$a}';
$string['livelessonreversed'] = 'إلغاء أرباح الحصة المباشرة رقم {$a}';
$string['flexexpirednote'] = 'انتهاء فلكسات غير مستخدمة (شراء باقة رقم {$a})';
$string['itemtype_package'] = 'باقة حصص';

// Mobile API (api.php).
$string['err_notateacher'] = 'محفظة الأرباح للمدرسين فقط.';
$string['err_coursenotfound'] = 'الكورس غير موجود.';
$string['err_paymentunavailable'] = 'الدفع الإلكتروني غير متاح حاليًا.';
$string['err_topuprange'] = 'مبلغ الشحن لازم يكون بين {$a->min} و {$a->max}.';
$string['err_ordernotfound'] = 'عملية الشحن غير موجودة.';
$string['err_badmethod'] = 'اختر طريقة الصرف: تحويل بنكي أو محفظة أو كاش.';
$string['err_badstatus'] = 'حالة غير معروفة.';
$string['err_badwallettype'] = 'نوع محفظة غير معروف.';
$string['err_usernotfound'] = 'المستخدم غير موجود.';
$string['err_codenotfound'] = 'الكود غير موجود.';
$string['err_codenotactive'] = 'يمكن إيقاف الكود غير المستخدم فقط.';
$string['err_badexpiry'] = 'أدخل تاريخ انتهاء في المستقبل (YYYY-MM-DD)، أو 0 بدون انتهاء.';
$string['err_amountnonzero'] = 'أدخل مبلغًا غير الصفر، مثل 50 أو -50.';

// Free preview of a paid video lesson.
$string['saleheader_video'] = 'البيع والمعاينة المجانية';
$string['previewminutes'] = 'معاينة مجانية (بالدقايق)';
$string['previewminutes_help'] = 'أول الدقايق دي من الفيديو بتشتغل مجانًا لأي حد عامل تسجيل دخول ولسه ما اشتراش الدرس أو الكورس، علشان يجرّب قبل ما يشتري. الفيديو بيقف عندها ويعرض فتح الدرس. اتركه فارغًا لو مش عايز معاينة. ينفع نص دقيقة، مثلًا 2.5.';
$string['err_badpreview'] = 'اكتب الدقايق المجانية رقم من 0 لـ {$a}، مثلًا 3 أو 2.5.';
$string['err_nopreview'] = 'الدرس ده مالوش معاينة مجانية ليك.';
$string['err_nopreviewvideo'] = 'الدرس ده مافيهوش فيديو للمعاينة.';
$string['previewtitle'] = 'معاينة مجانية';
$string['previewchip'] = 'معاينة مجانية';
$string['previewbadge'] = 'معاينة مجانية · أول {$a}';
$string['previewintro'] = 'شوف أول {$a} من الدرس ده مجانًا. افتح الدرس علشان تشوفه كله.';
$string['previewended'] = 'المعاينة المجانية خلصت';
$string['previewendedtext'] = 'افتح الدرس علشان تكمّل باقي الفيديو.';
$string['previewfailed'] = 'المعاينة ما اشتغلتش. حدّث الصفحة وجرّب تاني.';
$string['previewunlock'] = 'افتح الدرس كامل';
$string['previewwatch'] = 'شاهد مجانًا';
$string['watchpreview'] = 'شاهد أول {$a} مجانًا';
$string['freepreviews'] = 'جرّب قبل ما تشتري';
$string['previewdur_one'] = 'دقيقة';
$string['previewdur_two'] = 'دقيقتين';
$string['previewdur_few'] = '{$a} دقايق';
$string['previewdur_many'] = '{$a} دقيقة';
$string['previewdur_clock'] = '{$a} دقيقة';
