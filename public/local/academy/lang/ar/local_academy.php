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

// Mobile API errors.
$string['err_postrequired']     = 'هذا الإجراء يتطلب POST';
$string['err_authrequired']     = 'يجب تسجيل الدخول';
$string['err_invalidtoken']     = 'انتهت الجلسة، برجاء تسجيل الدخول مرة أخرى';
$string['err_permissiondenied'] = 'غير مسموح';
$string['err_unknownfunction']  = 'دالة غير معروفة';
$string['err_internal']         = 'حدث خطأ داخلي. برجاء المحاولة لاحقًا.';
$string['err_nopermission']     = 'ليست لديك صلاحية لعرض هذا.';
$string['err_teachernotfound']  = 'المدرس غير موجود.';
$string['err_siteunavailable']  = 'الأكاديمية غير متاحة حاليًا. برجاء التواصل مع إدارة الأكاديمية.';
$string['err_invalidjson']      = 'المعامل "{$a}" يجب أن يكون JSON صحيحًا.';
$string['err_requiredfield']    = 'الحقل "{$a}" مطلوب.';
$string['err_invalidphone']     = 'يرجى إدخال رقم هاتف صحيح (مثال: 01012345678).';
$string['err_invalidlanguage']  = 'لغة غير معروفة.';
$string['err_invalidoption']    = 'اختيار غير صحيح في "{$a}".';
$string['err_divisionmismatch'] = 'هذه الشعبة لا تتبع النظام الدراسي المختار.';
$string['err_invalidnationalid'] = 'الرقم القومي 14 رقم.';
$string['err_coursenotfound']   = 'الكورس غير موجود.';
$string['err_notenrolled']      = 'أنت غير مشترك في هذا الكورس.';
$string['err_lessonlocked']     = 'أكمل الدروس السابقة أولًا.';
$string['err_lessonforsale']    = 'يجب شراء هذا الدرس أولًا.';
$string['err_coursenotfree']    = 'هذا الكورس ليس مجانيًا.';
$string['err_enrolfailed']      = 'تعذّر تسجيلك في هذا الكورس.';
$string['err_invalidemail']     = 'يرجى إدخال بريد إلكتروني صحيح.';
$string['err_toomanyrequests']  = 'طلبات كثيرة. برجاء الانتظار بضع دقائق ثم المحاولة.';
$string['err_otpexpired']       = 'انتهت صلاحية الكود. اطلب كودًا جديدًا.';
$string['err_otplocked']        = 'محاولات خاطئة كثيرة. اطلب كودًا جديدًا.';
$string['err_otpinvalid']       = 'الكود غير صحيح.';
$string['err_resetexpired']     = 'انتهت جلسة استعادة كلمة المرور. ابدأ من جديد.';
$string['err_weakpassword']     = 'كلمة المرور الجديدة لا تستوفي الشروط.';
$string['err_wrongpassword']    = 'كلمة المرور الحالية غير صحيحة.';
$string['err_authnochange']     = 'لا يمكن تغيير كلمة مرور هذا الحساب هنا (يسجل الدخول بجوجل).';

// تسجيل الطالب (register.php + register_student في التطبيق).
$string['reg_firstname'] = 'الاسم الأول';
$string['reg_secondname'] = 'الاسم الثاني';
$string['reg_thirdname'] = 'الاسم الثالث';
$string['reg_lastname'] = 'الاسم الأخير';
$string['reg_phone'] = 'رقم الهاتف';
$string['reg_grade'] = 'الصف الدراسي';
$string['reg_national'] = 'رقم الطالب القومي';
$string['reg_fatherphone'] = 'رقم هاتف الأب';
$string['reg_motherphone'] = 'رقم هاتف الأم';
$string['reg_school'] = 'اسم المدرسة';
$string['reg_guardianjob'] = 'مهنة ولي الأمر';
$string['reg_studysystem'] = 'النظام الدراسي';
$string['reg_governorate'] = 'المحافظة';
$string['reg_division'] = 'الشعبة الدراسية';
$string['reg_religion'] = 'مادة التربية الدينية';
$string['reg_gender'] = 'النوع';
$string['reg_email'] = 'البريد الإلكتروني';
$string['reg_password'] = 'كلمة السر';
$string['reg_required'] = 'هذا الحقل مطلوب';
$string['reg_invalidchoice'] = 'اختر من القائمة.';
$string['reg_emailtaken'] = 'يوجد حساب بهذا البريد الإلكتروني بالفعل. سجّل الدخول.';
$string['reg_mustagree'] = 'لازم توافق على الشروط والأحكام';
$string['reg_success'] = 'تم إنشاء حسابك بنجاح. أهلًا بيك!';
$string['reg_failed'] = 'برجاء تصحيح الحقول المحددة.';
$string['err_registrationdisabled'] = 'التسجيل مغلق في هذه الأكاديمية.';
$string['err_invalidregistration'] = 'برجاء تصحيح الحقول المحددة.';
$string['err_toomanyregistrations'] = 'تم إنشاء حسابات كثيرة من نفس الشبكة. حاول مرة أخرى بعد ساعة.';
$string['reg_passwordmismatch'] = 'كلمتا السر غير متطابقتين';
