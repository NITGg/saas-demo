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

$string['pluginname'] = 'لوحة تحكم ولي الأمر';
$string['cachedef_failures'] = 'محاولات الدخول الخاطئة على لوحة ولي الأمر';

// Settings.
$string['settingsheading'] = 'لوحة تحكم ولي الأمر';
$string['defaultcountrycode'] = 'كود الدولة الافتراضي';
$string['defaultcountrycode_desc'] = 'أرقام بتتحط قبل الرقم المحلي اللي مفيهوش كود دولة، عشان 010… و +2010… يتطابقوا. مصر = 20.';
$string['parentphonefield'] = 'حقل رقم ولي الأمر';
$string['parentphonefield_desc'] = 'الاسم المختصر لحقل البروفايل اللي فيه رقم ولي أمر الطالب. لوحة ولي الأمر بتقارن رقم ولي الأمر بالحقل ده وبحقلي رقم الأب ورقم الأم.';

// Student sign-up field.
$string['parentphonelabel'] = 'رقم ولي الأمر';

// Dashboard — hero and form.
$string['dashboard'] = 'لوحة تحكم ولي الأمر';
$string['title_a'] = 'لوحة تحكم';
$string['title_b'] = 'ولي الأمر';
$string['intro'] = 'دلوقتي تقدر تتابع نجلك أسبوعياً بكل سهولة لكل نشاطاته التعليمية من حيث مشاهداته ودرجاته، عشان تكون مطمئن على مستقبله وتبقى جزء من رحلته التعليمية، وتساعدنا في تحقيق هدفه للوصول لأفضل النتائج.';
$string['childphone'] = 'رقم هاتف نجلك';
$string['yourphone'] = 'رقم هاتفك';
$string['start'] = 'ابدأ المتابعة';
$string['err_invalidphone'] = 'يرجى إدخال رقم هاتف صحيح (مثال: 01012345678). غير مسموح بالحروف أو النصوص.';
$string['err_nomatch'] = 'البيانات غير صحيحة. اتأكد من رقم نجلك ومن رقمك المسجل عنده كرقم ولي الأمر.';
$string['err_toomany'] = 'محاولات كثيرة غير صحيحة. حاول مرة أخرى بعد ربع ساعة.';

// Dashboard — results.
$string['courselist'] = 'كورسات نجلك';
$string['togglelist'] = 'تصغير / تكبير قائمة الكورسات';
$string['choosecourse'] = 'أختر الكورس أولا لإظهار كل ما يخص نجلك من إحصائيات ونتائج';
$string['nocourses'] = 'لا يوجد كورسات مشترك بها نجلك حاليا لعرض البيانات';
$string['stats_a'] = 'تفاصيل احصائيات';
$string['stats_b'] = 'نجلك !';
$string['showcontent'] = 'عرض المحتوى';
$string['hidecontent'] = 'إخفاء المحتوى';
$string['nocontent'] = 'لم يتم نزول المحتوى في هذا الكورس حتى الآن';
$string['emptyweek'] = 'لم يتم رفع محتوى';
$string['startedon'] = 'بدأ في :';
$string['col_videos'] = 'الفيديوهات';
$string['col_homework'] = 'الواجبات';
$string['col_exams'] = 'الامتحانات';
$string['studentlevel'] = 'مستوى الطالب';

// Items, named by their order in the lecture.
$string['label_videos'] = 'الفيديو {$a}';
$string['label_homework'] = 'الواجب {$a}';
$string['label_exams'] = 'الامتحان {$a}';
$string['ord1'] = 'الأول';
$string['ord2'] = 'الثاني';
$string['ord3'] = 'الثالث';
$string['ord4'] = 'الرابع';
$string['ord5'] = 'الخامس';
$string['ord6'] = 'السادس';
$string['ord7'] = 'السابع';
$string['ord8'] = 'الثامن';
$string['ord9'] = 'التاسع';
$string['ord10'] = 'العاشر';
$string['done_videos'] = 'تم مشاهدة';
$string['done_videos_after'] = 'من الفيديو';
$string['done_homework'] = 'نتيجة الواجب';
$string['done_exams'] = 'نتيجة الاختبار';
$string['pending_videos'] = 'جاري المشاهدة';
$string['pending_homework'] = 'تم التسليم، في انتظار التصحيح';
$string['pending_exams'] = 'تم الحل، في انتظار النتيجة';
$string['absent_videos'] = 'لم يتم مشاهدة الفيديو';
$string['absent_homework'] = 'لم يتم حضور الواجب';
$string['absent_exams'] = 'لم يتم حضور الاختبار';
$string['none_videos'] = 'لا يوجد فيديوهات حتى الآن';
$string['none_homework'] = 'لا يوجد واجبات حتى الآن';
$string['none_exams'] = 'لا يوجد اختبارات حتى الآن';

// Privacy.
$string['privacy:metadata'] = 'إضافة لوحة ولي الأمر لا تخزن أي بيانات شخصية؛ هي بتعرض بيانات الطالب الموجودة بالفعل لما رقمه ورقم ولي أمره يتطابقوا.';
