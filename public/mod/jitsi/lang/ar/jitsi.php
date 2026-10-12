<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename']       = 'جلسة Jitsi';
$string['modulenameplural'] = 'جلسات Jitsi';
$string['pluginname']       = 'جلسة Jitsi';
$string['pluginadministration'] = 'إدارة جلسة Jitsi';
$string['jitsi:addinstance'] = 'إضافة جلسة Jitsi جديدة';
$string['jitsi:view']       = 'عرض جلسة Jitsi';
$string['jitsi:moderate']   = 'إدارة جلسة Jitsi';
$string['sessionname']      = 'اسم الجلسة';
$string['nonewmodules']     = 'لا توجد جلسات Jitsi';
$string['privacy:metadata'] = 'لا يخزّن هذا المكوّن أي بيانات شخصية.';

// View page strings.
$string['notallowed']      = 'أنت غير مسجّل في هذه الجلسة.';
$string['sessionopening']  = 'ستُفتح الجلسة خلال {$a} دقيقة.';
$string['waitingforteacher'] = 'في انتظار انضمام المعلم لبدء الجلسة. سيتم إدخالك تلقائياً عند انضمامه.';
$string['teacheraway'] = 'المعلم ليس في الحصة الآن. ستراه هنا عند عودته.';
$string['sessionended']    = 'انتهت هذه الجلسة.';
$string['roomunavailable'] = 'هذه الغرفة غير متاحة لك.';
$string['youarehost']      = 'أنت المضيف';
$string['featurelocked']   = 'الجلسات المباشرة (Jitsi) متاحة في الباقة الاحترافية. يُرجى ترقية الأكاديمية لاستخدام هذا النشاط.';
$string['recording']       = 'تسجيل';
$string['recordings']      = 'تسجيلات الجلسة';
$string['norecordings']    = 'لا توجد تسجيلات بعد. عند قيام المضيف بتسجيل الجلسة ستظهر هنا بعد المعالجة.';
$string['recordingprocessing'] = 'جارٍ المعالجة…';
$string['tab_video']       = 'الجلسة المرئية';
$string['recordings']      = 'تسجيلات الجلسة';
$string['norecordings']    = 'لا تتوفر تسجيلات بعد.';
$string['recording']       = 'تسجيل';
$string['recprocessing']   = 'جارٍ معالجة التسجيل، يُرجى التحقق لاحقاً.';
$string['recnotavailable'] = 'التسجيل غير متاح في الوقت الحالي.';
$string['recstorage']      = 'موقع التخزين';

// Security options.
$string['security_header']       = 'خيارات الأمان';
$string['roompassword']          = 'كلمة مرور الغرفة';
$string['roompassword_help']     = 'إذا تم تعيينها، يجب على المشاركين إدخال هذه الكلمة للانضمام. اتركها فارغة لعدم استخدام كلمة مرور.';
$string['lobby_enabled']         = 'ينتظر الطالب موافقة المعلم على الدخول';
$string['lobby_enabled_help']    = 'عند التفعيل، لا يستطيع الطالب فتح الغرفة إلا بوجود أحد معلمي المقرر (أو المدير) فيها، ثم ينتظر في غرفة الانتظار داخل الاجتماع حتى يسمح له المعلم بالدخول من داخل الاجتماع. يدخل المعلمون والمديرون مباشرة. يطبَّق على غرف المقررات (المجدولة وغير المجدولة)، لا على الحصص الخاصة.';

// موعد الجلسة (إعدادات النشاط).
$string['schedule_header']       = 'موعد الجلسة';
$string['sessionstart']          = 'تبدأ في';
$string['sessionstart_help']     = 'فعّله لجدولة الجلسة. يصل تذكير قبلها لكل طلاب المقرر (ومن يسجّل لاحقًا أيضًا)، وتُفتح الغرفة لهم قبل البداية بـ 30 دقيقة، وتظهر الجلسة في لوحة المتابعة المباشرة وفي تقرير الحصص. يبقى النشاط ظاهرًا في صفحة المقرر طوال الوقت، فيمكن مشاهدة التسجيل بعد الجلسة. اتركه غير مفعّل لغرفة مفتوحة دائمًا.';
$string['sessionstart_past']     = 'هذا الموعد انتهى بالفعل. راجع اليوم والساعة.';
$string['sessionend']            = 'تنتهي في';
$string['sessionend_help']       = 'بعد هذا الوقت لا يدخل أحد جديد، إلا إذا كان المعلم ما زال داخل الجلسة: تبقى مفتوحة ويُحتسب الحضور حتى يخرج المعلم أو يُنهي الجلسة للجميع، ثم تُغلق خلال دقائق.';
$string['sessionend_beforestart'] = 'وقت الانتهاء لازم يكون بعد وقت البداية.';
$string['sessionend_toolong']    = 'الجلسة لا تزيد عن 24 ساعة.';
$string['sessiontime']           = 'موعد الجلسة';
$string['sessiontime_text']      = '{$a->start} – {$a->end}';

// بعد الخروج من الاجتماع (view.php).
$string['ended_byteacher'] = 'أنهيت الجلسة للجميع.';
$string['ended_byhost']    = 'أنهى المعلم الجلسة.';
$string['ended_left']      = 'خرجت من الجلسة.';
$string['ended_ending']    = 'جارٍ إنهاء الجلسة…';
$string['ended_failed']    = 'تعذّر تسجيل انتهاء الجلسة. حاول مرة أخرى من الغرفة.';
$string['ended_recnote']   = 'إذا سُجّلت الجلسة، يظهر التسجيل في "تسجيلات الجلسة" بعد انتهائها ببضع دقائق.';
$string['ended_recfailed'] = 'تعذّر تحميل التسجيلات الآن.';

// سجل حضور الغرفة.
$string['attendancelog'] = 'سجل الحضور';
$string['task_closestalepresence'] = 'إغلاق حضور من غادر الغرفة دون إشعار';
$string['log_day'] = 'اليوم';
$string['log_download'] = 'تنزيل CSV';
$string['log_empty'] = 'لم يدخل أحد هذه الغرفة بعد.';
$string['log_people'] = 'الأشخاص';
$string['log_students'] = 'الطلاب';
$string['log_hosts'] = 'المعلمون والمديرون';
$string['log_visits'] = 'مرات الدخول';
$string['log_role'] = 'الدور';
$string['log_host'] = 'معلم / مدير';
$string['log_student'] = 'طالب';
$string['log_joined'] = 'وقت الدخول';
$string['log_left'] = 'خرج';
$string['log_leftat'] = 'وقت الخروج';
$string['log_minutes'] = 'الدقائق';
$string['log_inside'] = 'في الحصة';
$string['log_stillin'] = 'ما زال بالداخل';
$string['log_summary'] = 'أول دخول {$a->first} · دخل {$a->visits} مرة · {$a->minutes} دقيقة';

// خطوة ما قبل دخول الغرفة (view.php بدون join=1).
$string['joinsession'] = 'انضم إلى الجلسة';
$string['prejoin_title'] = 'غرفة الجلسة جاهزة';
$string['prejoin_text'] = 'لن تدخل الغرفة ولن تُستخدم الكاميرا أو الميكروفون إلا بعد الضغط على "انضم إلى الجلسة".';
$string['prejoin_autorecord'] = 'يبدأ تسجيل الجلسة تلقائياً بعد دخولك.';
$string['waitingadmit'] = 'طلبت الدخول. في انتظار أن يسمح لك المعلم بالدخول…';
// تاب الجلسة المرئية والطالب في انتظار المعلم (view.php).
$string['waiting_title'] = 'لم يبدأ المعلم الجلسة بعد.';
$string['waiting_next_join'] = 'ستدخل تلقائياً عند انضمامه.';
$string['waiting_next_button'] = 'سيظهر لك هنا زر "انضم إلى الجلسة" عند انضمامه.';
$string['waiting_next_admit'] = 'بعدها ينتظر طلبك حتى يسمح لك المعلم بالدخول.';
$string['waiting_title_join'] = 'المعلم ليس في الحصة الآن.';

// أزرار التحكم الخاصة بالغرفة (templates/dock.mustache).
$string['dock_blur'] = 'تمويه الخلفية';
$string['dock_camera'] = 'الكاميرا';
$string['dock_chat'] = 'المحادثة';
$string['dock_device_camera'] = 'الكاميرا';
$string['dock_device_mic'] = 'الميكروفون';
$string['dock_device_speaker'] = 'السماعة';
$string['dock_endall'] = 'إنهاء الحصة للجميع';
$string['dock_fullscreen'] = 'ملء الشاشة';
$string['dock_hand'] = 'رفع اليد';
$string['dock_leave'] = 'مغادرة';
$string['dock_live'] = 'مباشر';
$string['dock_mic'] = 'الميكروفون';
$string['dock_muteall'] = 'كتم الجميع';
$string['dock_noise'] = 'إزالة الضوضاء';
$string['dock_people'] = 'المشاركون';
$string['dock_rec'] = 'جارٍ التسجيل';
$string['dock_record'] = 'تسجيل';
$string['dock_screen'] = 'مشاركة الشاشة';
$string['dock_settings'] = 'الإعدادات';
$string['dock_tile'] = 'عرض الكل';
$string['dock_whiteboard'] = 'السبورة';
