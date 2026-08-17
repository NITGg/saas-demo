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
 * Arabic language strings for local_googleauth.
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'مصادقة جوجل للجوال';
$string['enabled'] = 'تفعيل نقطة إصدار توكن جوجل للجوال';
$string['enabled_desc'] = 'السماح لتطبيق الجوال باستبدال توكن هوية جوجل (ID token) المُتحقَّق منه بتوكن خدمة ويب في مودل عبر ‎/local/googleauth/token.php.';
$string['clientids'] = 'معرّفات عملاء جوجل المسموح بها';
$string['clientids_desc'] = 'قائمة بمعرّفات عملاء OAuth المقبولة مفصولة بفواصل (قيمة الـ "aud" في توكن هوية جوجل). أدرج معرّف عميل الويب/الخادم الذي تطلب به التوكن، بالإضافة إلى أي معرّفات عملاء أندرويد/iOS.';
$string['allowcreate'] = 'إنشاء المستخدمين تلقائيًا';
$string['allowcreate_desc'] = 'إذا فُعِّل، يُنشَأ حساب مودل جديد عند عدم وجود مستخدم يطابق بريد جوجل المُتحقَّق منه. إذا عُطِّل، تُرفَض العناوين غير المعروفة.';
$string['newuserauth'] = 'طريقة المصادقة للمستخدمين الجدد';
$string['newuserauth_desc'] = 'إضافة المصادقة المُسنَدة للحسابات المُنشأة تلقائيًا (مثل oauth2 أو manual). يجب أن تكون طريقة مصادقة مُفعّلة.';
$string['restrictdomain'] = 'التقييد بنطاقات بريد معيّنة';
$string['restrictdomain_desc'] = 'قائمة اختيارية بنطاقات البريد المسموح بها مفصولة بفواصل (مثل nitg-eg.com). اتركها فارغة للسماح بأي بريد جوجل مُتحقَّق منه.';
$string['allowedlinkauth'] = 'طرق المصادقة القابلة للربط';
$string['allowedlinkauth_desc'] = 'قائمة بطرق المصادقة التي يجوز ربط حساب موجود بها عبر تسجيل الدخول بجوجل مفصولة بفواصل (مثل oauth2). يُرفَض تسجيل الدخول بجوجل إذا كان الحساب المطابق يستخدم طريقة مصادقة غير موجودة في هذه القائمة — وهذا يمنع الاستيلاء على حساب بكلمة مرور محلية يشترك في نفس البريد. القيمة الافتراضية oauth2.';
$string['ratelimit'] = 'حد الطلبات (طلب/دقيقة لكل IP)';
$string['ratelimit_desc'] = 'أقصى عدد من طلبات التوكن المقبولة من عنوان IP واحد في الدقيقة. الطلبات الزائدة تحصل على استجابة HTTP 429. اضبطها على 0 لتعطيل التقييد. الافتراضي 20.';
$string['privacy:metadata'] = 'لا تخزّن إضافة مصادقة جوجل للجوال بيانات شخصية بنفسها؛ فهي تتحقّق من توكنات هوية جوجل وتُصدِر توكنات خدمة ويب باستخدام آليات مودل الأساسية.';
