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
 * Arabic strings for local_vdocipher.
 *
 * @package    local_vdocipher
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'VdoCipher';
$string['vdocipher:manage'] = 'إنشاء وتعديل وحذف فيديوهات VdoCipher';
$string['vdocipher:view'] = 'تشغيل فيديوهات VdoCipher';
$string['credsheading'] = 'بيانات اعتماد VdoCipher';
$string['credsheading_desc'] = 'بيانات API الخاصة بحساب VdoCipher. يُستخدم السر على الخادم فقط لتوقيع الطلبات وإنشاء أكواد تشغيل (OTP) قصيرة العمر؛ ولا يُرسل أبداً لأي عميل.';
$string['apisecret'] = 'سر API';
$string['apisecret_desc'] = 'سر API من لوحة تحكم VdoCipher (Config → API keys). يُرسل في الترويسة "Authorization: Apisecret &lt;key&gt;".';
$string['apibase'] = 'الرابط الأساسي لـ API';
$string['apibase_desc'] = 'الرابط الأساسي لواجهة VdoCipher REST. الافتراضي: https://dev.vdocipher.com/api';
$string['playbackheading'] = 'التشغيل والحماية';
$string['playbackheading_desc'] = 'يتحكم في كود التشغيل قصير العمر (OTP) والعلامة المائية المتحركة للمستخدم على الفيديو.';
$string['otpttl'] = 'مدة صلاحية كود التشغيل (بالثواني)';
$string['otpttl_desc'] = 'المدة التي يبقى فيها كود التشغيل صالحاً. خليها قصيرة — العميل بيطلب كود جديد قبل التشغيل مباشرة.';
$string['watermarktext'] = 'نص العلامة المائية';
$string['watermarktext_desc'] = 'النص الذي يظهر فوق الفيديو. العناصر {fullname} و {email} و {userid} تُملأ على الخادم ببيانات المشاهد، فلا يمكن للعميل تزوير العلامة المائية أو إزالتها.';
$string['watermarkenabled'] = 'تفعيل العلامة المائية';
$string['watermarkenabled_desc'] = 'إظهار هوية المشاهد على الفيديو كطبقة متحركة.';
$string['watermarkalpha'] = 'شفافية العلامة المائية';
$string['watermarkalpha_desc'] = 'شفافية نص العلامة المائية، من 0 (غير مرئي) إلى 1 (كامل). الافتراضي 0.60.';
$string['watermarksize'] = 'حجم خط العلامة المائية';
$string['watermarksize_desc'] = 'حجم خط نص العلامة المائية بالبكسل. الافتراضي 15.';
$string['diagnose'] = 'تشخيص VdoCipher';
$string['err_nosecret'] = 'سر API الخاص بـ VdoCipher غير مضبوط. اضبطه من إدارة الموقع ← الإضافات ← الإضافات المحلية ← VdoCipher.';
$string['err_apifailed'] = 'فشل طلب VdoCipher API: {$a}';
$string['err_novideo'] = 'لا يوجد فيديو VdoCipher مرتبط بهذا النشاط.';
$string['err_noaccess'] = 'ليس لديك صلاحية لمشاهدة هذا الفيديو.';
$string['task_cleanup_orphans'] = 'تنظيف الفيديوهات غير المرتبطة بأي نشاط';
