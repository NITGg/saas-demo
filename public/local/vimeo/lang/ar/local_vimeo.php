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
 * Arabic strings for local_vimeo.
 *
 * @package    local_vimeo
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vimeo';
$string['vimeo:manage'] = 'إنشاء وتعديل وحذف فيديوهات Vimeo';
$string['vimeo:view'] = 'تشغيل فيديوهات Vimeo';
$string['credsheading'] = 'بيانات اعتماد Vimeo';
$string['credsheading_desc'] = 'بيانات حساب Vimeo. هذا توكن وصول مشترك على مستوى المنصة يتم تجهيزه لكل باقة بواسطة nit2؛ يُستخدم على الخادم فقط في الترويسة "Authorization: bearer &lt;token&gt;" ولا يُرسل أبداً لأي عميل.';
$string['access_token'] = 'توكن الوصول';
$string['access_token_desc'] = 'توكن الوصول لـ Vimeo (الصلاحيات: public, private, edit, upload, video_files). يُرسل في الترويسة "Authorization: bearer &lt;token&gt;". يتم تجهيزه تلقائياً لكل باقة بواسطة nit2.';
$string['client_id'] = 'معرّف العميل';
$string['client_id_desc'] = 'معرّف تطبيق Vimeo. محفوظ للاكتمال (هوية تطبيق OAuth)؛ الرفع والتشغيل يستخدمان توكن الوصول بالأعلى.';
$string['client_secret'] = 'سر العميل';
$string['client_secret_desc'] = 'سر تطبيق Vimeo. محفوظ للاكتمال؛ غير مطلوب للرفع أو التشغيل باستخدام توكن وصول شخصي.';
$string['apibase'] = 'الرابط الأساسي لـ API';
$string['apibase_desc'] = 'الرابط الأساسي لواجهة Vimeo REST. الافتراضي: https://api.vimeo.com';
$string['playbackheading'] = 'التشغيل والخصوصية';
$string['playbackheading_desc'] = 'فيديوهات Vimeo تُنشأ خاصة (المشاهدة معطّلة) مع خصوصية التضمين في نطاقات مسموحة فقط — تعمل فقط عند تضمينها في نطاق مسموح. لا يوجد كود تشغيل لكل مشاهدة ولا علامة مائية.';
$string['autowhitelist'] = 'السماح بهذا النطاق تلقائياً';
$string['autowhitelist_desc'] = 'عند إنشاء فيديو، يُضاف نطاق الأكاديمية لقائمة التضمين المسموح بها ليعمل الفيديو الخاص هنا. أوقفه فقط لو بتدير القائمة بطريقة أخرى.';
$string['whitelistdomain'] = 'تخصيص النطاق المسموح';
$string['whitelistdomain_desc'] = 'النطاق الذي يُضاف لقائمة التضمين لكل فيديو جديد، مثال academy.example.com. اتركه فارغاً لاستخدام نطاق هذا الموقع (من wwwroot).';
$string['diagnose'] = 'تشخيص Vimeo';
$string['err_notoken'] = 'توكن الوصول لـ Vimeo غير مضبوط. اضبطه من إدارة الموقع ← الإضافات ← الإضافات المحلية ← Vimeo.';
$string['err_nosecret'] = 'توكن الوصول لـ Vimeo غير مضبوط. اضبطه من إدارة الموقع ← الإضافات ← الإضافات المحلية ← Vimeo.';
$string['err_apifailed'] = 'فشل طلب Vimeo API: {$a}';
$string['err_novideo'] = 'لا يوجد فيديو Vimeo مرتبط بهذا النشاط.';
$string['err_noaccess'] = 'ليس لديك صلاحية لمشاهدة هذا الفيديو.';
$string['privacy:metadata'] = 'إضافة Vimeo تحفظ ربطاً بين أنشطة الكورسات ومعرّفات فيديوهات Vimeo؛ ولا تحفظ بيانات شخصية عن مشاهدي الفيديو. الفيديوهات وبياناتها محفوظة لدى Vimeo.';
$string['task_cleanup_orphans'] = 'تنظيف الفيديوهات غير المرتبطة بأي نشاط';
