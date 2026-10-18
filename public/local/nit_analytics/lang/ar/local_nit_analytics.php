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

$string['lastndays'] = 'آخر {$a} يوم';
$string['direct'] = '(مباشر / غير معروف)';

$string['privacy:metadata:preference:utm_source'] = 'مصدر الحملة (utm_source) الذي جاء منه المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_medium'] = 'وسيلة الحملة (utm_medium) التي جاء منها المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_campaign'] = 'اسم الحملة (utm_campaign) التي جاء منها المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_term'] = 'كلمة الحملة (utm_term) التي جاء منها المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:utm_content'] = 'محتوى الحملة (utm_content) الذي جاء منه المستخدم قبل التسجيل.';
$string['privacy:metadata:preference:signup_pending'] = 'هل لم يُرسل حدث التسجيل بعد.';
$string['privacy:metadata:trackers'] = 'عند ضبطها، تُحمَّل Google Analytics وGoogle Tag Manager وMeta Pixel في المتصفح وتستقبل زيارات الصفحات وحدثي التسجيل والشراء، وذلك فقط بعد موافقة الزائر على ملفات التحليل.';
$string['privacy:metadata:trackers:pageurl'] = 'الصفحة التي تمت زيارتها.';

$string['requireconsent'] = 'اطلب الموافقة أولًا';
$string['requireconsent_desc'] = 'اعرض شريط موافقة ولا تحمّل أي أداة تتبّع (GA4 أو Tag Manager أو Pixel) حتى يوافق الزائر. أوقفه فقط حيث يسمح القانون بالتتبّع دون موافقة.';
$string['consent_text'] = 'نستخدم ملفات تعريف الارتباط التحليلية (Google وMeta) لنعرف أي الإعلانات والصفحات تجلب المتعلمين. هل تسمح بها؟';
$string['consent_accept'] = 'موافق';
$string['consent_decline'] = 'رفض';
$string['funnelreport'] = 'مسار الاكتساب';
$string['funnelintro'] = 'الزيارات القادمة من روابط الحملات، وما أدت إليه من تسجيلات ومشتريات، لكل مصدر. التسجيلات والمشتريات تُحسب للمصدر الذي جاء منه المتعلم أول مرة.';
$string['nodata'] = 'لا توجد بيانات في هذه الفترة.';
$string['downloadcsv'] = 'تنزيل CSV';
$string['col_source'] = 'المصدر';
$string['col_medium'] = 'الوسيلة';
$string['col_campaign'] = 'الحملة';
$string['col_landings'] = 'الزيارات';
$string['col_signups'] = 'التسجيلات';
$string['col_signuprate'] = 'زيارة ← تسجيل';
$string['col_buyers'] = 'المشترون';
$string['col_buyerrate'] = 'تسجيل ← شراء';
$string['col_purchases'] = 'المشتريات';
$string['col_revenue'] = 'الإيراد';
$string['privacy:metadata:preference:purchase_pending'] = 'مشتريات لم يُرسل حدث تحويلها إلى أدوات التحليل بعد.';
$string['privacy:metadata:conv'] = 'المشتريات المدفوعة، تُحفظ لقياس أي الحملات تؤدي إلى مبيعات.';
$string['privacy:metadata:conv:userid'] = 'المشتري.';
$string['privacy:metadata:conv:kind'] = 'ما تم شراؤه: كورس أو باقة حصص أو اشتراك.';
$string['privacy:metadata:conv:amount_minor'] = 'المبلغ المدفوع.';
$string['privacy:metadata:conv:currency'] = 'العملة.';
$string['privacy:metadata:conv:timecreated'] = 'وقت الشراء.';
$string['linksheading'] = 'روابط الحملات';
$string['linksintro'] = 'اصنع الرابط الذي تضعه في الإعلان أو المنشور. الزيارات والتسجيلات والمشتريات القادمة منه تظهر تحت مصدره وحملته في مسار الاكتساب وفي Google Analytics.';
$string['links_path'] = 'الصفحة';
$string['links_path_help'] = 'الصفحة التي يفتحها الرابط في الموقع، مثل / للصفحة الرئيسية أو /local/academy/course.php?id=3.';
$string['links_source'] = 'المصدر';
$string['links_source_help'] = 'مكان ظهور الرابط: google أو facebook أو instagram أو tiktok…';
$string['links_medium'] = 'الوسيلة';
$string['links_medium_help'] = 'نوع الزيارة: cpc للإعلانات المدفوعة، paid_social، social، email…';
$string['links_campaign'] = 'الحملة';
$string['links_campaign_help'] = 'اسم قصير تعرفه في التقارير، مثل oct_english.';
$string['links_content'] = 'المحتوى (اختياري)';
$string['links_content_help'] = 'للمقارنة بين إعلانين أو بانرين في نفس الحملة، مثل video_a.';
$string['links_build'] = 'اصنع الرابط';
$string['links_result'] = 'رابطك';
$string['links_copy'] = 'نسخ';
$string['links_copied'] = 'تم النسخ';
$string['links_missing'] = 'المصدر والوسيلة والحملة مطلوبة كلها.';
$string['links_googleads'] = 'إعلانات جوجل: يمكنك أيضًا ترك الوسم التلقائي مفعّلًا (يضيف gclid). هذه الزيارات تُحسب تلقائيًا كـ google / cpc، واسم الحملة يأتي من Google Ads داخل Google Analytics.';
