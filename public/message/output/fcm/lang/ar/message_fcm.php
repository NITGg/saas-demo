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
 * Arabic language strings for message_fcm.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'إشعارات فايربيس (FCM)';
$string['serviceaccountjson'] = 'مفتاح Service Account (محتوى ملف JSON)';
$string['serviceaccountjson_desc'] = 'افتح ملف المفتاح اللي نزّلته من Firebase (Project settings ← Service accounts ← Generate new private key) ببرنامج Notepad، وانسخ كل محتواه والصقه هنا. لو الخانة دي فيها مفتاح، مش هيتم استخدام الملف اللي تحت.';
$string['serviceaccountjson_invalid'] = 'ده مش ملف Service Account صحيح. انسخ محتوى ملف الـJSON كله من أوله لآخره.';
$string['serviceaccountfile'] ='مسار ملف Service Account JSON';
$string['serviceaccountfile_desc'] = 'مسار ملف بيانات اعتماد Firebase Service Account JSON على السيرفر. يمكن أن يكون مساراً مطلقاً أو نسبياً لمجلد المنصة. الافتراضي: ../firebase-service-account.json';
$string['project_id'] = 'معرف مشروع فايربيس (Project ID)';
$string['project_id_desc'] = 'معرف مشروع Google Cloud / Firebase. إذا تُرِك فارغاً، سيتم استخراجه تلقائياً من ملف الـ JSON.';
$string['appid'] = 'فلترة معرف التطبيق (App ID)';
$string['appid_desc'] = 'اختياري. إذا تم تحديده (مثل com.bassthalk.app)، سيتم إرسال الإشعارات فقط للأجهزة المسجلة بهذا المعرف. اتركه فارغاً للإرسال لجميع الأجهزة المسجلة.';
$string['default_title'] = 'عنوان الإشعار الافتراضي';
$string['default_title_desc'] = 'العنوان المستخدم عندما لا يحتوي الإشعار على عنوان صريح.';
$string['notconfigured'] = 'خدمة Firebase Cloud Messaging غير مهيأة بعد. يرجى توفير ملف Service Account JSON صالح في إعدادات الإضافة.';
$string['configured'] = 'خدمة Firebase Cloud Messaging مهيأة ومتصلة بنجاح.';
$string['test_page_title'] = 'اختبار وتشخيص إشعارات FCM';
$string['test_push'] = 'إرسال إشعار تجريبي';
$string['test_push_desc'] = 'إرسال إشعار فوري تجريبي للتأكد من نجاح الاتصال ووصول الإشعارات لأجهزة المستخدم.';
$string['test_user'] = 'معرف المستخدم المستلم (User ID) أو اسم المستخدم';
$string['test_title'] = 'عنوان الإشعار';
$string['test_body'] = 'نص الإشعار';
$string['test_send_btn'] = 'إرسال الإشعار التجريبي';
$string['test_success'] = 'تم إرسال الإشعار التجريبي بنجاح إلى {$a} جهاز!';
$string['test_no_devices'] = 'لم يتم العثور على أجهزة مسجلة لهذا المستخدم. تأكد من قيام المستخدم بتسجيل الدخول عبر تطبيق الموبايل وتسجيل رمز جهازه (FCM Token).';
$string['nodevices'] = 'لا توجد أجهزة هاتف مسجلة لاستقبال الإشعارات الفورية.';
$string['devicesregistered'] = 'أجهزة الهاتف المسجلة:';
$string['privacy:metadata'] = 'تقوم إضافة إشعارات فايربيس بإرسال محتوى الإشعارات ورموز الأجهزة إلى Google Firebase لتسليم الإشعارات الفورية للأجهزة المحمولة.';
