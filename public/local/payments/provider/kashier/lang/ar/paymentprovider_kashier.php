<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'مزود الدفع كاشير';
$string['sandbox_mode'] = 'الوضع التجريبي';
$string['sandbox_mode_desc'] = 'تفعيل الوضع التجريبي لمعاملات كاشير.';
$string['merchant_id'] = 'معرف التاجر';
$string['merchant_id_desc'] = 'معرف التاجر الخاص بك في كاشير.';
$string['api_key'] = 'مفتاح API';
$string['api_key_desc'] = 'مفتاح API للدفع من كاشير (يستخدم للمصادقة والتحقق من توقيع الويب هوك).';
$string['secret_key'] = 'المفتاح السري';
$string['secret_key_desc'] = 'المفتاح السري لكاشير (يستخدم في ترويسة التفويض).';
$string['base_url'] = 'رابط API الأساسي';
$string['base_url_desc'] = 'رابط API الأساسي لكاشير. الافتراضي: https://api.kashier.io';
$string['refund_base_url'] = 'رابط الاسترداد الأساسي';
$string['refund_base_url_desc'] = 'رابط API الاسترداد/الإلغاء لكاشير. الافتراضي: https://fep.kashier.io';
$string['allowed_methods'] = 'طرق الدفع المسموحة';
$string['allowed_methods_desc'] = 'قائمة طرق الدفع المسموحة مفصولة بفواصل (مثال: card,wallet).';
$string['enable_3ds'] = 'تفعيل التحقق الثلاثي الأبعاد';
$string['enable_3ds_desc'] = 'طلب مصادقة 3D Secure لمدفوعات البطاقات.';
$string['max_failure_attempts'] = 'الحد الأقصى لمحاولات الفشل';
$string['max_failure_attempts_desc'] = 'الحد الأقصى لعدد محاولات الدفع الفاشلة قبل انتهاء الجلسة.';

// تبديل الوضع (مباشر/تجريبي) + بيانات الاعتماد.
$string['payment_mode'] = 'وضع الدفع';
$string['payment_mode_desc'] = 'ما إذا كانت هذه الأكاديمية تستقبل مدفوعات مباشرة (حقيقية) أو تجريبية (Sandbox). تبدأ الأكاديميات الجديدة من الإعداد الافتراضي للمنصة؛ وتغيّره المنصة (NIT) والمالك من هنا.';
$string['default_payment_mode'] = 'الوضع الافتراضي للمنصة (مُدار)';
$string['default_payment_mode_desc'] = 'الوضع الافتراضي الذي تحدده المنصة. يُستخدم عندما يكون "وضع الدفع" أعلاه على الإعداد الافتراضي للمنصة.';
$string['mode_default'] = 'استخدام الإعداد الافتراضي للمنصة';
$string['mode_test'] = 'تجريبي / Sandbox';
$string['mode_live'] = 'مباشر';
$string['heading_live'] = 'بيانات الاعتماد (مباشر)';
$string['heading_test'] = 'بيانات الاعتماد (تجريبي / Sandbox)';

$string['fep_url'] = 'رابط واجهة الدفع (FEP)';
$string['fep_url_desc'] = 'مضيف عمليات الاسترداد والإلغاء والتوكنز والتحويلات (Kashier FEP). مباشر: https://fep.kashier.io — تجريبي: https://test-fep.kashier.io. يختلف عن رابط واجهة الجلسات أعلاه.';
