<?php
defined('MOODLE_INTERNAL') || die();

// Arabic strings for the renewal banner (the rest of local_license falls back to
// English). Shown to Arabic-locale academies in the final week before expiry.
$string['expiry_banner_soon']    = 'ينتهي اشتراك أكاديميتك خلال {$a->days} يوم (بتاريخ {$a->date}). جدّد لتجنّب أي انقطاع.';
$string['expiry_banner_expired'] = 'انتهى اشتراك أكاديميتك. جدّد الآن قبل إيقافها — بياناتك محفوظة.';
$string['expiry_renew']          = 'جدّد الآن';
$string['expiry_banner_autorenew'] = 'تتجدّد أكاديميتك تلقائياً بتاريخ {$a->date} — لا حاجة لأي إجراء.';

// My plan & subscription page.
$string['myplan_nav']       = 'خطتي';
$string['myplan_heading']   = 'خطتي والاشتراك';
$string['pkg_heading']      = 'خطتك';
$string['pkg_storage']      = 'المساحة';
$string['pkg_gb']           = '{$a} جيجابايت';
$string['pkg_unlimited']    = 'غير محدود';
$string['sub_heading']      = 'الاشتراك';
$string['sub_subscribed']   = 'تاريخ الاشتراك';
$string['sub_expires']      = 'تاريخ الانتهاء';
$string['sub_daysleft']     = 'الأيام المتبقية';
$string['sub_status']       = 'الحالة';
$string['sub_autorenew']    = 'التجديد التلقائي';
$string['sub_status_active']    = 'نشط';
$string['sub_status_expired']   = 'منتهٍ';
$string['sub_status_suspended'] = 'موقوف';
$string['sub_unknown']      = 'غير معروف';
$string['billing_heading']  = 'الفوترة والترقية';
$string['billing_note']     = 'السعر والفواتير والترقية والتجديد تتم إدارتها من حساب NIT الخاص بك.';
$string['billing_manage']   = 'إدارة الاشتراك · ترقية · تجديد';
$string['yes']              = 'نعم';
$string['no']               = 'لا';
$string['videolimitreached'] = 'تم الوصول للحد الأقصى للفيديوهات — استهلكت هذه الأكاديمية كل الفيديوهات في باقتها. احذف فيديو أو رقِّ باقتك لإضافة المزيد.';

// Strings that were missing in Arabic (English pages showed through).
$string['pluginname'] = 'ترخيص الأكاديمية';
$string['enabled'] = 'تطبيق حدود الترخيص';
$string['enabled_desc'] = 'المفتاح الرئيسي. عند إيقافه لا يتم تقييد أي شيء (الأكاديمية تعمل بكل المزايا) — فعّله لتطبيق الباقة المحددة بالأسفل. مُوقف افتراضياً حتى لا يغيّر تثبيت الإضافة أي شيء قبل تفعيلك له.';
$string['tier'] = 'الباقة';
$string['tier_desc'] = 'الباقة التي تعمل عليها هذه الأكاديمية. لكل باقة حدودها الخاصة للكورسات والأنشطة ومجموعة مزاياها.';
$string['expirydate'] = 'تاريخ الانتهاء';
$string['expirydate_desc'] = 'موعد قفل الأكاديمية (بالصيغة YYYY-MM-DD). اتركه فارغاً لعدم الانتهاء. الفترة التجريبية عادةً أسبوعان، والباقات المدفوعة سنة.';
$string['gracedays'] = 'فترة السماح (بالأيام)';
$string['gracedays_desc'] = 'أيام إضافية بعد تاريخ الانتهاء قبل أن تُقفل الأكاديمية فعلياً. 0 = القفل في تاريخ الانتهاء.';
$string['statuspage'] = 'ترخيص الأكاديمية — الحالة';
$string['status_heading'] = 'حالة الترخيص';
$string['status_enforced'] = 'التطبيق';
$string['status_on'] = 'مفعّل';
$string['status_off'] = 'مُوقف (لا توجد حدود مطبقة)';
$string['status_tier'] = 'الباقة';
$string['status_expiry'] = 'ينتهي في';
$string['status_daysleft'] = 'الأيام المتبقية';
$string['status_never'] = 'أبداً';
$string['status_features'] = 'المزايا المتاحة';
$string['status_videosrc'] = 'مصدر الفيديو';
$string['status_none'] = 'لا يوجد';
$string['usage_heading'] = 'الاستخدام مقابل الحدود';
$string['usage_item'] = 'العنصر';
$string['usage_used'] = 'المستخدم / المسموح';
$string['usage_courses'] = 'الكورسات';
$string['usage_teachers'] = 'المدرسين';
$string['usage_quiz'] = 'الاختبارات';
$string['usage_video'] = 'الفيديوهات';
$string['usage_pdf'] = 'الملفات / PDF';
$string['limit_course'] = 'باقة {$a} وصلت للحد الأقصى من الكورسات. قم بالترقية لإضافة كورسات أكثر.';
$string['limit_activity'] = 'باقة {$a->tier} وصلت للحد الأقصى لهذا النوع من الأنشطة في الكورس ({$a->type}). قم بالترقية لإضافة المزيد.';
$string['feature_locked'] = 'نشاط {$a->type} غير متاح في باقة {$a->tier}. قم بالترقية لاستخدامه.';
$string['video_source_locked'] = 'مصدر الفيديو في باقة {$a->tier} ({$a->source}) لا يسمح بنشاط {$a->type}. قم بالترقية للحصول على فيديو محمي (DRM).';
$string['limit_teacher'] = 'باقة {$a->tier} وصلت للحد الأقصى من المدرسين ({$a->max}). قم بالترقية لإضافة مدرسين أكثر.';
$string['expired_title'] = 'انتهت صلاحية الأكاديمية';
$string['expired_heading'] = 'انتهت صلاحية هذه الأكاديمية';
$string['expired_body'] = 'انتهت فترة {$a}. قم بالترقية لإعادة تفعيل أكاديميتك والاحتفاظ بمحتواك.';
$string['expired_contact'] = 'تواصل مع NIT للترقية أو التجديد.';
$string['suspended_title'] = 'الأكاديمية موقوفة';
$string['suspended_heading'] = 'هذه الأكاديمية موقوفة';
$string['suspended_body'] = 'تم إيقاف الوصول لهذه الأكاديمية مؤقتاً. محتواك في أمان — تواصل معنا لاستعادته.';
$string['contact_btn'] = 'تواصل معنا';
$string['backtosite'] = 'الرجوع للموقع';
