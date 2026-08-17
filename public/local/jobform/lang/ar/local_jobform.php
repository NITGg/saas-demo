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
 * Arabic strings for local_jobform.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'استمارة التوظيف';
$string['jobform:manage'] = 'إدارة قالب حقول استمارة التوظيف وعرض الاستمارات المُرسلة';

// Admin page.
$string['managejobform'] = 'إدارة استمارة التوظيف';
$string['tabfields'] = 'الحقول';
$string['tabsubmissions'] = 'الاستمارات المُرسلة';
$string['templatefieldsheading'] = 'الحقول الافتراضية';
$string['templatefieldsintro'] = 'دي الحقول الافتراضية اللي أي نشاط "استمارة توظيف" جديد بيبدأ منها. تعديل نشاط معيّن بعد كده بيغيّر نسخته هو بس.';

// Fields table.
$string['addfield'] = 'إضافة حقل';
$string['editfield'] = 'تعديل الحقل';
$string['nofields'] = 'لسه مفيش حقول متعرّفة.';
$string['fieldname'] = 'اسم الحقل';
$string['fieldname_en'] = 'اسم الحقل (إنجليزي)';
$string['fieldname_ar'] = 'اسم الحقل (عربي)';
$string['fieldname_ar_help'] = 'اختياري. لو ملّيت اللغتين، الاسم بيتخزّن كقيمة ثنائية اللغة بصيغة {mlang} وكل طالب بيشوفه بلغته. سيبه فاضي عشان يستخدم الاسم الإنجليزي للكل.';
$string['fieldgroup'] = 'المجموعة';
$string['fieldgroup_help'] = 'اختياري. اختار مجموعة عشان الحقل ده يظهر مع باقي حقول المجموعة تحت عنوان واحد في الفورم. اختار "بدون مجموعة" عشان يفضل مستقل. تقدر تعمل مجموعات من زرار "إضافة مجموعة".';

// Groups.
$string['groups'] = 'المجموعات:';
$string['nogroups'] = 'لسه مفيش مجموعات — ضيف واحدة عشان تنظّم الحقول في أقسام.';
$string['nogroup'] = 'بدون مجموعة';
$string['addgroup'] = 'إضافة مجموعة';
$string['editgroup'] = 'تعديل المجموعة';
$string['addfieldtogroup'] = 'إضافة حقل للمجموعة دي';
$string['nofieldsingroup'] = 'لسه مفيش حقول في المجموعة دي.';
$string['groupname_en'] = 'اسم المجموعة (إنجليزي)';
$string['groupname_ar'] = 'اسم المجموعة (عربي)';
$string['confirmdeletegroup'] = 'متأكد إنك عايز تمسح المجموعة دي؟ حقولها هتبقى بدون مجموعة (مش هتتمسح).';
$string['invalidgroup'] = 'مجموعة غير صالحة.';
$string['usedefaultfields'] = 'استخدام الحقول الافتراضية';
$string['fieldtype'] = 'نوع الحقل';
$string['fielddetails'] = 'التفاصيل';
$string['fieldrequired'] = 'إجباري';
$string['fieldoptions'] = 'خيارات القائمة المنسدلة';
$string['fieldoptions_help'] = 'اكتب خيار في كل سطر. الطالب هيختار من القيم دي. عشان تترجم خيار، حط العربي بتاعه في نفس رقم السطر في خانة العربي؛ السطور بتتقابل بالترتيب.';
$string['fieldoptions_en'] = 'خيارات القائمة (إنجليزي)';
$string['fieldoptions_ar'] = 'خيارات القائمة (عربي)';
$string['optionn'] = 'الخيار {no}';
$string['optionenglish'] = 'إنجليزي';
$string['optionarabic'] = 'عربي';
$string['addoption'] = 'إضافة خيار';
$string['deleteoption'] = 'حذف';
$string['fieldmultiple'] = 'السماح باختيار متعدد';
$string['fieldsingle'] = 'اختيار واحد';
$string['fieldfixedvalue'] = 'قيمة ثابتة';
$string['fieldfixedvalue_help'] = 'قيمة للقراءة فقط بتحطها انت (الأدمن). الطالب بيشوفها بس مش بيقدر يغيّرها — بتتبعت زي ما هي. املا اللغتين عشان كل طالب يشوف القيمة بلغته.';
$string['fieldfixedvalue_en'] = 'قيمة ثابتة (إنجليزي)';
$string['fieldfixedvalue_ar'] = 'قيمة ثابتة (عربي)';
$string['actions'] = 'إجراءات';
$string['confirmdeletefield'] = 'متأكد إنك عايز تمسح الحقل ده؟';

// Field types.
$string['fieldtype_text'] = 'نص';
$string['fieldtype_number'] = 'رقم';
$string['fieldtype_email'] = 'بريد إلكتروني';
$string['fieldtype_phone'] = 'رقم هاتف';
$string['fieldtype_date'] = 'تاريخ';
$string['fieldtype_checkbox'] = 'مربع اختيار';
$string['fieldtype_url'] = 'رابط (URL)';
$string['fieldtype_select'] = 'قائمة منسدلة';
$string['fieldtype_fixed'] = 'قيمة ثابتة';

// Validation / errors.
$string['erroroptionsrequired'] = 'اكتب خيار واحد على الأقل للقائمة المنسدلة.';
$string['errorfixedvaluerequired'] = 'اكتب القيمة الثابتة.';
$string['invalidfield'] = 'حقل غير صالح.';
$string['invalidsubmission'] = 'استمارة غير صالحة.';

// Submissions.
$string['jobform'] = 'استمارة التوظيف';
$string['nosubmissions'] = 'لسه مفيش استمارات مُرسلة.';
$string['modnotinstalled'] = 'نشاط "استمارة التوظيف" لسه مش متثبّت، فمفيش استمارات نعرضها.';
$string['student'] = 'الطالب';
$string['submittedon'] = 'اتبعتت في';
$string['viewsubmission'] = 'عرض الاستمارة';
$string['answers'] = 'الإجابات';
$string['answer'] = 'الإجابة';
$string['noanswers'] = 'الاستمارة دي مفيهاش إجابات.';
$string['deletedfield'] = '(حقل متمسوح)';
$string['confirmdeletesubmission'] = 'متأكد إنك عايز تمسح الاستمارة المُرسلة دي نهائيًا؟';
$string['submissiondeleted'] = 'تم حذف الاستمارة المُرسلة.';

// Privacy.
$string['privacy:metadata'] = 'بلجن إدارة استمارة التوظيف بيخزّن تعريفات قالب الحقول بس ومابيخزّنش بيانات شخصية. الاستمارات المُرسلة بيخزّنها نشاط استمارة التوظيف.';
