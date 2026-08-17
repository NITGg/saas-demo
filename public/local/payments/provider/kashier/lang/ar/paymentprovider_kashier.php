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
