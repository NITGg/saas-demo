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
 * Arabic strings for local_nit_reviews.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'التقييمات';
$string['nit_reviews:rate'] = 'تقييم كورس ومدرسينه';
$string['nit_reviews:moderate'] = 'الموافقة على التقييمات ورفضها وحذفها';

// Rate page.
$string['ratecourse'] = 'قيّم هذا الكورس';
$string['rateteacher'] = 'قيّم المدرس';
$string['ratecourseandteachers'] = 'قيّم الكورس والمدرسين';
$string['yourrating'] = 'تقييمك';
$string['yourreview'] = 'تعليقك (اختياري)';
$string['submitreview'] = 'إرسال التقييم';
$string['updatereview'] = 'تحديث التقييم';
$string['reviewsaved'] = 'شكرًا — تم حفظ تقييمك.';
$string['reviewsaved_pending'] = 'شكرًا — تم حفظ تقييمك، وتعليقك هيظهر بعد موافقة الإدارة.';
$string['mustenrol'] = 'لازم تكون مشترك في الكورس علشان تقيّمه.';
$string['cannotrateteacher'] = 'تقدر تقيّم المدرس في كورس ليه أنت مشترك فيه، أو بعد حصة معاه.';
$string['commentneedsapproval'] = 'النجوم بتظهر على طول، والتعليق بيظهر بعد موافقة الإدارة.';
$string['mine_pending'] = 'تقييمك في انتظار الموافقة.';
$string['mine_approved'] = 'تقييمك منشور.';
$string['mine_rejected'] = 'تقييمك ماتوافقش عليه. تقدر تعدّله وتبعته تاني.';
$string['mine_rejected_reason'] = 'تقييمك ماتوافقش عليه: {$a}. تقدر تعدّله وتبعته تاني.';
$string['back'] = 'رجوع';
$string['type_course'] = 'الكورس';
$string['type_teacher'] = 'المدرس';
$string['privatelessons'] = 'الحصص الخاصة';

// Course reviews page (for teachers).
$string['coursereviews'] = 'التقييمات';
$string['coursereviews_desc'] = 'تقييمات وآراء الطلاب في هذا المقرر ومدرسيه.';
$string['averagerating'] = 'متوسط التقييم';
$string['totalreviews'] = 'إجمالي التقييمات';
$string['stardistribution'] = 'توزيع النجوم';
$string['allratings'] = 'جميع التقييمات';
$string['stars_count'] = '{$a} نجوم';
$string['one_star'] = 'نجمة واحدة';
$string['filterbystars'] = 'تصفية بالنجوم';
$string['filterbytarget'] = 'نوع التقييم';
$string['target_course'] = 'تقييم المقرر';
$string['target_teacher'] = 'تقييم المدرس';
$string['target_teacher_name'] = 'تقييم المدرس: {$a}';
$string['gotomoderation'] = 'مراجعة التقييمات (الموافقة والرفض)';
$string['viewpubliccourse'] = 'معاينة صفحة الكورس كطالب';
$string['teacherreviews'] = 'تقييمات المدرسين';

// Display.
$string['rating'] = 'التقييم';
$string['nreviews'] = '{$a} تقييم';
$string['ratingand'] = '{$a->rating} ({$a->count})';

// Moderation page.
$string['moderatereviews'] = 'مراجعة التقييمات';
$string['status_pending'] = 'في الانتظار';
$string['status_approved'] = 'موافق عليها';
$string['status_rejected'] = 'مرفوضة';
$string['allstatuses'] = 'الكل';
$string['reviewtype'] = 'النوع';
$string['alltypes'] = 'الكورسات والمدرسين';
$string['allcourses'] = 'كل الكورسات';
$string['searchplaceholder'] = 'اسم الطالب أو التعليق';
$string['noreviews'] = 'مفيش تقييمات هنا.';
$string['author'] = 'الطالب';
$string['reviewof'] = 'التقييم لـ';
$string['comment'] = 'التعليق';
$string['nocomment'] = 'نجوم بس';
$string['approve'] = 'موافقة';
$string['reject'] = 'رفض';
$string['confirmdelete'] = 'حذف التقييم ده نهائيًا؟';
$string['rejectreason'] = 'سبب الرفض (اختياري، بيظهر للطالب)';
$string['rejectreason_help'] = 'بيتبعت مع أي زرار رفض في الصفحة';
$string['approveselected'] = 'موافقة على المحدد';
$string['rejectselected'] = 'رفض المحدد';
$string['nothingselected'] = 'اختار تقييم واحد على الأقل.';
$string['moderation_done'] = 'تم تحديث {$a} تقييم.';
$string['moderation_failed'] = 'ماقدرناش نغيّر {$a}.';
$string['eventreviewmoderated'] = 'مراجعة تقييم';

// Notifications.
$string['messageprovider:reviewpending'] = 'تقييم في انتظار الموافقة';
$string['messageprovider:reviewmoderated'] = 'الموافقة على تقييمك أو رفضه';
$string['msg_pending_subject'] = 'تقييم جديد محتاج موافقة: {$a->target}';
$string['msg_pending_body'] = '{$a->author} قيّم {$a->target} بـ {$a->rating}/5 ومعاه تعليق. هيظهر بعد موافقتك.';
$string['msg_approved_subject'] = 'تقييمك اتنشر';
$string['msg_approved_body'] = 'تمت الموافقة على تقييمك لـ {$a->target} وبقى ظاهر للكل.';
$string['msg_rejected_subject'] = 'تقييمك ماتوافقش عليه';
$string['msg_rejected_body'] = 'تقييمك لـ {$a->target} ماتوافقش عليه. تقدر تعدّله وتبعته تاني.';
$string['msg_rejected_reason_subject'] = 'تقييمك ماتوافقش عليه';
$string['msg_rejected_reason_body'] = 'تقييمك لـ {$a->target} ماتوافقش عليه: {$a->reason}. تقدر تعدّله وتبعته تاني.';

// Privacy.
$string['privacy:metadata:local_nit_reviews'] = 'تقييمات النجوم والتعليقات اللي كتبها المستخدم عن الكورسات والمدرسين.';
$string['privacy:metadata:local_nit_reviews:courseid'] = 'الكورس اللي بيتقيّم، أو كورس المدرس اللي بيتقيّم.';
$string['privacy:metadata:local_nit_reviews:teacherid'] = 'المدرس اللي بيتقيّم، لو موجود.';
$string['privacy:metadata:local_nit_reviews:userid'] = 'المستخدم اللي كتب التقييم.';
$string['privacy:metadata:local_nit_reviews:rating'] = 'تقييم النجوم (1–5).';
$string['privacy:metadata:local_nit_reviews:review'] = 'نص التعليق.';
$string['privacy:metadata:local_nit_reviews:status'] = 'التقييم في الانتظار ولا موافق عليه ولا مرفوض.';
$string['privacy:metadata:local_nit_reviews:rejectreason'] = 'سبب الرفض.';
$string['privacy:metadata:local_nit_reviews:reviewedby'] = 'اللي وافق على التقييم أو رفضه.';
$string['privacy:metadata:local_nit_reviews:timecreated'] = 'وقت كتابة التقييم لأول مرة.';
$string['privacy:metadata:local_nit_reviews:timemodified'] = 'وقت آخر تعديل للتقييم.';

// Errors (mobile API and pages).
$string['err_coursenotfound'] = 'الكورس غير موجود.';
$string['err_cannotrate'] = 'تقدر تقيّم الكورسات اللي أنت مشترك فيها بس.';
$string['err_cannotrateteacher'] = 'تقدر تقيّم المدرس في كورس ليه أنت مشترك فيه، أو بعد حصة معاه.';
$string['err_invalidrating'] = 'اختار تقييم من 1 إلى 5 نجوم.';
$string['err_reviewtoolong'] = 'التعليق طويل جدًا (الحد الأقصى {$a} حرف).';
$string['err_reasontoolong'] = 'السبب طويل جدًا (الحد الأقصى {$a} حرف).';
$string['err_reviewnotfound'] = 'التقييم غير موجود.';
$string['err_toomanycourses'] = 'عدد الكورسات في الطلب كبير جدًا (الحد الأقصى {$a}).';
$string['err_teachernotfound'] = 'المدرس غير موجود.';
