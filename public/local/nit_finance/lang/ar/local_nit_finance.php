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
$string['financialreports'] = 'التقارير المالية';
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
