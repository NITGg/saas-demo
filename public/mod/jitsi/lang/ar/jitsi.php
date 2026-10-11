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
$string['tab_whiteboard']  = 'السبورة التفاعلية';
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
$string['lobby_enabled_help']    = 'عند التفعيل، لا يستطيع الطالب فتح الغرفة إلا بوجود أحد معلمي المقرر (أو المدير) فيها، ثم ينتظر في غرفة الانتظار داخل الاجتماع حتى يسمح له المعلم بالدخول من داخل الاجتماع. يدخل المعلمون والمديرون مباشرة. يطبَّق على غرف المقررات، لا على الحصص المباشرة المجدولة.';

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
