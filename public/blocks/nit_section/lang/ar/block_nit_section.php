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
 * Arabic language strings for block_nit_section.
 *
 * @package    block_nit_section
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'قسم NIT';
$string['newsectionblock'] = 'قسم NIT جديد';
$string['nit_section:addinstance'] = 'إضافة كتلة قسم NIT جديدة';
$string['nit_section:myaddinstance'] = 'إضافة كتلة قسم NIT جديدة إلى لوحة التحكم';

// Content.
$string['mode'] = 'وضع المحتوى';
$string['mode_help'] = 'المحرّر المرئي: اكتب نصًا منسّقًا باستخدام شريط الأدوات (مناسب للمحتوى البسيط). HTML خام: الصق الكود الذي يجب الحفاظ عليه كما هو تمامًا — القوالب والسكربتات والتنسيق الدقيق — لأن المحرّر المرئي يحذف هذه العناصر.';
$string['mode_visual'] = 'المحرّر المرئي';
$string['mode_html'] = 'HTML خام';
$string['content'] = 'المحتوى';
$string['contentraw'] = 'محتوى HTML';
$string['contentraw_help'] = 'HTML خام، يُعرَض كما هو مكتوب تمامًا (بما في ذلك القوالب والسكربتات). أشِر إلى الصور عبر رابط URL. لا يستطيع تحريره إلا الأدوار المسموح لها بإضافة هذه الكتلة.';
$string['showtitle'] = 'إظهار عنوان';
$string['configtitle'] = 'العنوان';

// Layout.
$string['layoutheading'] = 'التخطيط';

$string['width'] = 'العرض';
$string['width_help'] = 'مقدار ما تشغله هذه الكتلة من صف المنطقة. على الشاشات الصغيرة تصبح كل كتلة بعرض كامل تلقائيًا.';
$string['width_full'] = 'عرض كامل';
$string['width_twothirds'] = 'الثلثان (2/3)';
$string['width_half'] = 'النصف (1/2)';
$string['width_third'] = 'الثلث (1/3)';
$string['width_quarter'] = 'الربع (1/4)';

$string['align'] = 'الموضع الأفقي';
$string['align_help'] = 'عندما تكون الكتلة أضيق من الصف الكامل، يحدّد هذا ما إذا كانت تقع في بداية الصف أو وسطه أو نهايته. خيار "تدفّق" يسمح للكتل بالوقوف بجوار بعضها.';
$string['align_stretch'] = 'تدفّق (الوقوف بجوار الأخرى)';
$string['align_start'] = 'البداية';
$string['align_center'] = 'الوسط';
$string['align_end'] = 'النهاية';

$string['margintop'] = 'مسافة علوية';
$string['margintop_help'] = 'رقم بالوحدة المختارة أدناه (مثل 24 مع الوحدة "px"، أو 2 مع الوحدة "rem"). اتركه فارغًا للإبقاء على التباعد الافتراضي للقالب.';
$string['marginbottom'] = 'مسافة سفلية';
$string['marginbottom_help'] = 'رقم بالوحدة المختارة أدناه. اتركه فارغًا للإبقاء على التباعد الافتراضي للقالب.';
$string['spacingunit'] = 'وحدة التباعد';
$string['spacingnumeric'] = 'أدخل رقمًا فقط (مثل 24). اتركه فارغًا للقيمة الافتراضية.';

$string['attachprev'] = 'إلصاق بالكتلة أعلاه';
$string['attachprev_help'] = 'يزيل الفراغ أعلى هذه الكتلة لتلتصق مباشرةً بالكتلة التي فوقها.';

$string['plain'] = 'مبسّط (بدون إطار بطاقة)';
$string['plain_help'] = 'يزيل حدود البطاقة والخلفية والحشو ليمتد المحتوى من الحافة إلى الحافة — مفيد للبانرات الرئيسية والأقسام كاملة العرض.';

// Privacy.
$string['privacy:metadata'] = 'لا تخزّن كتلة قسم NIT سوى المحتوى المُعدّ داخل نسخة الكتلة نفسها.';
