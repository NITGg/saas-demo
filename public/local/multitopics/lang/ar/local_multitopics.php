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
 * Arabic strings for local_multitopics.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'واجهة محتوى الكورسات (Multitopics)';
$string['mobilesettings'] = 'إعدادات تطبيق الموبايل';
$string['mobilesettings_desc'] = 'قيم تُرسل لتطبيق الموبايل بدون تسجيل دخول عبر getsettings.php (تُقرأ قبل الدخول). التوكنات خاصة بكل أكاديمية؛ و google_client_id يجب أن يكون نفسه في كل الأكاديميات.';
$string['user_token'] = 'توكن خدمة الويب للمستخدم';
$string['user_token_desc'] = 'توكن خدمة الويب الخاص بتطبيق الموبايل لهذه الأكاديمية.';
$string['admin_token'] = 'توكن الأدمن / التسجيل';
$string['admin_token_desc'] = 'توكن مشترك قبل الدخول يستخدمه التطبيق للتسجيل. خاص بكل أكاديمية — غيّر قيمة EAAC بعد الإطلاق.';
$string['google_client_id'] = 'معرّف عميل Google OAuth';
$string['google_client_id_desc'] = 'يجب أن يكون متطابقاً في كل الأكاديميات — عميل OAuth مرتبط بمعرّف حزمة التطبيق وليس بالموقع.';
$string['prevent_screen_recording'] = 'منع تسجيل الشاشة';
$string['prevent_screen_recording_desc'] = 'اطلب من التطبيق منع لقطات الشاشة وتسجيلها.';
$string['watermark'] = 'العلامة المائية (احتياطي)';
$string['watermark_desc'] = 'احتياطي فقط — التطبيق يفضّل إعداد العلامة المائية في إضافة VdoCipher عند تثبيتها.';
$string['watermark_text'] = 'نص العلامة المائية (احتياطي)';
$string['watermark_color'] = 'لون العلامة المائية';
$string['watermark_color_desc'] = '6 أرقام hex بدون # في البداية (مثال: ff3b30). أبيض (ffffff) لو تُرك فارغاً.';
$string['watermark_speed'] = 'سرعة حركة العلامة المائية';
$string['watermark_speed_desc'] = 'رقم عشري، مثال 0.002 (القيمة الافتراضية للتطبيق).';
$string['watermark_fontsize'] = 'حجم خط العلامة المائية';
$string['watermark_fontsize_desc'] = 'رقم، مثال 14.';
$string['videourl'] = 'الرابط الأساسي للفيديو';
$string['whatsapp_phone'] = 'رقم واتساب';
$string['whatsapp_message'] = 'رسالة واتساب الافتراضية';
$string['allow_paymob'] = 'السماح بالدفع عبر Paymob';
$string['android_version'] = 'أحدث إصدار أندرويد';
$string['android_url'] = 'رابط متجر أندرويد';
$string['ios_version'] = 'أحدث إصدار iOS';
$string['ios_url'] = 'رابط متجر iOS';
$string['google_login'] = 'الدخول بجوجل (احتياطي)';
$string['apple_login'] = 'الدخول بأبل (احتياطي)';
$string['facebook_login'] = 'الدخول بفيسبوك (احتياطي)';
$string['social_login_desc'] = 'احتياطي فقط — يتحكم فيه الترخيص (local_license) عند تثبيته.';
$string['server_timeout_duration'] = 'مهلة الخادم (بالثواني)';
