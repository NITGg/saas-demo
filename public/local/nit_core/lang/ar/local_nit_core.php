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
 * Arabic language strings for local_nit_core.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Core';

// Privacy.
$string['privacy:metadata'] = 'إضافة NIT Core هي طبقة SDK ولا تخزّن أي بيانات شخصية بنفسها.';

// Cache definitions.
$string['cachedef_registry'] = 'ذاكرة السجلّ المشترك لـ NIT Core';

// Audit events.
$string['event_entity_created'] = 'تم إنشاء كيان NIT';
$string['event_entity_updated'] = 'تم تحديث كيان NIT';
$string['event_entity_deleted'] = 'تم حذف كيان NIT';

// Rendering seam (M4).
$string['showwelcomepanel'] = 'إظهار لوحة الترحيب في لوحة التحكم';
$string['showwelcomepanel_desc'] = 'تنفيذ مرجعي لطبقة العرض في NIT: عند التفعيل، تظهر لوحة ترحيب (يبنيها الـ SDK ويعرضها القالب) في لوحة التحكم. مُعطّلة افتراضيًا.';
$string['welcome_greeting'] = 'أهلًا بعودتك، {$a}!';
$string['welcome_message'] = 'إليك لمحة سريعة عن تعلّمك.';
$string['welcome_cta'] = 'الذهاب إلى مقرراتي';

// Branding (M5).
$string['branding'] = 'هوية NIT';
$string['brand_preset'] = 'القالب الجاهز للهوية';
$string['brand_preset_desc'] = 'اختر قالبًا جاهزًا حسب القطاع. يضبط اللون الأساسي والخط والكثافة؛ ويمكنك تجاوز القيم الفردية أدناه.';
$string['brand_primary'] = 'اللون الأساسي';
$string['brand_primary_desc'] = 'يتجاوز اللون الأساسي للقالب الجاهز. اتركه فارغًا لاستخدام القالب الجاهز. يُختار لون نص مقروء تلقائيًا.';
$string['brand_accent'] = 'اللون المميّز';
$string['brand_accent_desc'] = 'لون مميّز اختياري. اتركه فارغًا لاستخدام القالب الجاهز.';
$string['brand_font'] = 'الخط';
$string['brand_font_desc'] = 'يتجاوز خط القالب الجاهز. الخطوط مستضافة ذاتيًا؛ ولا يُرسَل أي طلب خارجي.';
$string['brand_font_default'] = 'استخدام خط القالب الجاهز';
$string['brand_font_sans'] = 'بدون زوائد (Sans)';
$string['brand_font_rounded'] = 'مُدوّر';
$string['brand_font_serif'] = 'بزوائد (Serif)';
$string['preset_corporate'] = 'شركات';
$string['preset_education'] = 'تعليم';
$string['preset_medical'] = 'طبّي';
$string['preset_government'] = 'حكومي';
$string['preset_kids'] = 'أطفال';
$string['preset_university'] = 'جامعي';

// Feature flags (M6).
$string['features'] = 'ميزات NIT';
$string['flagdomain_ui'] = 'واجهة المستخدم';
$string['flagdomain_experimental'] = 'تجريبي';
$string['flag_ui_compact_mode'] = 'الوضع المضغوط';
$string['flag_ui_compact_mode_desc'] = 'علامة تجريبية: تخطيط واجهة أكثر كثافة. تثبت عمل محرّك علامات الميزات من البداية للنهاية.';
$string['flag_experimental_preview'] = 'معاينة تجريبية';
$string['flag_experimental_preview_desc'] = 'علامة تجريبية في نطاق ثانٍ، لتوضيح التجميع. بلا تأثير حتى الآن.';

// Errors.
$string['error_unknownservice'] = 'خدمة NIT غير معروفة: {$a}';
$string['error_invalidservice'] = 'الخدمة المسجّلة لـ "{$a}" ليست كائنًا صالحًا.';
