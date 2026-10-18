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
 * Arabic strings for local_nit_analytics.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'التحليلات التسويقية';
$string['nit_analytics:viewreport'] = 'عرض التسجيلات حسب المصدر';

$string['ga4_id'] = 'معرّف القياس في Google Analytics 4';
$string['ga4_id_desc'] = 'بالشكل G-XXXXXXXXXX. اتركه فارغًا لإيقاف GA4. لا تحتاجه إذا كان GA4 يُحمَّل عبر Tag Manager.';
$string['gtm_id'] = 'معرّف حاوية Google Tag Manager';
$string['gtm_id_desc'] = 'بالشكل GTM-XXXXXXX. اتركه فارغًا لإيقاف Tag Manager.';
$string['meta_pixel_id'] = 'معرّف Meta (فيسبوك) Pixel';
$string['meta_pixel_id_desc'] = 'رقم البكسل. اتركه فارغًا لإيقاف البكسل.';
$string['trackadmins'] = 'تتبّع مديري الموقع';
$string['trackadmins_desc'] = 'متوقف افتراضيًا، حتى لا تُحسب زيارات الإدارة ضمن الزيارات.';

$string['signupsreport'] = 'التسجيلات حسب المصدر';
$string['lastndays'] = 'آخر {$a} يوم';
$string['source'] = 'المصدر';
$string['medium'] = 'الوسيلة';
$string['campaign'] = 'الحملة';
$string['signups'] = 'التسجيلات';
$string['direct'] = '(مباشر / غير معروف)';
$string['nosignups'] = 'لا توجد تسجيلات في هذه الفترة.';

$string['privacy:metadata:preference:utm_source'] = 'مصدر الحملة (utm_source) الذي جاء منه المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_medium'] = 'وسيلة الحملة (utm_medium) التي جاء منها المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_campaign'] = 'اسم الحملة (utm_campaign) التي جاء منها المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_term'] = 'كلمة الحملة (utm_term) التي جاء منها المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_content'] = 'محتوى الحملة (utm_content) الذي جاء منه المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:signup_pending'] = 'هل لم يُرسل حدث التسجيل بعد.';
$string['privacy:metadata:trackers'] = 'عند ضبطها، تُحمَّل Google Analytics وGoogle Tag Manager وMeta Pixel في المتصفح وتستقبل زيارات الصفحات وحدث التسجيل.';
$string['privacy:metadata:trackers:pageurl'] = 'الصفحة التي تمت زيارتها.';
