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
// T1 "Modern Minimal" course landing.
$string['acad_curriculum'] = 'المنهج';
$string['acad_nlessons'] = '{$a} درسًا';
$string['acad_1lesson'] = 'درس واحد';
$string['acad_nlearners'] = '{$a} متعلمًا';
$string['acad_1learner'] = 'متعلم واحد';
$string['acad_updated'] = 'آخر تحديث {$a}';
$string['acad_lifetime'] = 'وصول مدى الحياة';
$string['acad_buynow'] = 'اشترِ الآن';
$string['acad_insubscription'] = 'ضمن اشتراكك';
$string['acad_lockedlesson'] = 'مقفل';
$string['acad_currency'] = 'ج.م';
$string['acad_tababout'] = 'عن الكورس';
$string['acad_tabforum'] = 'المنتدى';
$string['acad_tabreviews'] = 'التقييمات';
$string['acad_nreviews'] = '{$a} تقييم';
$string['acad_ratingof'] = 'التقييم {$a} من 5';
$string['acad_ratebtn'] = 'قيّم الكورس والمدرسين';
$string['acad_noreviews'] = 'لسه مفيش تقييمات.';
$string['acad_lessons'] = 'الدروس';
$string['acad_lessonslabel'] = 'الدروس:';
$string['acad_subscribe'] = 'اشترك الآن !';
$string['acad_discount'] = 'خصم {$a}%';
$string['acad_parthide'] = 'إخفاء';
$string['acad_partshow'] = 'إظهار';
$string['acad_noitems'] = 'لا يوجد محتوى في هذا الدرس بعد';

// Inline front-page editor (theme/nit/js/editor.js + edit.php).
$string['edit_editpage'] = 'تعديل الصفحة';
$string['edit_done'] = 'تم';
$string['edit_editimage'] = 'تعديل الصورة';
$string['edit_savefailed'] = 'تعذّر الحفظ';
$string['edit_imagetoolarge'] = 'حجم الصورة كبير جداً.';
$string['edit_editlogo'] = 'تعديل الشعار';
$string['edit_editbrand'] = 'تعديل الهوية';
$string['edit_academyname'] = 'اسم الأكاديمية';
$string['edit_replacelogo'] = 'تغيير الشعار';
$string['edit_favicon'] = 'أيقونة الموقع';
$string['edit_faviconhint'] = 'الأيقونة الصغيرة الظاهرة في تبويب المتصفح. يفضّل صورة PNG مربعة.';
$string['edit_replacefavicon'] = 'تغيير أيقونة الموقع';
$string['edit_loginbg'] = 'خلفية صفحة الدخول';
$string['edit_loginbghint'] = 'تظهر خلف نموذج الدخول والتسجيل، مع تعتيم خفيف لسهولة القراءة.';
$string['edit_edithero'] = 'تعديل الغلاف';
$string['edit_replaceimage'] = 'استبدال الصورة';
$string['edit_heroheight'] = 'الارتفاع';
$string['edit_save'] = 'حفظ';
$string['edit_saving'] = 'جارٍ الحفظ…';
$string['edit_cancel'] = 'إلغاء';
$string['edit_editabout'] = 'تعديل نبذة عنا';
$string['edit_aboutpoints'] = 'النقاط';
$string['edit_aboutsubheader'] = 'العنوان الفرعي';
$string['edit_addpoint'] = 'إضافة نقطة';
$string['edit_addcourse'] = 'إضافة دورة';
$string['edit_editgallery'] = 'تعديل المعرض';
$string['edit_addimage'] = 'إضافة صورة';
$string['edit_galleryempty'] = 'لا توجد صور بعد.';
$string['edit_gallerymax'] = 'الحد الأقصى {n} صور.';
$string['upgrade_link'] = 'ترقية الباقة';
$string['edit_deleteconfirm'] = 'حذف هذه الصورة؟';
$string['edit_close'] = 'إغلاق';
$string['edit_editapps'] = 'تعديل روابط التطبيق';
$string['edit_appsandroid'] = 'رابط Google Play';
$string['edit_appsios'] = 'رابط App Store';
$string['edit_appshint'] = 'اتركه فارغًا لاستخدام رابط تطبيق NIT Academy الافتراضي لهذه الأكاديمية (يفتح التطبيق موجّهًا إليها).';
$string['edit_editcontact'] = 'تعديل التواصل';
$string['edit_c_phone'] = 'الهاتف';
$string['edit_c_whatsapp'] = 'واتساب';
$string['edit_c_facebook'] = 'رابط فيسبوك';
$string['edit_c_instagram'] = 'رابط إنستجرام';
$string['edit_c_youtube'] = 'رابط يوتيوب';
$string['edit_c_tiktok'] = 'رابط تيك توك';
$string['edit_c_website'] = 'رابط الموقع';
$string['edit_editfooter'] = 'تعديل التذييل';
$string['edit_footername'] = 'اسم الأكاديمية';
$string['edit_footerdesc'] = 'وصف';
$string['edit_footershowlogo'] = 'إظهار الشعار';
$string['edit_colours'] = 'الألوان';
$string['edit_palettenote'] = 'يتم اشتقاق النص والحدود ودرجات التمرير تلقائياً.';
$string['edit_col_primary'] = 'الأساسي';
$string['edit_col_accent'] = 'التمييز';
$string['edit_col_secondary'] = 'الثانوي';
$string['edit_col_background'] = 'الخلفية';
$string['edit_col_surface'] = 'البطاقات';
$string['edit_col_text'] = 'النص';
$string['edit_gallerydraghint'] = 'اسحب لإعادة الترتيب. تُحفظ التغييرات عند الضغط على حفظ.';
$string['edit_new'] = 'جديد';

// شريط تحميل التطبيقات (الصفحة الرئيسية، قبل التذييل).
$string['download_title'] = 'حمّل التطبيق';
$string['download_sub'] = 'تعلّم في أي وقت — حمّل تطبيق NIT Academy لأندرويد أو iOS.';
$string['download_geton'] = 'حمّله من';

// Homepage editor — design panel (v2026.09.86).
$string['edit_discard'] = 'تجاهل';
$string['edit_unsaved'] = 'لديك تغييرات غير محفوظة. هل تريد تجاهلها؟';
$string['edit_sidehint'] = 'اختر قسمًا لتعديل نصوصه وصوره وروابطه. تظهر التغييرات مباشرةً، واضغط حفظ لنشرها.';
$string['edit_pagesections'] = 'أقسام الصفحة';
$string['edit_design'] = 'التصميم';
$string['edit_settings'] = 'إعدادات';
$string['edit_moveup'] = 'نقل لأعلى';
$string['edit_movedown'] = 'نقل لأسفل';
$string['edit_hidesection'] = 'إخفاء القسم';
$string['edit_showsection'] = 'إظهار القسم';
$string['edit_resetsection'] = 'إعادة القسم إلى القالب (تُفقد تعديلاته)';
$string['edit_resetconfirm'] = 'إعادة هذا القسم إلى القالب؟ ستُفقد تعديلاته.';
$string['edit_addsection'] = 'إضافة قسم';
$string['edit_noeditable'] = 'لا يحتوي هذا القسم على نصوص أو صور قابلة للتعديل؛ فهو يعرض بيانات مباشرة.';
$string['edit_notlicensed'] = 'غير مشمول في خطتك.';
$string['edit_noreviews'] = 'لا توجد آراء مكتوبة بعد — ستظهر هنا عندما يقيّم المتعلمون دورة.';
$string['edit_noitems'] = 'لا توجد عناصر للاختيار بعد.';
$string['edit_pickshint'] = 'ألغِ التحديد لإخفاء عنصر من الصفحة الرئيسية. تحديد الكل = عرض كل شيء.';
$string['edit_selectall'] = 'الكل';
$string['edit_selectnone'] = 'لا شيء';
$string['edit_aboutcards'] = 'بطاقات المميزات (٥ كحد أقصى)';
$string['edit_cardtitle'] = 'العنوان';
$string['edit_cardtext'] = 'النص';
$string['edit_addcard'] = 'إضافة بطاقة';
$string['edit_remove'] = 'إزالة';
$string['edit_navlinks'] = 'روابط القائمة العلوية';
$string['edit_navlinkshint'] = 'اختر من ٣ إلى ٥ صفحات للقائمة العلوية بالترتيب. بدون اختيار = قائمة مودل الافتراضية.';
$string['edit_footerlinks'] = 'روابط التذييل';
$string['edit_footerlinkshint'] = 'الصفحات المعروضة في عمود روابط التذييل. بدون اختيار = افتراضيات القالب.';
$string['edit_authwelcome'] = 'عنوان الترحيب';
$string['edit_authtagline'] = 'الوصف المختصر';
$string['edit_loginpreview'] = 'معاينة صفحة الدخول';
$string['edit_col_onprimary'] = 'لون نص الأزرار';
$string['edit_auto'] = 'تلقائي';
$string['edit_toolarge'] = 'الصورة كبيرة جدًا';
$string['edit_advanced'] = 'متقدم…';
$string['edit_hidepanel'] = 'إخفاء لوحة التحرير';
$string['edit_showpanel'] = 'إظهار لوحة التحرير';
$string['edit_publish'] = 'نشر';
$string['edit_unpublished'] = 'التغييرات معروضة على الصفحة فقط. اضغط نشر لتطبيقها أو تجاهل.';
$string['edit_nochanges'] = 'لا توجد تغييرات غير منشورة.';
$string['edit_herohighlight'] = 'الكلمات المميّزة (بلون الهوية)';
$string['edit_selected'] = 'محدد';
$string['edit_pickatleast'] = 'اختر على الأقل';
$string['edit_max'] = 'الحد الأقصى';
$string['edit_structurewarn'] = 'سيغيّر هذا بنية الصفحة الآن ويتجاهل تعديلاتك غير المنشورة. متابعة؟';
$string['edit_loginpage'] = 'صفحة الدخول';
$string['edit_signuppage'] = 'صفحة التسجيل';
$string['edit_signuphint'] = 'اتركه فارغًا لاستخدام نصوص صفحة الدخول.';

// ── Design Gallery / Brand Colors suite (navbar, category, mode, logo, fonts, auth) — ported from EAAC ──
$string['brandgroupswitch'] = 'المجموعة قيد التحرير';
$string['navbarshape_usage'] = 'اختر ما تشاء أو لا شيء — تُرسم العلامات المختارة معًا';
$string['navbarsize'] = 'الحجم بالبكسل';
$string['navbarweight'] = 'ثِخَن الخط';
$string['navbarglass'] = 'شفافية الخلفية';
$string['navbarglass_usage'] = 'السماح بظهور الصفحة من خلف الشريط، وبأي درجة';
$string['navbarglass_on'] = 'تفعيل الشفافية';
$string['navbarglass_degree'] = 'نسبة الشفافية';
$string['navbarscroll'] = 'النمط بعد التمرير';
$string['navbarscroll_usage'] = 'يأخذ الشريط نمط هذه المجموعة بمجرّد أن تبدأ الصفحة في التمرير';
$string['navbarscroll_same'] = 'نفس هذه المجموعة (بلا تغيير)';
$string['btnoutlinefill'] = 'تعبئة الخلفية';
$string['btnoutlinefill_usage'] = 'عند الإيقاف تكون شفافة، فيأخذ الزر لون ما يقع فوقه';
$string['tab_changestyle'] = 'تغيير النمط';
$string['tab_categorystyles'] = 'أنماط التصنيفات';
$string['categorystyles_desc'] = 'اضبط هوية كل تصنيف رئيسي مرّتين — مرّة للوضع الفاتح ومرّة للوضع الداكن — لأن هذا ما ينتقل بينه زر الوضع الفاتح/الداكن في شريط التنقّل. يحدّد <strong>النمط</strong> مجموعة ألوان الهوية التي تُعرض بها صفحات التصنيف (اضبط المجموعات نفسها في تبويب ألوان الهوية)، و<strong>الشعار</strong> هو العلامة التي تظهر في شريط التنقّل في تلك الصفحات. يرث كل ما تحت التصنيف الرئيسي هويّته: تصنيفاته الفرعية وكل مقرّراته. اترك النمط على <strong>افتراضي الموقع</strong> أو اترك الشعار فارغًا لاستخدام ما يخصّ الموقع في ذلك الوضع.';
$string['categorystyles_col_category'] = 'التصنيف';
$string['categorystyles_col_group'] = 'مجموعة الهوية';
$string['categorystyles_col_stylefor'] = 'النمط — {$a}';
$string['categorystyles_col_logofor'] = 'الشعار — {$a}';
$string['categorystyles_col_logolight'] = 'الشعار — الوضع الفاتح';
$string['categorystyles_col_logodark'] = 'الشعار — الوضع الداكن';
$string['categorystyles_sitedefault'] = 'افتراضي الموقع';
$string['categorystyles_nologo'] = 'شعار الموقع';
$string['categorystyles_removelogo'] = 'إزالة';
$string['categorystyles_col_image'] = 'الصورة';
$string['categorystyles_noimage'] = 'لا توجد صورة';
$string['categorystyles_editimage'] = 'تعيين صورة';
$string['categorystyles_none'] = 'لا توجد تصنيفات.';
$string['categorylogouploaderror'] = 'تعذّر رفع {$a}. حاول مرّة أخرى.';
$string['categorylogoinvalidtype'] = 'يجب أن يكون {$a} صورة بصيغة PNG أو JPG أو WebP أو GIF أو SVG.';
$string['categorylogotoomany'] = 'لم يُحفظ {$a} من ملفات الشعارات: هذا الخادم يقبل عددًا محدودًا من الملفات المرفوعة في الطلب الواحد. ارفع شعارات بضعة تصنيفات في كل مرّة.';
$string['savecategorygroups'] = 'حفظ أنماط التصنيفات';
$string['categorygroupssaved'] = 'تم حفظ أنماط التصنيفات.';
$string['sitestyles'] = 'أنماط الموقع';
$string['sitestyles_desc'] = 'اختر مجموعة ألوان الهوية التي يستخدمها الموقع في كل وضع عرض. يبدّل زر الوضع الفاتح/الداكن في شريط التنقّل بين الوضعين — وهو لا يحمل لوحة ألوان خاصة به، بل يختار إحدى المجموعات أدناه، فيرى الزائر عند الضغط عليه الألوان نفسها التي ضبطتها في تبويب ألوان الهوية. اجعل للوضعين مجموعتين مختلفتين؛ فإن تطابقتا لم يغيّر الزر شيئًا ولن يظهر. و«الافتراضي» هو الوضع الذي يفتح به الموقع للزائر الذي لم يضغط الزر قط؛ وبعد الضغط يتذكّر المتصفح اختياره. أمّا الصفحات داخل تصنيف له أنماطه الخاصة فتستخدم تلك الأنماط، وينتقل الزر بين زوج ذلك التصنيف.';
$string['sitestyles_col_mode'] = 'وضع العرض';
$string['sitestyles_col_group'] = 'مجموعة الهوية';
$string['sitestyles_col_default'] = 'الافتراضي';
$string['sitestyles_default_aria'] = 'فتح الموقع في {$a} للزوار الذين لم يختاروا وضعًا بعد';
$string['savesitestyles'] = 'حفظ أنماط الموقع';
$string['sitestylessaved'] = 'تم حفظ أنماط الموقع.';
$string['homechrome'] = 'إطار الصفحة الرئيسية';
$string['homechrome_desc'] = 'اختر ما إذا كان شريط التنقّل وتذييل الموقع يظهران في الصفحة الرئيسية. يؤثّر هذا على الصفحة الرئيسية فقط — وتحتفظ كل الصفحات الأخرى بهما. الجزء الذي يُوقَف يُحذف من الصفحة كليًا فلا يشغل أي مساحة. ويظهر الاثنان دائمًا أثناء تشغيل وضع التحرير، لأن مفتاح وضع التحرير وقائمة المستخدم موجودان في شريط التنقّل.';
$string['homechrome_navbar'] = 'إظهار شريط التنقّل في الصفحة الرئيسية';
$string['homechrome_navbar_desc'] = 'عند الإيقاف: تبدأ الصفحة الرئيسية من أعلى الشاشة بأول كتلة؛ ولا يُرسم فيها شريط التنقّل (الشعار والقائمة والبحث واللغة وتسجيل الدخول).';
$string['homechrome_footer'] = 'إظهار التذييل في الصفحة الرئيسية';
$string['homechrome_footer_desc'] = 'عند الإيقاف: تنتهي الصفحة الرئيسية بآخر كتلة؛ ولا يُرسم فيها شريط تذييل الموقع (بيانات التواصل وأعمدة الروابط وحقوق النشر).';
$string['savehomechrome'] = 'حفظ إطار الصفحة الرئيسية';
$string['homechromesaved'] = 'تم حفظ إطار الصفحة الرئيسية.';
$string['modelight'] = 'الوضع الفاتح';
$string['modedark'] = 'الوضع الداكن';
$string['modeswitchtolight'] = 'التبديل إلى الوضع الفاتح';
$string['modeswitchtodark'] = 'التبديل إلى الوضع الداكن';
$string['tab_authscreens'] = 'تسجيل الدخول وإنشاء الحساب';
$string['authscreens_desc'] = 'الصورة التي تظهر بجانب بطاقتَي تسجيل الدخول وإنشاء الحساب، والاقتباس الذي يُرسَم فوقها. يُرسَم شعار الموقع هناك أيضًا — وهو نفس الشعار الذي يعرضه شريط التنقّل (إدارة الموقع ← المظهر ← الشعارات)، فلا يحتاج إلى ضبطه مرتين ولا يصبح قديمًا. لا يظهر أي من ذلك تحت عرض 992 بكسل: تُخفى اللوحة على الهواتف والأجهزة اللوحية حيث يملأ النموذج الشاشة.';
$string['authimagelogin'] = 'صورة صفحة تسجيل الدخول';
$string['authimagelogin_desc'] = 'تظهر في شاشة تسجيل الدخول — وفي بقية شاشات الحساب (نسيت كلمة المرور، تأكيد البريد) ما لم تُحدَّد صورة لإنشاء الحساب بالأسفل. اترك الحقل فارغًا للإبقاء على الصورة الافتراضية المرفقة مع مودل، والتي تحمل عبارة «صورة مولّدة بالذكاء الاصطناعي».';
$string['authimagesignup'] = 'صورة صفحة إنشاء الحساب';
$string['authimagesignup_desc'] = 'تظهر في شاشة إنشاء الحساب فقط. اترك الحقل فارغًا لاستخدام صورة تسجيل الدخول هناك أيضًا.';
$string['authimageactive'] = 'قيد الاستخدام';
$string['authimagenone'] = 'لم تُرفَع أي صورة.';
$string['authimageremove'] = 'إزالة هذه الصورة عند الحفظ';
$string['authimageinvalidtype'] = 'تم تجاهل {$a}: تُقبل الصور بصيغة ‎.jpg و‎.png و‎.webp فقط.';
$string['authimageuploaderror'] = 'تعذّر رفع {$a}. من فضلك حاول مرة أخرى.';
$string['authquote'] = 'الاقتباس';
$string['authquote_desc'] = 'يُرسَم داخل بطاقة أسفل الصورة. اكتبه بكل لغة من لغات الموقع — فالمتعلّم الذي يقرأ الموقع بالعربية لا ينبغي أن يُعرَض له نصّ إنجليزي هنا. يمكن ترك أي من اللغتين فارغة؛ وتُستخدَم اللغة المكتوبة في الحالتين. واترك الاثنتين فارغتين فلا تُرسَم البطاقة أصلًا. اكتب علامات التنصيص التي تريدها — فلا يُضاف شيء نيابةً عنك.';
$string['authquotetext'] = 'نص الاقتباس';
$string['authquoteauthor'] = 'نسبة الاقتباس';
$string['authquoteauthorplaceholder'] = 'براين هربرت · قائد تربوي';
$string['saveauthscreens'] = 'حفظ شاشات الدخول والتسجيل';
$string['authscreenssaved'] = 'تم حفظ شاشتَي تسجيل الدخول وإنشاء الحساب. أُعيد بناء CSS الخاص بالقالب.';
$string['acad_level'] = 'المستوى';
$string['acad_whatlearn_q'] = 'ماذا تتعلم في هذا الكورس؟';
$string['acad_videolength'] = 'مدة الفيديو';
$string['acad_instructorlabel'] = 'المدرّب';
$string['acad_watchpromo'] = 'شاهد الفيديو التعريفي';
$string['acad_closevideo'] = 'إغلاق الفيديو';
$string['acad_hascert'] = 'شهادة معتمدة';
$string['acad_startson'] = 'يبدأ {$a}';
$string['acad_nenrolled'] = '{$a} ملتحق';
$string['acad_ilos'] = 'النتائج التعليمية المرجوة';
$string['acad_bytheend'] = 'بنهاية هذا البرنامج التدريبي ستتمكّن من';
$string['acad_aboutinstructor'] = 'عن المدرّب';
$string['acad_nyearsexp'] = '{$a} سنة خبرة';
$string['acad_1yearexp'] = 'سنة خبرة واحدة';
$string['acad_yearsexp'] = 'سنوات الخبرة';
$string['acad_speaks'] = 'يدرّس باللغة';
$string['acad_specialization'] = 'التخصص';
$string['passwordstrength'] = 'قوة كلمة المرور';
$string['passwordstrengthweak'] = 'كلمة مرور ضعيفة';
$string['passwordstrengthfair'] = 'كلمة مرور مقبولة';
$string['passwordstrengthgood'] = 'كلمة مرور جيدة';
$string['passwordstrengthstrong'] = 'كلمة مرور قوية';
$string['showpassword'] = 'إظهار كلمة المرور';
$string['hidepassword'] = 'إخفاء كلمة المرور';
$string['gatehint'] = 'يرجى إكمال جميع الحقول المطلوبة أولًا.';
$string['createaccount'] = 'أنشئ حسابك';
$string['createaccountsub'] = 'ابدأ التعلّم مع الأكاديمية.';
$string['or'] = 'أو';
$string['continuewith'] = 'المتابعة باستخدام {$a}';
$string['welcomeback'] = 'أهلاً بعودتك';
$string['welcomebacksub'] = 'سجّل الدخول لمواصلة التعلّم';
$string['loginemail'] = 'البريد الإلكتروني';
$string['loginemailplaceholder'] = 'name@example.com';
$string['forgotyourpassword'] = 'هل نسيت كلمة المرور؟';
$string['eitherorlockedbyusername'] = 'مقفل ما دام اسم المستخدم مُدخلًا — ابحث بأحدهما فقط.';
$string['eitherorlockedbyemail'] = 'مقفل ما دام عنوان البريد الإلكتروني مُدخلًا — ابحث بأحدهما فقط.';
$string['eitherorclearusername'] = 'امسح اسم المستخدم';
$string['eitherorclearemail'] = 'امسح عنوان البريد الإلكتروني';
$string['noaccount'] = 'ليس لديك حساب؟';
$string['signupnow'] = 'إنشاء حساب';
$string['continueasguest'] = 'المتابعة بصفة ضيف';
$string['navmanagement'] = 'الإدارة';
$string['navgallery'] = 'معرض التصميم';
$string['gearmenu'] = 'قائمة الترس في شريط التنقل';
$string['gearmenu_desc'] = 'أيقونة الترس في شريط التنقل تفتح قائمة من المجموعات، كل مجموعة عنوان تحته بضع صفحات — <em>التنقل</em> (مقرراتي، إدارة الموقع) و<em>الإدارة</em> (الكوبونات والعروض والاشتراكات وبقية شاشات الإدارة). المجموعات وأسماؤها باللغتين والصفحات تحت كل مجموعة وترتيبها ومن يرى كل صفحة، كلها تُضبط من النص أدناه، بنفس طريقة كتابة «عناصر القائمة المخصصة» أعلاه. مفتاح وضع التحرير، لمن يملكه، يوضع بعد المجموعة الأولى.';
$string['gearmenuitems'] = 'عناصر قائمة الترس';
$string['gearmenuitems_desc'] = '<p>سطر لكل عنصر، والأجزاء مفصولة بعلامة <code>|</code>:</p>
<ul>
<li>السطر <b>بدون</b> شرطة يبدأ مجموعة: <code>الاسم الإنجليزي|الاسم العربي</code></li>
<li>السطر <b>الذي يبدأ بشرطة</b> صفحة داخل تلك المجموعة: <code>-الاسم الإنجليزي|الاسم العربي|الرابط|لمن</code></li>
</ul>
<p>الرابط صفحة في هذا الموقع (<code>/my/courses.php</code>) أو عنوان كامل. اكتب اسمًا واحدًا فقط ويُستخدم للغتين.</p>
<p><b>لمن</b> يحدد من يرى الصفحة: <code>guest</code> (زائر غير مسجّل)، <code>user</code> (مستخدم مسجّل ليس مديرًا)، <code>admin</code> (مدير)، أو <code>all</code> (الجميع). يمكن الجمع بفاصلة: <code>guest,user</code>. اتركه فارغًا ويُستنتج من الرابط: شاشات الإدارة وصفحات إدارة الموقع للمديرين، وما عداها للجميع. ومهما كتبت، لا تظهر شاشة إدارة لمن لا يستطيع فتحها.</p>
<p>اترك المربع فارغًا لإخفاء كل المجموعات. مثال:</p>
<pre>Navigation|التصفح
-My courses|مقرراتي الدراسية|/my/courses.php|user,admin
-Site administration|إدارة الموقع|/admin/search.php|admin
-Log in|تسجيل الدخول|/login/index.php|guest
Management|الإدارة
-Manage coupons|إدارة الكوبونات|/local/nit_commerce/manage_coupons.php|admin
-Calendar|التقويم|/calendar/view.php|all</pre>';
$string['gearmenuerrornolink'] = 'السطر {$a->line} بلا رابط، فلم يُحفظ شيء: "{$a->text}". اكتب الصفحة بالشكل -الاسم الإنجليزي|الاسم العربي|الرابط مع علامة | قبل الرابط.';
$string['gearmenuerrornoname'] = 'السطر {$a->line} فيه رابط لكن بلا اسم، فلم يُحفظ شيء: "{$a->text}".';
$string['gearmenuerroraudience'] = 'السطر {$a->line} يحدد "{$a->word}" لمن يرى الصفحة وهي كلمة غير معروفة، فلم يُحفظ شيء: "{$a->text}". استخدم guest أو user أو admin أو all — أو احذف هذا الجزء.';
$string['logosize'] = 'حجم الشعار';
$string['logosize_desc'] = 'الحجم الذي يُرسم به الشعار أعلاه. <strong>حجم الشعار</strong> هو الإعداد الوحيد الذي تحتاجه أغلب المواقع: يغيّر حجم كل شعارات الموقع دفعةً واحدة مع الحفاظ على التناسب بينها. أما الارتفاعات أسفله فتضبط كل موضع على حدة، وتُضرب في هذه النسبة.';
$string['logoscale'] = 'حجم الشعار';
$string['logoscale_desc'] = 'نسبة مئوية تُطبَّق على كل شعارات الموقع — شريط التنقل، وقائمة الهاتف، والتذييل، وشاشات الدخول. القيمة 100% ترسمها بالارتفاعات الواردة أدناه، و150% تجعلها أكبر بمقدار النصف. المدى المسموح 25–400.';
$string['logoheightnavbar'] = 'ارتفاع الشعار في شريط التنقل';
$string['logoheightnavbar_desc'] = 'ارتفاع الشعار بالبكسل في الشريط العلوي لكل صفحة. يزداد ارتفاع الشريط نفسه عند الحاجة، فالقيمة الكبيرة هنا تجعل الترويسة كلها أطول بدلاً من أن يتجاوز الشعار حدودها.';
$string['logoheightdrawer'] = 'ارتفاع الشعار في قائمة الهاتف';
$string['logoheightdrawer_desc'] = 'ارتفاع الشعار بالبكسل أعلى القائمة المنزلقة، وهي ما يحلّ محلّ روابط شريط التنقل على الهاتف.';
$string['logoheightfooter'] = 'ارتفاع الشعار في التذييل';
$string['logoheightfooter_desc'] = 'أقصى ارتفاع بالبكسل للشعار في تذييل الموقع. الشعار العريض يبلغ عرض عموده قبل أن يبلغ هذا الارتفاع.';
$string['logoheightauthpanel'] = 'ارتفاع الشعار في لوحة الدخول';
$string['logoheightauthpanel_desc'] = 'أقصى ارتفاع بالبكسل للشعار المرسوم فوق الصورة المجاورة لنموذجَي الدخول والتسجيل.';
$string['logoheightauthcard'] = 'ارتفاع الشعار في نموذج الدخول';
$string['logoheightauthcard_desc'] = 'أقصى ارتفاع بالبكسل للشعار داخل بطاقتَي الدخول والتسجيل، أعلى العنوان.';
$string['logomode'] = 'شعارات الوضع الفاتح والداكن';
$string['logomode_desc'] = 'يتغيّر لون شريط التنقّل مع زر الوضع الفاتح/الداكن، والشعار المرسوم لأحدهما لا يظهر على الآخر — فالعلامة البيضاء تختفي على شريط أبيض. حدّد أدناه الوضع الذي رُسمت له الشعارات أعلاه، ثم ارفع نسخ الوضع الآخر. اترك الإعداد على <em>غير محدّد</em> ولن يُستبدل أي شعار: يستخدم الموقع الشعارات أعلاه في كل مكان تمامًا كما كان.';
$string['logosfor'] = 'الشعارات أعلاه مرسومة لـ';
$string['logosfor_desc'] = 'وضع العرض الذي يناسب الشعار والشعار المختصر وأيقونة الموقع أعلاه. تستخدم الصفحات في الوضع الآخر الملفات المرفوعة أدناه — مع الرجوع إلى ما أعلاه لأي خانة تُترك فارغة.';
$string['logosfor_unset'] = 'غير محدّد — لا تستبدل أبدًا';
$string['logosfor_dark'] = 'الوضع الداكن (علامة فاتحة على شريط داكن)';
$string['logosfor_light'] = 'الوضع الفاتح (علامة داكنة على شريط فاتح)';
$string['altlogo'] = 'شعار الوضع الآخر';
$string['altlogo_desc'] = 'الشعار الكامل، مرسومًا للوضع الذي لا تناسبه الشعارات أعلاه. اتركه فارغًا لاستخدام الشعار أعلاه في الوضعين.';
$string['altlogocompact'] = 'الشعار المختصر للوضع الآخر';
$string['altlogocompact_desc'] = 'العلامة التي تظهر في شريط التنقّل، وهي الأهم لأنها الشعار الظاهر في كل صفحة. اتركه فارغًا لاستخدام الشعار المختصر أعلاه في الوضعين.';
$string['altfavicon'] = 'أيقونة الموقع للوضع الآخر';
$string['altfavicon_desc'] = 'أيقونة تبويب المتصفح. اتركها فارغة لاستخدام الأيقونة أعلاه في الوضعين.';

// Bassthalk: الشعار الملوّن، إعدادات الفوتر، قائمة الترس.
$string['brandlogo'] = 'الشعار الملوّن (للخلفيات الفاتحة)';
$string['brandlogo_desc'] = 'الشعار الذي يظهر على الخلفيات الفاتحة: فوتر الموقع وصفحة تسجيل الدخول وصفحة إنشاء الحساب. اتركه فارغًا لاستخدام الشعار المدمج.';
$string['footerdescription'] = 'وصف الفوتر';
$string['footerdescription_desc'] = 'الجملة التي تظهر تحت الشعار في الفوتر. اتركها فارغة لإخفائها.';
$string['footercopyright'] = 'سطر حقوق النشر';
$string['footercopyright_desc'] = 'السطر العريض تحت الوصف. اكتب {year} في المكان الذي تريد أن تظهر فيه السنة الحالية. اتركه فارغًا لإخفائه.';
$string['footerpages'] = 'عمود الصفحات';
$string['footerpages_desc'] = 'الروابط التي تظهر تحت «الصفحات». الرابط الذي يبدأ بـ / هو صفحة داخل الموقع (مثل /my/)، وغير ذلك يجب أن يكون عنوانًا كاملًا يبدأ بـ http:// أو https://. خانة «يظهر لـ» تحدد من يرى الرابط.';
$string['footerpages_name'] = 'اسم الصفحة';
$string['footerpages_url'] = 'الرابط';
$string['footerpages_show'] = 'يظهر لـ';
$string['footerpages_show_all'] = 'الجميع';
$string['footerpages_show_guest'] = 'الزوار فقط (غير المسجلين)';
$string['footerpages_show_user'] = 'المستخدمون المسجلون فقط';
$string['footerpages_add'] = 'إضافة صفحة';
$string['footerpages_remove'] = 'حذف';
$string['footerpages_incomplete'] = 'كل صفحة تحتاج اسمًا ورابطًا معًا. أكمل الجزء الناقص أو احذف الصف.';
$string['footerpages_invalidurl'] = 'الرابط «{$a}» يجب أن يبدأ بـ / (صفحة داخل الموقع) أو بـ http:// أو https://.';
$string['footersocial'] = 'السوشيال ميديا';
$string['footersocial_desc'] = 'اكتب رابط أي شبكة ليظهر رمزها تحت «السوشيال ميديا». الشبكات الفارغة لا تظهر، وإذا كانت كلها فارغة يختفي العمود كله.';
$string['footersocial_facebook'] = 'رابط فيسبوك';
$string['footersocial_instagram'] = 'رابط انستجرام';
$string['footersocial_tiktok'] = 'رابط تيك توك';
$string['footersocial_youtube'] = 'رابط يوتيوب';
$string['footersocial_whatsapp'] = 'رابط واتساب';
$string['footersocial_telegram'] = 'رابط تليجرام';
// الصفحة الرئيسية (بسطتهالك).
$string['sitepages'] = 'مدير صفحات الموقع';
$string['sitepages_footer'] = 'الفوتر';
$string['sitepages_navmenus'] = 'قوائم الشريط العلوي';
$string['navmenu_bar'] = 'روابط شريط التنقل (بجانب الشعار)';
$string['navmenu_bar_desc'] = 'الروابط التي تظهر ككلمات صريحة مباشرة في شريط التنقل العلوي بين الشعار وزر البحث. الرابط الذي يبدأ بـ "/" صفحة داخل الموقع (مثل /local/nit_pages/page.php?p=about).';
$string['navmenu_gear'] = 'روابط قائمة الترس';
$string['navmenu_gear_desc'] = 'الروابط التي تظهر في قائمة الترس (⚙) في الشريط العلوي. قبل حفظ هذه القائمة تعرض القائمة صفحات Moodle الافتراضية. الرابط الذي يبدأ بـ "/" صفحة داخل الموقع. "من يراه" يحدد الدور، والصفحة نفسها تتحقق من الصلاحية.';
$string['navmenu_user'] = 'روابط قائمة الملف الشخصي';
$string['navmenu_user_desc'] = 'الروابط التي تظهر في قائمة الملف الشخصي (الصورة الرمزية). قائمة اللغة و"تبديل الدور" و"تسجيل الخروج" تبقى دائمًا في الأسفل. قبل حفظ هذه القائمة تعرض القائمة روابط Moodle الافتراضية.';
$string['navmenu_show_all'] = 'الجميع (الزوار والمسجلون)';
$string['navmenu_show_guest'] = 'الزوار فقط (غير المسجلين)';
$string['navmenu_show_user'] = 'المستخدمون المسجلون فقط';
$string['navmenu_show_student'] = 'الطلاب';
$string['navmenu_show_teacher'] = 'المدرسون';
$string['navmenu_show_admin'] = 'المديرون والمشرفون';
$string['navpagesmenu'] = 'صفحات الموقع';

// نصوص الناف بار والفوتر (بسطتهالك).
$string['bth_searchsite'] = 'ابحث في الموقع';
$string['bth_searchtitle'] = 'ابحث في كورسات الموقع ..';
$string['bth_searchlabel'] = 'ابحث';
$string['bth_searchsubmit'] = 'بحث';
$string['bth_login'] = 'تسجيل الدخول';
$string['bth_signup'] = 'حساب جديد';
$string['bth_footerpages'] = 'الصفحات';
$string['bth_footersocial'] = 'السوشيال ميديا';

// Bassthalk login card (templates/core/loginform + login_layout).
$string['bth_backhome'] = 'الرجوع للرئيسية';
$string['bth_loginheading'] = 'أهلا تاني! جاهز للمذاكرة؟';
$string['bth_loginsubtitle'] = 'ادخل علي حسابك بإدخال البريد الإلكتروني و كلمة المرور المسجل بهم من قبل';
$string['bth_loginemail'] = 'البريد الإلكتروني';
$string['bth_loginpassword'] = 'كلمة السر';
$string['bth_loginforgot'] = 'هل نسيت كلمة السر؟';
$string['bth_loginforgotlink'] = 'اضغط هنا';
$string['bth_loginsubmit'] = 'تسجيل الدخول';
$string['bth_loginnoaccount'] = 'لا يوجد لديك حساب؟';
$string['bth_logincreate'] = 'انشئ حسابك الآن !';
$string['bth_loginparent'] = 'لوحة تحكم ولي الأمر';

// Strings that were missing in Arabic (English pages showed through).
$string['homepagetemplates'] = 'قالب الصفحة الرئيسية';
$string['homepagetemplates_desc'] = 'اختر شكل الصفحة الرئيسية لهذه الأكاديمية. التطبيق يعيد كتابة أقسام الصفحة الرئيسية من القالب المختار؛ وبعدها صاحب الأكاديمية يضيف صوره ولون علامته التجارية.';
$string['applytemplate'] = 'تطبيق هذا القالب';
$string['templatereapply'] = 'إعادة التطبيق';
$string['templatecurrent'] = 'الحالي';
$string['templateapplied'] = 'تم تطبيق قالب الصفحة الرئيسية "{$a}".';
$string['templateunknown'] = 'قالب غير معروف.';
$string['viewhomepage'] = 'عرض الصفحة الرئيسية';
$string['applyconfirm'] = 'تطبيق قالب "{$a}"؟ هذا يستبدل أقسام الصفحة الرئيسية الحالية. أي صور أو نصوص مضافة لهذه الأقسام سيتم استبدالها.';
$string['applywarning'] = 'تطبيق القالب يستبدل أقسام الصفحة الرئيسية بتصميم القالب. اعمل ده قبل ما صاحب الأكاديمية يضيف صوره ومحتواه — إعادة التطبيق لاحقاً تعيد ضبط هذه الأقسام.';
$string['invalidtemplate'] = 'قالب صفحة رئيسية غير معروف: {$a}';
$string['homepagecontent'] = 'محتوى الصفحة الرئيسية';
$string['homepagecontent_desc'] = 'عدّل نصوص وصور قالب الصفحة الرئيسية لهذه الأكاديمية. الحقول ثنائية اللغة فيها إنجليزي + عربي. اترك الحقل فارغاً للإبقاء على القيمة الافتراضية للقالب. محتوى الكورسات والاشتراكات والكوبونات ديناميكي ويُملأ تلقائياً.';
$string['contentsaved'] = 'تم حفظ محتوى الصفحة الرئيسية.';
$string['contentimghint'] = 'اختر صورة لاستبدال هذه الصورة. اتركها فارغة للإبقاء على الصورة الحالية.';
$string['welcometosite'] = 'أهلاً بك في {$a}';
$string['authsidetagline'] = 'كورسات قصيرة ومنظمة يقدمها محترفون. اتعلم بالسرعة اللي تناسبك واحصل على شهادة.';
$string['loginwelcomeback'] = 'أهلاً بعودتك';
$string['logincontinue'] = 'سجّل الدخول لتكمل التعلم.';
$string['keepsignedin'] = 'تذكرني';
$string['signupcreatetitle'] = 'أنشئ حسابك';
$string['signupsubtitle'] = 'ابدأ مجاناً — بدون بطاقة.';
$string['signupstepaccount'] = 'الحساب';
$string['signupstepverify'] = 'التحقق';
$string['signupstepdone'] = 'تم';
