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
 * Arabic strings for mod_jobform.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'استمارة التوظيف';
$string['modulename'] = 'استمارة التوظيف';
$string['modulenameplural'] = 'استمارات التوظيف';
$string['modulename_help'] = 'نشاط "استمارة التوظيف" بيخلّي الطالب يملا استمارة ويبعتها (مثلاً استمارة تقديم على وظيفة) بعد ما يخلّص الكورس. المعلّم أو الأدمن بيحدّد الحقول، وممكن يخلّي الاستمارة متاحة بس بعد الحصول على شهادة، وبيراجع الاستمارات المُرسلة.';
$string['pluginadministration'] = 'إدارة استمارة التوظيف';

// Capabilities.
$string['jobform:addinstance'] = 'إضافة نشاط استمارة توظيف جديد';
$string['jobform:view'] = 'عرض نشاط استمارة التوظيف';
$string['jobform:submit'] = 'ملء وإرسال استمارة التوظيف';
$string['jobform:managefields'] = 'إدارة حقول نشاط استمارة التوظيف';
$string['jobform:viewsubmissions'] = 'عرض استمارات التوظيف المُرسلة';

// Settings form.
$string['jobformname'] = 'اسم النشاط';
$string['availability'] = 'الإتاحة';
$string['nocertificate'] = 'بدون شهادة (متاح دايمًا)';
$string['linkedcertificate'] = 'الشهادة المرتبطة';
$string['linkedcertificate_help'] = 'اختار نشاط شهادة من الكورس ده. الطالب مش هيقدر يملا الاستمارة إلا بعد ما ياخد الشهادة دي — يعني بعد ما يخلّص الكورس. اختار "بدون شهادة" عشان الاستمارة تبقى متاحة دايمًا.';
$string['allowresubmit'] = 'السماح للطالب بالتعديل وإعادة الإرسال';
$string['allowresubmit_help'] = 'لو مفعّل، الطالب يقدر يعدّل ويبعت الاستمارة تاني بعد إرسالها. لو مقفول، الاستمارة بتتقفل بمجرد إرسالها.';

// View.
$string['generalsection'] = 'عام';
$string['activityfieldsintro'] = 'دي الحقول اللي الطلبة هيملوها في النشاط ده. تعديلها هنا بيأثر على النشاط ده بس.';
$string['confirmusedefaultfields'] = 'ده هيمسح كل حقول ومجموعات النشاط ده الحالية ويستبدلها بالقالب الافتراضي. أي إجابات اتجمّعت للحقول الحالية هتتمسح. تكمّل؟';
$string['defaultfieldsapplied'] = 'تم تطبيق الحقول الافتراضية على النشاط ده.';
$string['certificaterequired'] = 'الاستمارة دي بتتاح بعد ما تحصل على شهادة الكورس. من فضلك خلّص الكورس الأول.';
$string['alreadysubmitted'] = 'انت بعتت الاستمارة دي بالفعل. تحت اللي انت أرسلته.';
$string['noformfields'] = 'الاستمارة دي لسه مفيهاش حقول. من فضلك ارجعلها بعدين.';
$string['nothingtodisplay'] = 'مفيش حاجة نعرضها.';

// Student form buttons and messages.
$string['sendform'] = 'إرسال';
$string['savedraft'] = 'حفظ كمسودة';
$string['formsent'] = 'تم إرسال استمارتك.';
$string['draftsaved'] = 'تم حفظ المسودة.';

// Validation.
$string['errornotnumber'] = 'من فضلك اكتب رقم.';
$string['errornotemail'] = 'من فضلك اكتب بريد إلكتروني صحيح.';
$string['errornoturl'] = 'من فضلك اكتب رابط صحيح يبدأ بـ http:// أو https://.';
$string['errornotphone'] = 'من فضلك اكتب رقم هاتف صحيح (من 7 لـ 15 رقم، ويجوز يبدأ بـ +).';
$string['errorinvalidcmid'] = 'مفيش نشاط "استمارة توظيف" لرقم الـ course module ده ({$a}). ابعت الـ cmid بتاع نشاط الاستمارة نفسه — تقدر تجيب القيمة الصح من mod_jobform_get_jobforms_by_courses.';

// Date field help.
$string['dateoptional'] = 'تاريخ اختياري';
$string['dateoptional_help'] = 'التاريخ ده اختياري. علّم على مربّع "تمكين" عشان تدخل تاريخ، أو سيبه من غير تعليم عشان تتخطى الحقل ده.';

// Privacy.
$string['privacy:metadata:jobform_submission'] = 'الاستمارات اللي الطالب بيبعتها لنشاط استمارة التوظيف.';
$string['privacy:metadata:jobform_submission:jobformid'] = 'نشاط استمارة التوظيف اللي الاستمارة بتخصّه.';
$string['privacy:metadata:jobform_submission:userid'] = 'الطالب اللي بعت الاستمارة.';
$string['privacy:metadata:jobform_submission:status'] = 'هل الاستمارة مسودة ولا اتبعتت.';
$string['privacy:metadata:jobform_submission:timemodified'] = 'آخر تعديل على الاستمارة.';
$string['privacy:metadata:jobform_submission_data'] = 'الإجابات جوه الاستمارة.';
$string['privacy:metadata:jobform_submission_data:value'] = 'القيمة اللي الطالب كتبها في الحقل.';
