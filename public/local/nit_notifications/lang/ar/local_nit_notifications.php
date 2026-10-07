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
 * Arabic strings for local_nit_notifications.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'الإشعارات';
$string['nit_notifications:send'] = 'إرسال الإشعارات ومراجعة سجلها';
$string['messageprovider:announcement'] = 'إشعارات من إدارة المنصة';
$string['messageprovider:announcement_email'] = 'إشعارات إدارة المنصة بالإيميل (لما الإدارة تبعتها بالإيميل)';
$string['notifications'] = 'الإشعارات';
$string['sendnotification'] = 'إرسال إشعار';
$string['notificationlog'] = 'سجل الإشعارات';
$string['task_deliver'] = 'إرسال إشعار لمجموعة كبيرة';
$string['eventnotificationsent'] = 'إرسال إشعار';

// Compose.
$string['audience'] = 'إرسال إلى';
$string['audience_course_students'] = 'طلاب كورس';
$string['audience_course_teachers'] = 'مدرسين كورس';
$string['audience_all_students'] = 'كل الطلاب';
$string['audience_all_teachers'] = 'كل المدرسين';
$string['audience_managers'] = 'المديرين';
$string['audience_admins'] = 'مسئولي النظام';
$string['audience_users'] = 'مستخدمين محددين (تلقائي)';
$string['allmycourses'] = 'كل الكورسات اللي بديرها';
$string['allcourses'] = 'كل الكورسات';
$string['type'] = 'النوع';
$string['type_general'] = 'عام';
$string['type_courses'] = 'كورسات';
$string['type_subscriptions'] = 'اشتراكات';
$string['type_offers'] = 'عروض وكوبونات';
$string['title'] = 'العنوان';
$string['body'] = 'النص';
$string['link'] = 'رابط (اختياري)';
$string['link_help'] = 'صفحة بتفتح لما المستخدم يدوس على الإشعار، زي صفحة كورس أو عرض.';
$string['alsoemail'] = 'ابعته كمان على الإيميل';
$string['alsoemail_help'] = 'الإيميل بيروح بس للمستخدمين اللي مفعّلين "إشعارات إدارة المنصة بالإيميل" في تفضيلات الإشعارات بتاعتهم. الباقيين بيوصلهم الإشعار في الجرس والتطبيق بس.';
$string['next'] = 'التالي';
$string['back'] = 'رجوع';
$string['confirmcount'] = 'الإشعار ده هيتبعت لـ {$a} مستخدم.';
$string['bigsendnote'] = 'المجموعات الكبيرة بتتبعت على دفعات في الخلفية خلال الدقايق الجاية.';
$string['sendnow'] = 'ابعت دلوقتي لـ {$a} مستخدم';
$string['sentdone'] = 'تم إرسال الإشعار: وصل لـ {$a->sent}، وفشل {$a->failed}.';
$string['sentqueued'] = 'تم حفظ الإشعار، وجاري إرساله لـ {$a} مستخدم في الخلفية.';
$string['scopednote'] = 'تقدر تبعت لطلاب ومدرسين الكورسات اللي بتديرها.';
$string['langnote'] = 'اكتب باقي اللغات لو تقدر: المستخدم اللي لغته فاضية هيوصله النص بلغة الموقع الأساسية.';

// Log.
$string['sender'] = 'المرسل';
$string['system'] = 'النظام';
$string['source'] = 'المصدر';
$string['allsources'] = 'يدوي وتلقائي';
$string['source_manual'] = 'يدوي';
$string['source_auto'] = 'تلقائي';
$string['alltypes'] = 'كل الأنواع';
$string['searchplaceholder'] = 'العنوان أو النص';
$string['nonotifications'] = 'مفيش إشعارات لسه.';
$string['norecipients'] = 'مفيش مستلمين هنا.';
$string['recipients'] = 'المستلمين';
$string['state_queued'] = 'في الانتظار';
$string['state_sent'] = 'وصل';
$string['state_failed'] = 'فشل';
$string['state_read'] = 'اتقرا';
$string['state_unread'] = 'ماتقراش';
$string['timesent'] = 'وقت الإرسال';
$string['timeread'] = 'وقت القراءة';
$string['emailcopy'] = 'نسخة الإيميل';
$string['email_sent'] = 'الإيميل اتبعت';
$string['email_failed'] = 'الإيميل فشل';
$string['email_bymoodle'] = 'اتبعت إيميل (من تفضيلات المستخدم)';
$string['email_off'] = 'الإيميل مقفول (من تفضيلاته)';
$string['status_draft'] = 'مسودة';
$string['status_queued'] = 'في الانتظار';
$string['status_sending'] = 'جاري الإرسال';
$string['status_done'] = 'اتبعت';

// Errors (pages and mobile API).
$string['err_audience'] = 'مش مسموحلك تبعت للمجموعة دي.';
$string['err_course'] = 'تقدر تبعت للكورسات اللي بتديرها بس.';
$string['err_choosecourse'] = 'اختار كورس.';
$string['err_type'] = 'اختار نوع الإشعار.';
$string['err_required'] = 'مطلوب.';
$string['err_titletoolong'] = 'العنوان طويل جدًا (الحد الأقصى {$a} حرف).';
$string['err_bodytoolong'] = 'النص طويل جدًا (الحد الأقصى {$a} حرف).';
$string['err_url'] = 'اكتب رابط كامل بيبدأ بـ https://';
$string['err_norecipients'] = 'مفيش حد في المجموعة دي، فماتبعتش حاجة.';
$string['err_notificationnotfound'] = 'الإشعار غير موجود.';

// Privacy.
$string['privacy:metadata:local_nit_notif'] = 'الإشعارات اللي المستخدم بعتها لمجموعة.';
$string['privacy:metadata:local_nit_notif:senderid'] = 'مين بعت الإشعار.';
$string['privacy:metadata:local_nit_notif:title'] = 'عنوان الإشعار.';
$string['privacy:metadata:local_nit_notif:body'] = 'نص الإشعار.';
$string['privacy:metadata:local_nit_notif:timecreated'] = 'وقت الإرسال.';
$string['privacy:metadata:local_nit_notif_rcpt'] = 'مين استلم كل إشعار.';
$string['privacy:metadata:local_nit_notif_rcpt:userid'] = 'المستلم.';
$string['privacy:metadata:local_nit_notif_rcpt:status'] = 'الإشعار وصل ولا لأ.';
$string['privacy:metadata:local_nit_notif_rcpt:timesent'] = 'وقت الوصول.';
$string['privacy:metadata:core_message'] = 'الإشعارات نفسها بتتبعت كإشعارات Moodle.';

// Automatic notifications.
$string['messageprovider:subscription'] = 'اشتراكاتك ومشترياتك (تفعيل، تجديد، إلغاء)';
$string['messageprovider:newquiz'] = 'اختبار جديد في كورساتك';
$string['messageprovider:sessionreminder'] = 'تذكير قبل الحصص المباشرة والحصص الخاصة';
$string['task_sessionreminders'] = 'إرسال تذكيرات الحصص المباشرة والخاصة';
$string['autosettings'] = 'الإشعارات التلقائية';
$string['autosettings_desc'] = 'إشعارات المنصة بتبعتها لوحدها. بتتكتب بكل اللغات المتسطّبة (كل مستخدم بيوصله بلغته)، وبتظهر في سجل الإشعارات على إنها "تلقائي"، وكل مستخدم يقدر يقفل أي نوع منها من تفضيلات الإشعارات بتاعته.';
$string['auto_subscription'] = 'الاشتراكات والمشتريات';
$string['auto_subscription_desc'] = 'يبلّغ الطالب لما اشتراكه يتفعّل أو يتجدد أو يتلغي، ولما شراء كورس يتلغي أو فلوسه ترجع. (التذكير قبل انتهاء الاشتراك إعداداته في إعدادات الاشتراكات.)';
$string['auto_newquiz'] = 'اختبار جديد';
$string['auto_newquiz_desc'] = 'يبلّغ طلاب الكورس لما اختبار يبقى ظاهر فيه (مرة واحدة لكل اختبار).';
$string['auto_sessionreminder'] = 'تذكير الحصص';
$string['auto_sessionreminder_desc'] = 'يفكّر الطلاب والمدرس قبل ما الحصة المباشرة أو الحصة الخاصة تبدأ.';
$string['session_reminder_minutes'] = 'مواعيد التذكير (دقايق قبل الحصة)';
$string['session_reminder_minutes_desc'] = 'مفصولة بفاصلة، مثلًا 60,10 = قبلها بساعة وقبلها بعشر دقايق. الحصة اللي بتبدأ قبل ميعاد منهم بياخد تذكير واحد بس.';
$string['source_subscription_started'] = 'تفعيل اشتراك';
$string['source_subscription_renewed'] = 'تجديد اشتراك';
$string['source_subscription_cancelled'] = 'إلغاء اشتراك';
$string['source_course_cancelled'] = 'إلغاء شراء كورس';
$string['source_course_refunded'] = 'استرداد شراء كورس';
$string['source_subscription_expiry'] = 'قرب انتهاء الاشتراك';
$string['source_flex_expiry'] = 'قرب انتهاء باقة الحصص';
$string['source_newquiz'] = 'اختبار جديد';
$string['source_session_reminder'] = 'تذكير حصة مباشرة';
$string['source_lesson_reminder'] = 'تذكير حصة خاصة';
$string['auto_substarted_title'] = 'اشتراكك "{$a->plan}" اتفعّل';
$string['auto_substarted_body'] = 'أهلًا بيك! اشتراكك "{$a->plan}" شغال لحد {$a->expires}، وكورساته مفتوحة ليك.';
$string['auto_subrenewed_title'] = 'اشتراكك "{$a->plan}" اتجدد';
$string['auto_subrenewed_body'] = 'اشتراكك "{$a->plan}" اتجدد وشغال لحد {$a->expires}.';
$string['auto_subcancelled_title'] = 'اشتراكك "{$a->plan}" اتلغى';
$string['auto_subcancelled_body'] = 'إدارة المنصة لغت اشتراكك "{$a->plan}"، فكورساته مبقتش مفتوحة ليك. كلّمنا لو عندك أي سؤال.';
$string['auto_coursecancelled_title'] = 'شراء كورس "{$a->course}" اتلغى';
$string['auto_coursecancelled_body'] = 'إدارة المنصة لغت شراءك لكورس "{$a->course}"، فالكورس مبقاش مفتوح ليك.';
$string['auto_courserefunded_title'] = 'فلوس كورس "{$a->course}" اترجعت';
$string['auto_courserefunded_body'] = 'تم استرداد قيمة شراءك لكورس "{$a->course}"، فالكورس مبقاش مفتوح ليك.';
$string['auto_newquiz_title'] = 'اختبار جديد: {$a->quiz}';
$string['auto_newquiz_body'] = 'اتضاف اختبار جديد "{$a->quiz}" في كورس "{$a->course}".';
$string['auto_session_title'] = 'تذكير: "{$a->title}" هتبدأ بعد {$a->minutes} دقيقة';
$string['auto_session_body'] = 'الحصة المباشرة "{$a->title}" في كورس "{$a->course}" هتبدأ بعد {$a->minutes} دقيقة.';
$string['auto_lesson_title'] = 'تذكير: حصتك الخاصة هتبدأ بعد {$a->minutes} دقيقة';
$string['auto_lesson_body'] = 'حصة {$a->subject} مع {$a->with} هتبدأ بعد {$a->minutes} دقيقة.';
$string['offer_notify_title'] = 'عرض جديد: {$a->name}';
$string['offer_notify_body'] = '{$a->name}: خصم {$a->discount} على {$a->items}. {$a->dates}';
$string['coupon_notify_title'] = 'كوبون خصم: {$a->code}';
$string['coupon_notify_body'] = 'استخدم الكوبون {$a->code} وخد خصم {$a->discount} على {$a->items}. {$a->dates}';
$string['notify_until'] = 'لحد {$a}.';
$string['notify_allitems'] = 'كل الكورسات';
