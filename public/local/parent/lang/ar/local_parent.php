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
 * Arabic strings for local_parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'حسابات أولياء الأمور';

// Capability.
$string['parent:view'] = 'الاطّلاع على تقدّم ابنه المرتبط';

// The parent role.
$string['parentrole'] = 'ولي أمر';
$string['parentroledesc'] = 'ولي أمر يقدر يتابع درجات ابنه ونشاط اختباراته وأحداثه. بيتسند تلقائيًا في سياق كل ابن لما الحسابين يترابطوا بالرقم.';

// Settings.
$string['settingsheading'] = 'حسابات أولياء الأمور';
$string['defaultcountrycode'] = 'كود الدولة الافتراضي';
$string['defaultcountrycode_desc'] = 'أرقام بتتحط قبل الرقم المحلي اللي مفيهوش كود دولة، عشان 010… و +2010… يتطابقوا. مصر = 20.';
$string['parentphonefield'] = 'حقل رقم ولي الأمر';
$string['parentphonefield_desc'] = 'الاسم المختصر لحقل البروفايل المخصص للطالب اللي بيحمل رقم ولي الأمر (بيتملى وقت تسجيل الطالب).';

// Student sign-up field.
$string['parentphonelabel'] = 'رقم ولي الأمر';

// Parent phone-gate page.
$string['parentsignup'] = 'إنشاء حساب ولي أمر';
$string['parentsignupintro'] = 'اكتب رقم الموبايل اللي ابنك سجّله كرقم ولي أمره. مش هنكمّل غير لو طالب سجّل الرقم ده قبل كده.';
$string['yourphone'] = 'رقم موبايلك';
$string['continuetosignup'] = 'متابعة';

// Parent dashboard.
$string['dashboard'] = 'لوحة ولي الأمر';
$string['marks'] = 'الدرجات';
$string['quizactivity'] = 'نشاط الاختبارات';
$string['col_course'] = 'الكورس';
$string['col_grade'] = 'الدرجة';
$string['col_quiz'] = 'الاختبار';
$string['col_score'] = 'النتيجة';
$string['col_taken'] = 'الوقت';
$string['col_duration'] = 'المدة';
$string['noquizzes'] = 'لسه مفيش محاولات اختبار.';
$string['nomarks'] = 'لسه مفيش درجات.';

// Linking / flow messages.
$string['err_noparentnumber'] = 'مفيش طالب سجّل الرقم ده. اطلب من ابنك يضيفه في ملفه الأول.';
$string['err_invalidphone'] = 'يرجى إدخال رقم هاتف صحيح (مثال: 01012345678). غير مسموح بالحروف أو النصوص.';
$string['parentsignupbadge'] = 'إنشاء حساب ولي أمر (مرتبط برقم: {$a})';
$string['parentsignupprompt'] = 'هل أنت ولي أمر وترغب في متابعة ابنك؟';
$string['parentsignuplink'] = 'أنشئ حساب ولي أمر من هنا';
$string['parentphonehint'] = 'اختياري: اكتب رقم ولي أمرك لكي يتمكن من متابعة درجاتك ونشاطك.';
$string['mychildren'] = 'أولادي';
$string['linkedchildren'] = 'الأبناء المرتبطون';
$string['nochildren'] = 'لسه مفيش أبناء مرتبطين بحسابك.';

// Notifications to parents.
$string['messageprovider:child_activity'] = 'إشعارات تقدم الابن (الاختبارات والدرجات والأنشطة)';

$string['notif_quiz_subject'] = 'تم تسليم اختبار: {$a->child} أنهى {$a->quiz}';
$string['notif_quiz_body'] = 'مرحباً،

أنهى ابنك/ابنتك {$a->child} الاختبار "{$a->quiz}" في كورس "{$a->course}".
الدرجة: {$a->score}

يمكنك الاطلاع على كافة التفاصيل ولوحة المتابعة من هنا:
{$a->url}';

$string['notif_grade_subject'] = 'درجة جديدة لابنك {$a->child}: {$a->item}';
$string['notif_grade_body'] = 'مرحباً،

تم رصد درجة جديدة لابنك/ابنتك {$a->child} في "{$a->item}" بكورس "{$a->course}".
الدرجة: {$a->grade}

تابع درجات ابنك ونشاطه عبر لوحة ولي الأمر:
{$a->url}';

$string['notif_course_completed_subject'] = 'مبروك! {$a->child} أتم كورس {$a->course}';
$string['notif_course_completed_body'] = 'مرحباً،

خبر رائع! أتم ابنك/ابنتك {$a->child} بنجاح كورس "{$a->course}".

يمكنك مراجعة تقرير الإنجاز عبر لوحة ولي الأمر:
{$a->url}';

$string['notif_course_enrolled_subject'] = '{$a->child} انضم إلى كورس جديد: {$a->course}';
$string['notif_course_enrolled_body'] = 'مرحباً،

انضم ابنك/ابنتك {$a->child} إلى كورس "{$a->course}".

يمكنك متابعة تقدمه أولاً بأول من لوحة ولي الأمر:
{$a->url}';

