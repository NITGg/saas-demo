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
 * Arabic language strings for theme_nit.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT';
$string['choosereadme'] = 'NIT هو أساس قالب مبني على Boost لإطار عمل NIT LMS. يوفّر هذا الإصدار (M2) هيكل القالب ومسار بناء الأصول؛ ويأتي نظام التصميم والهوية البصرية في مراحل لاحقة.';
$string['configtitle'] = 'إعدادات NIT';
$string['frontpagecachettl'] = 'مدة تخزين الصفحة الرئيسية مؤقتًا';
$string['frontpagecachettl_desc'] = 'المدة التي تحتفظ فيها الصفحة الرئيسية ببطاقات المقررات وعدّادات الموقع في الذاكرة المؤقتة قبل إعادة حسابها من قاعدة البيانات. القيم الأعلى تقلّل الضغط على قاعدة البيانات في أكثر الصفحات زيارةً، لكنها تجعل الأرقام أقدم قليلًا. اضبطها على 0 لتعطيل التخزين المؤقت (إعادة الحساب في كل طلب).';
$string['foundation'] = 'الأساس';
$string['gallery'] = 'نظام تصميم NIT — معرض المكوّنات';
$string['foundation_desc'] = 'هذا إصدار الأساس (M2): قالب فرعي خفيف من Boost مع مسار بناء SCSS وJavaScript جاهز. تأتي عناصر الهوية والمكوّنات في مراحل لاحقة.';

// Colour palette (edited on the gallery page).
$string['colours'] = 'لوحة الألوان';
$string['colours_desc'] = 'حرّر لوحة ألوان الموقع من صفحة معرض نظام التصميم:';
$string['coloureditor'] = 'لوحة الألوان';
$string['coloureditor_desc'] = 'الألوان التي يُبنى منها الموقع بالكامل. يُنشر كل لون كخاصية CSS مخصّصة (<code>--nit-primary</code>، <code>--nit-navbaraccent</code>، …)، فتقرأ المكوّنات — بما فيها شريط التنقّل — لونها من هنا. اختر لونًا واحفظ لإعادة تلوين الموقع.';
$string['colourssaved'] = 'تم حفظ لوحة الألوان. أُعيد بناء CSS الخاص بالقالب.';
$string['coloursreset'] = 'أُعيدت لوحة الألوان إلى القيم الافتراضية.';
$string['savecolours'] = 'حفظ الألوان';
$string['resetcolours'] = 'إعادة إلى الافتراضي';

// Brand Colors palette (the new semantic layer — edited on the gallery page).
$string['brandcolours_desc'] = 'الألوان الدلالية التي يُبنى منها الموقع بالكامل. يُنشر كل دور كخاصية CSS مخصّصة (<code>--nit-brand-primary</code>، <code>--nit-brand-surface</code>، …) تعود افتراضيًّا إلى <strong>المجموعة 1</strong>. يمكن لأي مكوّن استخدام مجموعة أخرى بإضافة صنفها (<code>.nit-brand-2</code>، <code>.nit-brand-3</code>) — نفس أسماء المتغيّرات، بقيم تلك المجموعة. اختر لونًا واحفظ لإعادة تلوين الموقع.';
$string['brandcolourssaved'] = 'تم حفظ ألوان الهوية. أُعيد بناء CSS الخاص بالقالب.';
$string['brandcoloursreset'] = 'أُعيدت ألوان الهوية إلى القيم الافتراضية.';
$string['savebrandcolours'] = 'حفظ ألوان الهوية';
$string['resetbrandcolours'] = 'إعادة إلى الافتراضي';

// Design-system gallery tabs.
$string['tab_brandcolours'] = 'ألوان الهوية';
$string['tab_colours'] = 'الألوان';
$string['tab_fonts'] = 'الخطوط';
$string['tab_components'] = 'المكوّنات';

// Fonts (edited on the gallery page).
$string['fonts'] = 'الخطوط';
$string['fonts_desc'] = 'ارفع ملف خط (‎.ttf أو ‎.otf) لكل لغة من لغات الموقع. يُطبَّق الخط الإنجليزي عندما يكون الموقع بالإنجليزية (<code>html[lang="en"]</code>) والخط العربي عندما يكون الموقع بالعربية (<code>html[lang="ar"]</code>). الخطوط مستضافة ذاتيًا — لا يُرسَل أي طلب خارجي إطلاقًا. اترك الخانة فارغة للإبقاء على الخط الحالي؛ ويُستخدَم خط النظام المدمج حتى ترفع خطًا.';
$string['fonten'] = 'الخط الإنجليزي';
$string['fontar'] = 'الخط العربي';
$string['fonten_help'] = 'يُطبَّق عندما تكون لغة الموقع الإنجليزية.';
$string['fontar_help'] = 'يُطبَّق عندما تكون لغة الموقع العربية.';
$string['fontactive'] = 'مُفعّل';
$string['fontnone'] = 'يُستخدَم خط النظام الافتراضي.';
$string['fontpreview'] = 'معاينة';
$string['fontsampleen'] = 'The quick brown fox jumps over the lazy dog — 0123456789';
$string['fontsamplear'] = 'أبجد هوّز حطّي كلمن — نصّ تجريبي ٠١٢٣٤٥٦٧٨٩';
$string['savefonts'] = 'حفظ الخطوط';
$string['resetfonts'] = 'إزالة كل الخطوط';
$string['fontssaved'] = 'تم حفظ الخطوط. أُعيد بناء CSS الخاص بالقالب.';
$string['fontsreset'] = 'أُزيلت الخطوط. عاد الموقع إلى خط النظام الافتراضي.';
$string['fontinvalidtype'] = 'تم تجاهل {$a}: تُقبل ملفات الخطوط بصيغة ‎.ttf و‎.otf فقط.';
$string['fontuploaderror'] = 'تعذّر رفع {$a}. من فضلك حاول مرة أخرى.';

// Sign-up page.
$string['alreadyhaveaccount'] = 'لديك حساب بالفعل؟';
$string['logintoaccount'] = 'تسجيل الدخول';

// Block region.
$string['region-side-pre'] = 'يمين';

// Front page full-width block regions.
$string['region-fullwidth-top'] = 'بعرض الصفحة (أعلى)';
$string['region-above-content'] = 'أعلى المحتوى';
$string['region-below-content'] = 'أسفل المحتوى';
$string['region-fullwidth-bottom'] = 'بعرض الصفحة (أسفل)';

// Privacy.
$string['privacy:metadata'] = 'لا يخزّن قالب NIT أي بيانات شخصية.';

// صفحة تفاصيل الكورس (theme_nit\output\format_topics_renderer).
$string['acad_browse'] = 'تصفّح';
$string['acad_about'] = 'نظرة عامة';
$string['acad_skills_tab'] = 'المهارات';
$string['acad_requirements'] = 'المتطلبات';
$string['acad_modules'] = 'الوحدات';
$string['acad_instructor'] = 'المدرّب:';
$string['acad_plusmore'] = '+{$a} آخرين';
$string['acad_gotocourse'] = 'الذهاب إلى الكورس';
$string['acad_enrol'] = 'التحق الآن';
$string['acad_free'] = 'مجاني';
$string['acad_starts'] = 'يبدأ {$a}';
$string['acad_enrolledcount'] = '{$a} ملتحق بالفعل';
$string['acad_ataglance'] = 'نظرة سريعة';
$string['acad_nmodules'] = '{$a} وحدات';
$string['acad_duration'] = 'المدة';
$string['acad_nhours'] = '{$a} ساعة';
$string['acad_assessments'] = 'التقييمات';
$string['acad_nassessments'] = '{$a} تقييمات';
$string['acad_language'] = 'اللغة';
$string['acad_certificate'] = 'الشهادة';
$string['acad_certificate_sub'] = 'شهادة قابلة للمشاركة';
$string['acad_learn'] = 'ماذا ستتعلّم';
$string['acad_skills'] = 'المهارات التي ستكتسبها';
$string['acad_audience'] = 'لمن هذا الكورس';
$string['acad_prerequisites'] = 'المتطلبات المسبقة';
$string['acad_about_h'] = 'عن هذا الكورس';
$string['acad_nmodulesin'] = 'يحتوي هذا الكورس على {$a} وحدات';
$string['acad_modulen'] = 'الوحدة {$a}';
$string['acad_nitems'] = '{$a} عناصر';
$string['acad_moduledetails'] = 'تفاصيل الوحدة';
$string['acad_included'] = 'ما الذي تتضمّنه';
$string['acad_instructors'] = 'المدرّبون';
$string['acad_instructorrole'] = 'مدرّب';
$string['acad_offeredby'] = 'مقدَّم من';
// صيغ المفرد للأعداد.
$string['acad_nmodule'] = 'وحدة واحدة';
$string['acad_nhour'] = 'ساعة واحدة';
$string['acad_nassessment'] = 'تقييم واحد';
$string['acad_nitem'] = 'عنصر واحد';
$string['acad_1modulein'] = 'يحتوي هذا الكورس على وحدة واحدة';
