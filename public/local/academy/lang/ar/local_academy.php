<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. See <http://www.gnu.org/licenses/>.

/**
 * Arabic strings for local_academy (only those that differ from English).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['academic_page'] = 'الأنظمة الدراسية والشعب';
$string['academic_structure'] = 'الأنظمة الدراسية والشعب';
$string['academic_structure_desc'] = 'اختيارات قائمتي "النظام الدراسي" و"الشعبة" — في صفحة إعدادات الكورس (مجموعة "custom fields") وفي ملف الطالب وصفحة التسجيل. لكل نظام دراسي شعبه الخاصة: لما يتختار النظام، قائمة الشعبة بتعرض شعبه هو بس. الحفظ بيحدّث كل القوائم، والكورس اللي اختياره اتشال من القائمة بيفقد الاختيار ده.';
$string['academic_system'] = 'النظام الدراسي';
$string['academic_divisions'] = 'شعبه (شعبة في كل سطر)';
$string['academic_divisions_hint'] = 'شعبة في كل سطر';
$string['academic_add'] = 'إضافة نظام دراسي';
$string['academic_remove'] = 'حذف';
$string['academic_nosystems'] = 'أضف نظامًا دراسيًا واحدًا على الأقل.';
$string['academic_nodivisions'] = 'النظام الدراسي "{$a}" لازم يكون له شعبة واحدة على الأقل.';
$string['academic_years_categories'] = '<strong>الصفوف الدراسية</strong> هي تصنيفات الكورسات (كل المستويات) — تضيفها أو تعدّل اسمها أو ترتيبها من <a href="{$a}">إدارة الكورسات والتصنيفات</a>. صف الكورس هو التصنيف اللي هو فيه، وحقل "الصف الدراسي" للطالب بيعرض التصنيفات تلقائيًا.';

// صفحة المدرس (local/academy/teacher.php).
$string['teacherpage_title'] = 'صفحة المدرس : {$a}';
$string['teacherpage_notteacher'] = 'صفحة المدرس دي مش متاحة.';
$string['teacherpage_years'] = 'الصفوف';
$string['teacherpage_courses'] = 'الكورسات';
$string['teacherpage_coursecount'] = 'عدد الكورسات';
$string['teacherpage_yearcount'] = 'عدد الصفوف';
$string['teacherpage_studentcount'] = 'عدد الطلاب';
$string['teacherpage_about'] = 'عن المدرس';
$string['teacherpage_heading1'] = 'كورسات';
$string['teacherpage_heading2'] = 'المدرس';
$string['teacherpage_all'] = 'الكل';
$string['teacherpage_enter'] = 'الدخول للكورس';
$string['teacherpage_subscribe'] = 'الإشتراك في الكورس !';
$string['teacherpage_free'] = 'اشترك ببلاش';
$string['teacherpage_more'] = '... عرض باقي التفاصيل';
$string['teacherpage_less'] = 'إخفاء التفاصيل';
$string['teacherpage_created'] = 'تاريخ الإنشاء';
$string['teacherpage_modified'] = 'آخر تحديث';
$string['teacherpage_empty'] = 'لا توجد كورسات لهذا الصف حاليًا.';
