<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Arabic strings.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'حد الأجهزة';
$string['nit_devices:manage'] = 'عرض وحذف أجهزة أي حساب';
$string['nit_devices:exempt'] = 'بدون حد للأجهزة';

$string['enabled'] = 'تحديد عدد الأجهزة لكل حساب';
$string['enabled_desc'] = 'كل حساب يُستخدم على عدد محدد من الأجهزة (المتصفحات والتطبيق مع بعض). مدير الموقع والمديرين والمدرسين بدون حد.';
$string['maxdevices'] = 'عدد الأجهزة لكل حساب';
$string['maxdevices_desc'] = 'المتصفحات والتطبيق بيتحسبوا مع بعض. الجهاز يفضل مسجّل لحد ما يتشال من صفحة "الأجهزة" (تسجيل الخروج ما بيفضّيش مكانه).';
$string['policy'] = 'لما جهاز زيادة عن الحد يسجّل دخول';
$string['policy_desc'] = 'نرفضه (والطالب يطلب من الدعم يشيل جهاز)، أو نخرّج أقدم جهاز عشان نفضّي مكان.';
$string['policyblock'] = 'نرفض الجهاز الجديد';
$string['policyreplace'] = 'نخرّج أقدم جهاز';
$string['allowlegacylogin'] = 'السماح بالنسخة القديمة من التطبيق';
$string['allowlegacylogin_desc'] = 'نسخ التطبيق اللي ما بتبعتش رقم الجهاز بتدخل من /login/token.php، وكل موبايلاتها بتتحسب جهاز واحد. شيل العلامة لما كل الطلاب يحدّثوا التطبيق: النسخة القديمة هتاخد "appupdaterequired".';

$string['devicelimit'] = 'الحساب ده مستخدم بالفعل على {$a} أجهزة، وده أقصى عدد مسموح. اطلب من الدعم يشيل جهاز مش بتستخدمه.';
$string['appupdaterequired'] = 'من فضلك حدّث التطبيق لآخر نسخة عشان تقدر تسجّل دخول.';
$string['siteunavailable'] = 'الأكاديمية مش متاحة دلوقتي.';
$string['legacyapp'] = 'تطبيق الموبايل (نسخة قديمة)';

$string['blockedtitle'] = 'عدد الأجهزة اكتمل';
$string['blockedbody'] = 'لحماية حسابك، الحساب بيتفتح على عدد محدد من الأجهزة بس. ما اتسجّلش دخولك على الجهاز ده.';
$string['backtologin'] = 'رجوع لتسجيل الدخول';

$string['devices'] = 'الأجهزة';
$string['mydevices'] = 'أجهزتي';
$string['userdevices'] = 'أجهزة {$a}';
$string['device'] = 'الجهاز';
$string['kind'] = 'النوع';
$string['kind_web'] = 'متصفح';
$string['kind_mobile'] = 'تطبيق الموبايل';
$string['firstseen'] = 'أول استخدام';
$string['lastseen'] = 'آخر استخدام';
$string['ip'] = 'عنوان IP';
$string['nodevices'] = 'مفيش أجهزة لسه.';
$string['remove'] = 'حذف';
$string['removeconfirm'] = 'حذف الجهاز ده؟ هيتعمله تسجيل خروج فورًا ومكانه هيفضى.';
$string['removed'] = 'الجهاز اتحذف واتعمله تسجيل خروج.';
$string['resetall'] = 'حذف كل الأجهزة';
$string['resetconfirm'] = 'حذف كل أجهزة الحساب ده؟ كلهم هيتعملهم تسجيل خروج فورًا.';
$string['resetdone'] = 'اتحذف {$a} جهاز.';
$string['searchuser'] = 'دوّر على حساب';
$string['searchplaceholder'] = 'الاسم أو الإيميل أو اسم المستخدم أو الموبايل';
$string['nousersfound'] = 'مفيش حساب بالبيانات دي.';
$string['limitinfo'] = 'لحد {$a->max} أجهزة (المتصفحات والتطبيق مع بعض). لو جه جهاز زيادة: {$a->policy}.';
$string['limitoff'] = 'حد الأجهزة مقفول (إدارة الموقع ← الإضافات ← الإضافات المحلية ← حد الأجهزة).';
$string['exemptinfo'] = 'الحساب ده بدون حد للأجهزة (مدير أو مدرس).';

$string['videocheck'] = 'فحص حماية الفيديوهات';
$string['videocheck_desc'] = 'دروس VdoCipher بس هي المحمية بـ DRM (Widevine / FairPlay) وعليها العلامة المائية باسم الطالب. دروس Vimeo أسهل في التحميل وتسجيل الشاشة. الكورسات المدفوعة دي لسه فيها دروس Vimeo، انقلها لـ VdoCipher.';
$string['videocheck_none'] = 'كل الكورسات المدفوعة بتستخدم فيديوهات محمية (VdoCipher) بس.';
$string['videocheck_found'] = '{$a} كورس مدفوع لسه فيه دروس Vimeo.';
$string['videocheck_course'] = 'الكورس';
$string['videocheck_vimeo'] = 'دروس Vimeo';
$string['videocheck_vdocipher'] = 'دروس VdoCipher';

$string['privacy:metadata:table'] = 'الأجهزة (المتصفحات والتطبيق) اللي كل حساب دخل منها، عشان حد الأجهزة.';
$string['privacy:metadata:userid'] = 'الحساب.';
$string['privacy:metadata:kind'] = 'متصفح أو تطبيق موبايل.';
$string['privacy:metadata:name'] = 'المتصفح والنظام، أو موديل الموبايل اللي التطبيق بيبعته.';
$string['privacy:metadata:platform'] = 'نظام التشغيل.';
$string['privacy:metadata:ip'] = 'عنوان IP لآخر استخدام.';
$string['privacy:metadata:timecreated'] = 'أول مرة الجهاز اتستخدم.';
$string['privacy:metadata:lastseen'] = 'آخر مرة الجهاز اتستخدم.';
