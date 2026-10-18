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
 * Arabic strings for local_nit_tutors.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'المدرسون (NIT Tutors)';
$string['admincategory'] = 'طلبات انضمام المدرسين';
$string['task_refreshbadges'] = 'تحديث شارات Super Tutor';
$string['nit_tutors:review'] = 'مراجعة طلبات انضمام المدرسين';
$string['nit_tutors:manage'] = 'إدارة حقول طلب الانضمام';
$string['messageprovider:applicationupdate'] = 'تحديثات طلب انضمامي كمدرس';
$string['messageprovider:applicationsubmitted'] = 'طلبات انضمام جديدة للمراجعة';

// Statuses.
$string['status_draft'] = 'لم يُرسل بعد';
$string['status_pending'] = 'قيد المراجعة';
$string['status_changes'] = 'مطلوب تعديلات';
$string['status_approved'] = 'معتمد';
$string['status_rejected'] = 'مرفوض';
$string['status_suspended'] = 'موقوف';
$string['statushelp_draft'] = 'أكمل ملفك كمدرس ثم أرسله للمراجعة. سيظهر ملفك للطلاب بعد اعتماده من الأكاديمية.';
$string['statushelp_pending'] = 'طلبك عند الأكاديمية الآن، وسنراسلك فور مراجعته.';
$string['statushelp_changes'] = 'طلبت الأكاديمية بعض التعديلات. عدّل ملفك ثم أرسله مرة أخرى.';
$string['statushelp_approved'] = 'أنت مدرس معتمد: يمكن للطلاب العثور عليك وحجز حصص معك. أي فيديو تعريفي جديد يظهر بعد اعتماده.';
$string['statushelp_rejected'] = 'لم يُقبل طلبك. يمكنك تعديله والتقديم مرة أخرى.';
$string['statushelp_suspended'] = 'ملفك كمدرس موقوف: لا يظهر للطلاب ولا يمكن حجزك. الحصص المحجوزة بالفعل لا تتأثر.';
$string['videostatus_pending'] = 'قيد المراجعة';
$string['videostatus_approved'] = 'معتمد';
$string['videostatus_rejected'] = 'غير معتمد';
$string['reviewnote'] = 'رسالة المراجِع';
$string['reviewnote_help'] = 'السبب أو المطلوب تعديله. إلزامية عند الرفض أو طلب التعديل أو الإيقاف أو رفض الفيديو، ويراها المدرس.';
$string['stillmissing'] = 'ما زال ناقصًا';
$string['viewpublicpage'] = 'عرض الصفحة العامة';
$string['teacherprofile'] = 'ملف المدرس';
$string['saved'] = 'تم الحفظ.';
$string['submitted'] = 'تم إرسال طلبك للمراجعة.';
$string['done'] = 'تم.';

// What blocks submitting.
$string['missing_bio'] = 'نبذة عنك';
$string['missing_subjects'] = 'مادة واحدة على الأقل';
$string['missing_video'] = 'فيديو تعريفي';
$string['missing_qualifications'] = 'مؤهل واحد على الأقل (المطلوب: {$a})';

// Application form.
$string['form_about'] = 'عنك';
$string['form_headline'] = 'العنوان';
$string['form_bio'] = 'نبذة عنك';
$string['form_subjects'] = 'المواد';
$string['form_video'] = 'الفيديو التعريفي';
$string['form_video_help'] = 'فيديو قصير (من دقيقة إلى 3 دقائق) تعرّف فيه بنفسك وبطريقتك في التدريس. ارفع ملفًا أو الصق رابط YouTube أو Vimeo. يظهر للطلاب بعد اعتماده من الأكاديمية.';
$string['form_videotype'] = 'الفيديو';
$string['form_videotype_upload'] = 'رفع ملف فيديو';
$string['form_videotype_link'] = 'رابط YouTube أو Vimeo';
$string['form_videofile'] = 'ملف الفيديو';
$string['form_videourl'] = 'رابط الفيديو';
$string['form_quals'] = 'المؤهلات';
$string['form_quals_help'] = 'الشهادات والمؤهلات الدراسية. أرفق صورة الشهادة: الملف تراه الأكاديمية فقط، ويرى الطلاب اسم المؤهل وعلامة «موثّق» بعد أن تتحقق منه الأكاديمية.';
$string['form_qualtitle'] = 'المؤهل';
$string['form_qualinstitution'] = 'الجامعة / الجهة';
$string['form_qualyear'] = 'السنة';
$string['form_qualfile'] = 'الشهادة (PDF أو صورة)';
$string['form_qualdelete'] = 'حذف هذا المؤهل';
$string['form_qualadd'] = 'إضافة مؤهل آخر';
$string['form_more'] = 'معلومات إضافية';
$string['form_save'] = 'حفظ';
$string['form_submit'] = 'حفظ وإرسال للمراجعة';
$string['qual_verified'] = 'موثّق';

// Join as a teacher.

// Errors.
$string['err_noteditable'] = 'طلبك قيد المراجعة ولا يمكن تعديله الآن.';
$string['err_noreapply'] = 'لا تقبل هذه الأكاديمية طلبًا جديدًا بعد الرفض.';
$string['err_incomplete'] = 'أكمل طلبك أولًا: {$a}';
$string['err_notereq'] = 'اكتب رسالة للمدرس أولًا.';
$string['err_badvideourl'] = 'الصق رابط فيديو من YouTube أو Vimeo.';
$string['err_qualtitle'] = 'اكتب اسم المؤهل.';
$string['err_badanswer'] = 'إجابة «{$a}» غير صحيحة.';
$string['err_shortname'] = 'اكتب اسمًا مختصرًا للحقل (حروف إنجليزية وأرقام و - و _).';
$string['err_shortnametaken'] = 'هذا الاسم المختصر مستخدم لحقل آخر.';
$string['err_badtype'] = 'نوع حقل غير معروف.';
$string['err_fieldname'] = 'اكتب اسم الحقل.';
$string['err_nooptions'] = 'القائمة المنسدلة تحتاج خيارات، كل خيار في سطر.';
$string['err_phone'] = 'أدخل رقم هاتف صحيحًا (أرقام فقط، من 7 إلى 15 رقمًا).';
$string['err_agree'] = 'يجب الموافقة على الشروط والأحكام.';
$string['err_signup'] = 'راجع البيانات: {$a}';

// Admin: list and review.
$string['searchapplicants'] = 'الاسم أو البريد';
$string['noapplications'] = 'لا توجد طلبات هنا.';
$string['col_status'] = 'الحالة';
$string['col_submitted'] = 'تاريخ الإرسال';
$string['col_reviewed'] = 'تاريخ المراجعة';
$string['col_video'] = 'الفيديو';
$string['col_quals'] = 'المؤهلات الموثّقة';
$string['review'] = 'مراجعة';
$string['userprofile'] = 'حساب المستخدم';
$string['decision'] = 'القرار';
$string['act_approve'] = 'اعتماد';
$string['act_reinstate'] = 'إعادة التفعيل';
$string['act_reject'] = 'رفض';
$string['act_suspend'] = 'إيقاف';
$string['novideo'] = 'لا يوجد فيديو تعريفي.';
$string['noquals'] = 'لا توجد مؤهلات.';
$string['price'] = 'السعر';
$string['priceflex'] = '{$a} فلكس';

// Admin: fields.
$string['fields_intro'] = 'أسئلة يجيب عنها المدرس في طلب الانضمام، بجانب الأجزاء الثابتة (النبذة، والمواد، والفيديو التعريفي، والمؤهلات). يمكن إظهار الحقل في صفحة المدرس العامة، ويمكن جعل القائمة المنسدلة فلترًا في بحث المدرسين (مثل «اللغات التي يتحدثها» أو «الدولة»).';
$string['addfield'] = 'إضافة حقل';
$string['editfield'] = 'تعديل الحقل';
$string['nofields'] = 'لا توجد حقول بعد.';
$string['confirmdeletefield'] = 'حذف الحقل «{$a}» وإجابات كل المدرسين عليه؟';
$string['field_name'] = 'اسم الحقل';
$string['field_name_help'] = 'يظهر للمدرس. استخدم {mlang ar}…{mlang}{mlang en}…{mlang} للغتين.';
$string['field_shortname'] = 'الاسم المختصر';
$string['field_type'] = 'النوع';
$string['field_options'] = 'الخيارات (كل خيار في سطر)';
$string['field_description'] = 'نص المساعدة';
$string['field_required'] = 'إلزامي';
$string['field_public'] = 'يظهر في صفحة المدرس';
$string['field_public_help'] = 'يرى الطلاب هذه الإجابة في صفحة المدرس العامة.';
$string['field_filter'] = 'فلتر في بحث المدرسين';
$string['field_filter_help'] = 'يستطيع الطلاب فلترة بحث المدرسين بهذه القائمة.';
$string['field_active'] = 'مفعّل';
$string['type_text'] = 'نص قصير';
$string['type_textarea'] = 'نص طويل';
$string['type_select'] = 'قائمة منسدلة';
$string['type_number'] = 'رقم';
$string['type_url'] = 'رابط';
$string['type_date'] = 'تاريخ';
$string['type_checkbox'] = 'نعم / لا';
$string['type_file'] = 'ملف';

// Settings.
$string['set_requireapproval'] = 'الاعتماد مطلوب';
$string['set_requireapproval_desc'] = 'يظهر في بحث المدرسين ويمكن حجزه فقط المدرس المعتمد طلبه. عند الإيقاف: يظهر كل مدرس يقبل الحجز.';
$string['set_requirevideo'] = 'الفيديو التعريفي إلزامي';
$string['set_minqualifications'] = 'عدد المؤهلات المطلوبة';
$string['set_videomaxmb'] = 'أقصى حجم لرفع الفيديو (ميجابايت)';
$string['set_videomaxmb_desc'] = 'ويحدّه أيضًا حد الرفع في الخادم.';
$string['set_allowreapply'] = 'السماح بالتقديم مرة أخرى بعد الرفض';

// Messages.
$string['msg_submitted_subject'] = 'طلب انضمام مدرس جديد: {$a}';
$string['msg_submitted_body'] = 'أرسل {$a->name} طلب انضمام كمدرس. راجعه من هنا: {$a->url}';
$string['msg_approved_subject'] = 'أصبحت مدرسًا معتمدًا في الأكاديمية';
$string['msg_approved_body'] = 'مرحبًا {$a->name}، تم اعتماد طلب انضمامك كمدرس. يستطيع الطلاب الآن العثور عليك وحجز حصص معك. حدّد مواعيدك الأسبوعية وسعرك من ملفك: {$a->url}';
$string['msg_rejected_subject'] = 'بخصوص طلب انضمامك كمدرس';
$string['msg_rejected_body'] = 'مرحبًا {$a->name}، لم يُقبل طلب انضمامك كمدرس. السبب: {$a->note}. {$a->url}';
$string['msg_changes_subject'] = 'مطلوب تعديلات على طلب انضمامك';
$string['msg_changes_body'] = 'مرحبًا {$a->name}، من فضلك عدّل طلب انضمامك: {$a->note}. ثم أرسله مرة أخرى: {$a->url}';
$string['msg_suspended_subject'] = 'تم إيقاف ملفك كمدرس';
$string['msg_suspended_body'] = 'مرحبًا {$a->name}، تم إيقاف ملفك كمدرس: {$a->note}. لن يظهر ملفك للطلاب ولن يمكن حجزك. {$a->url}';
$string['msg_videook_subject'] = 'تم اعتماد الفيديو التعريفي';
$string['msg_videook_body'] = 'يرى الطلاب الآن الفيديو التعريفي الجديد. {$a->url}';
$string['msg_videono_subject'] = 'لم يُعتمد الفيديو التعريفي';
$string['msg_videono_body'] = 'السبب: {$a->note}. ارفع فيديو آخر: {$a->url}';

// Teacher search.
$string['findteacher'] = 'ابحث عن مدرس';
$string['nteachers'] = '{$a} مدرس';
$string['searchin'] = 'البحث في';
$string['tab_courses'] = 'الدورات';
$string['tab_teachers'] = 'المدرسين';
$string['viewallteachers'] = 'كل المدرسين المطابقين';
$string['searchplaceholder'] = 'اسم المدرس أو المادة…';
$string['filters'] = 'الفلاتر';
$string['any'] = 'الكل';
$string['pricerange'] = 'سعر الحصة (فلكس)';
$string['min'] = 'من';
$string['max'] = 'إلى';
$string['availableday'] = 'متاح يوم';
$string['availabletime'] = 'وقت اليوم';
$string['time_morning'] = 'صباحًا (6:00–12:00)';
$string['time_afternoon'] = 'ظهرًا (12:00–17:00)';
$string['time_evening'] = 'مساءً (17:00–24:00)';
$string['rating'] = 'التقييم';
$string['ratingup'] = '{$a} ★ فأكثر';
$string['sortby'] = 'الترتيب';
$string['sort_recommended'] = 'موصى به';
$string['sort_rating'] = 'الأعلى تقييمًا';
$string['sort_price_asc'] = 'السعر: من الأقل';
$string['sort_price_desc'] = 'السعر: من الأعلى';
$string['sort_lessons'] = 'الأكثر حصصًا';
$string['applyfilters'] = 'عرض المدرسين';
$string['clearfilters'] = 'مسح الفلاتر';
$string['hasvideo'] = 'لديه فيديو تعريفي';
$string['newteacher'] = 'مدرس جديد';
$string['nlessons'] = '{$a} حصة';
$string['perlesson'] = 'للحصة';
$string['booklesson'] = 'احجز حصة';
$string['viewprofile'] = 'عرض الملف';
$string['noteachers'] = 'لا يوجد مدرس يطابق هذه الفلاتر.';
$string['areyouteacher'] = 'هل أنت مدرس؟';
$string['jointeacher'] = 'قدّم طلب انضمام';
$string['availability'] = 'المواعيد الأسبوعية';
$string['timesin'] = 'المواعيد بتوقيت {$a}.';

// Privacy.
$string['privacy:metadata:tutor'] = 'طلب انضمام المدرس والفيديو التعريفي والشارة.';
$string['privacy:metadata:tutor:userid'] = 'المدرس.';
$string['privacy:metadata:tutor:status'] = 'حالة الطلب.';
$string['privacy:metadata:tutor:reviewnote'] = 'رسالة المراجِع للمدرس.';
$string['privacy:metadata:tutor:videourl'] = 'رابط الفيديو التعريفي.';
$string['privacy:metadata:qual'] = 'مؤهلات المدرس.';
$string['privacy:metadata:qual:userid'] = 'المدرس.';
$string['privacy:metadata:qual:title'] = 'المؤهل.';
$string['privacy:metadata:qual:institution'] = 'الجهة.';
$string['privacy:metadata:qual:year'] = 'السنة.';
$string['privacy:metadata:data'] = 'إجابات المدرس على حقول الطلب.';
$string['privacy:metadata:data:userid'] = 'المدرس.';
$string['privacy:metadata:data:value'] = 'الإجابة.';
$string['privacy:metadata:files'] = 'الفيديو التعريفي والشهادات والملفات المرفقة.';

// Admin page tabs (Local plugins → Teacher applications).
$string['tab_applications'] = 'الطلبات';
$string['tab_fields'] = 'حقول الطلب';
$string['tab_badges'] = 'الشارات';
$string['tab_settings'] = 'الإعدادات';
$string['opencertificate'] = 'فتح الشهادة';

// Application fields tab: the fixed parts.
$string['builtinfields'] = 'حقول موجودة في كل طلب';
$string['extrafields'] = 'حقولك الإضافية';
$string['builtin_video'] = 'ملف فيديو أو رابط YouTube / Vimeo';
$string['builtin_quals'] = 'مؤهلات مع صورة الشهادة';
$string['builtin_afterreview'] = 'بعد اعتماده من الأكاديمية';
$string['builtin_atleast'] = '{$a} على الأقل';

// Badges.
$string['badge'] = 'الشارة';
$string['badges_intro'] = 'تظهر الشارات في صفحة المدرس وفي بحث المدرسين، ويستطيع الطلاب الفلترة بها. الشارة إما تُمنح تلقائيًا للمدرس الذي يحقق قواعدها (تُراجع يوميًا وتُسحب عندما لا تعود متحققة)، أو يدويًا: افتح «المدرسون» بجانب الشارة واختر لكل مدرس: حسب القواعد، أو دائمًا، أو أبدًا.';
$string['addbadge'] = 'إضافة شارة';
$string['editbadge'] = 'تعديل الشارة';
$string['nobadges'] = 'لا توجد شارات بعد.';
$string['confirmdeletebadge'] = 'حذف الشارة «{$a}»؟ كل مدرس يحملها سيفقدها.';
$string['badge_name'] = 'الاسم';
$string['badge_name_help'] = 'ما يراه الطلاب، مثل «مدرس متميز». استخدم {mlang ar}…{mlang}{mlang en}…{mlang} للغتين.';
$string['badge_description'] = 'معناها';
$string['badge_color'] = 'اللون';
$string['badge_method'] = 'طريقة المنح';
$string['badgemethod_auto'] = 'تلقائيًا حسب القواعد';
$string['badgemethod_manual'] = 'يدويًا من المراجِع';
$string['badgemethod_manual_desc'] = 'يمنحها المراجِع من صفحة مراجعة المدرس.';
$string['badge_rules'] = 'القواعد';
$string['badge_rules_help'] = 'يحصل المدرس على الشارة عندما تتحقق كل قاعدة تحددها. اترك القاعدة 0 (ونسبة الغياب 100) حتى لا تُفحص.';
$string['badge_minrating'] = 'أقل متوسط تقييم (من 0 إلى 5)';
$string['badge_minreviews'] = 'أقل عدد تقييمات';
$string['badge_minlessons'] = 'أقل عدد حصص مكتملة';
$string['badge_maxabsence'] = 'أقصى نسبة غياب للمدرس (%)';
$string['badge_holders'] = 'عدد من يحملها';
$string['badge_wears'] = 'يحملها';
$string['badgemode_auto'] = 'حسب قواعدها';
$string['badgemode_auto_manual'] = 'غير ممنوحة';
$string['badgemode_on'] = 'امنحها دائمًا';
$string['badgemode_off'] = 'لا تمنحها أبدًا';
$string['rule_rating'] = 'تقييم {$a} فأكثر';
$string['rule_reviews'] = '{$a} تقييمات على الأقل';
$string['rule_lessons'] = '{$a} حصة مكتملة على الأقل';
$string['rule_absence'] = 'غياب {$a}% أو أقل';
$string['rule_none'] = 'بدون قواعد: كل مدرس معتمد';
$string['color_warning'] = 'ذهبي (Warning)';
$string['color_primary'] = 'اللون الأساسي (Primary)';
$string['color_success'] = 'أخضر (Success)';
$string['color_info'] = 'أزرق (Info)';
$string['color_error'] = 'أحمر (Error)';
$string['color_secondary'] = 'رمادي (Secondary)';
$string['err_badgename'] = 'اكتب اسم الشارة.';
$string['err_rating'] = 'أدخل تقييمًا من 0 إلى 5.';
$string['err_percent'] = 'أدخل نسبة من 0 إلى 100.';
$string['err_negative'] = 'أدخل 0 أو أكثر.';
$string['privacy:metadata:badgeuser'] = 'الشارات التي يحملها المدرس، واختيار المراجِع لكل شارة.';
$string['privacy:metadata:badgeuser:userid'] = 'المدرس.';
$string['privacy:metadata:badgeuser:mode'] = 'اختيار المراجِع (حسب القواعد، دائمًا، أبدًا).';
$string['privacy:metadata:badgeuser:awarded'] = 'هل يحمل المدرس الشارة.';

// Teacher sign-up steps and the profile page (teacher_input).
$string['signup_sent'] = 'تم إنشاء حسابك وإرسال طلب انضمامك كمدرس للمراجعة.';
$string['signup_laststep'] = 'الإرسال للمراجعة';
$string['signup_review_note'] = 'عند الإرسال تراجع الأكاديمية ملفك، وتصلك رسالة بالقرار. بعد الاعتماد يستطيع الطلاب العثور عليك وحجز حصص معك.';
$string['signup_filesagain'] = 'من فضلك اختر ملف الفيديو وملفات الشهادات مرة أخرى (أُعيد تحميل الصفحة).';
$string['signup_agree'] = 'أوافق على';
$string['signup_terms'] = 'الشروط والأحكام';
$string['signup_send'] = 'إنشاء حسابي وإرسال الطلب للمراجعة';
$string['form_headline_ph'] = 'سطر واحد تحت اسمك، مثل: «مدرب IELTS — خبرة 8 سنوات»';
$string['form_bio_ph'] = 'عرّف الطلاب بنفسك: خبرتك وطريقتك في التدريس.';
$string['form_qualtitle_ph'] = 'مثل: ليسانس آداب لغة إنجليزية';
$string['currentfile'] = 'الملف الحالي: {$a}';
$string['filelimit'] = 'حتى {$a}';
$string['err_form'] = 'من فضلك صحّح الأجزاء المحددة.';
$string['err_toolong'] = '{$a} حرفًا على الأكثر.';
$string['err_choosevideo'] = 'اختر ملف فيديو لرفعه، أو الصق رابطًا.';
$string['err_qualyear'] = 'أدخل سنة بين 1950 والسنة القادمة.';
$string['err_filetype'] = 'نوع الملف غير مقبول. المقبول: {$a}';
$string['err_filesize'] = 'الملف أكبر من المسموح (الحد {$a}).';
$string['savedforreview'] = 'تم الحفظ. الفيديو أو المؤهلات الجديدة تظهر بعد مراجعة الأكاديمية لها.';
$string['nocertificate'] = 'لم تُرفق شهادة.';
$string['isnew'] = 'جديد — للمراجعة';

// Review page.
$string['tab_updates'] = 'تعديلات جديدة';
$string['act_approveupdates'] = 'اعتماد التعديلات';
$string['act_rejectupdates'] = 'رفض التعديلات';
$string['decision_application'] = 'اعتمد المدرس، أو ارفض الطلب مع كتابة السبب الذي سيقرؤه المدرس (يستطيع التعديل والتقديم مرة أخرى).';
$string['decision_updates'] = 'هذا المدرس معتمد وغيّر الفيديو التعريفي أو المؤهلات. «اعتماد» يُظهرها للطلاب، و«رفض» (مع السبب) لا يُظهرها، ويبقى المدرس معتمدًا.';
$string['decision_approved'] = 'هذا المدرس معتمد. لإيقافه (لا يظهر في البحث ولا يمكن حجزه) اكتب السبب واضغط «إيقاف».';
$string['decision_suspended'] = 'هذا المدرس موقوف. أعد اعتماده ليظهر مرة أخرى.';
$string['msg_updates_subject'] = 'تعديلات على ملف مدرس للمراجعة: {$a}';
$string['msg_updates_body'] = 'غيّر {$a->name} الفيديو التعريفي أو المؤهلات. راجعها من هنا: {$a->url}';
$string['msg_updatesrejected_subject'] = 'لم تُعتمد تعديلات ملفك';
$string['msg_updatesrejected_body'] = 'مرحبًا {$a->name}، لم تُعتمد التعديلات على ملفك كمدرس: {$a->note}. {$a->url}';

// Badges tab: the teachers of a badge.
$string['badge_manage'] = 'المدرسون';
$string['badgeteachers_help'] = 'لكل مدرس معتمد اختر كيف يحصل على هذه الشارة: حسب قواعدها، أو دائمًا، أو أبدًا. وبهذه الطريقة تُمنح الشارة اليدوية.';
$string['noapprovedteachers'] = 'لا يوجد مدرسون معتمدون بعد.';

// Teacher sign-up: the checks of each step in the browser (signup_steps).
$string['js_bio'] = 'اكتب نبذة قصيرة عنك.';
$string['js_subjects'] = 'اختر مادة واحدة على الأقل.';
$string['js_filetype'] = 'نوع الملف غير مقبول. المقبول: %s';
$string['js_filesize'] = 'الملف أكبر من الحجم المسموح.';
$string['js_stepof'] = 'الخطوة %s من %n';
