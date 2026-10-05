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
 * Arabic strings for local_nit_mlang.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'الحقول متعددة اللغات';
$string['privacy:metadata'] = 'لا يخزّن مكوّن الحقول متعددة اللغات أي بيانات شخصية.';

$string['nit_mlang:edit'] = 'تعبئة الحقول القابلة للترجمة لكل لغة على حدة';

$string['status'] = 'الحالة';
$string['statuslangs'] = 'ستظهر الحقول القابلة للترجمة بحقل إدخال لكل حزمة لغة مثبّتة: {$a}. ثبّت أو احذف حزمة لغة لتغيير هذه القائمة.';
$string['statusnofilter'] = 'لم يتم تفعيل مرشّح «المحتوى متعدد اللغات (الإصدار 2)» ولا «المحتوى متعدد اللغات»، لذا ستظهر صيغة {mlang} كما هي بدل تحويلها إلى لغة القارئ. فعّل أحدهما من إدارة الموقع ← الإضافات ← المرشّحات ← إدارة المرشّحات.';

$string['enabled'] = 'تفعيل الحقول متعددة اللغات';
$string['enabled_desc'] = 'إظهار حقل إدخال لكل لغة مثبّتة بدلاً من حقل واحد، في كل حقل قابل للترجمة. لن يحتاج المحرّر إلى كتابة صيغة {mlang} يدويًا؛ يتم تكوينها تلقائيًا عند حفظ النموذج.';

$string['editors'] = 'تضمين محرّرات النص الغني';
$string['editors_desc'] = 'إضافة مبدّل لغة أيضًا إلى محرّرات الوصف والملخّص والمقدّمة ونص السؤال. أوقف هذا الخيار لقصر الميزة على الحقول النصية البسيطة مثل الاسم والعنوان.';

$string['howto'] = 'كيف تعرف اسم الحقل ونوع الصفحة؟';
$string['howto_desc'] = '<p>الإعدادان التاليان بيتكتبوا بـ <b>اسم الحقل</b> و <b>نوع الصفحة</b>. دي طريقة معرفتهم من أي صفحة في الموقع.</p>
<p><b>اسم الحقل.</b> كل حقل في نماذج Moodle موجود جوه حاوية معرّفها <code>fitem_id_&lt;اسم_الحقل&gt;</code>. اضغط كليك يمين على الحقل واختر <i>Inspect</i>، وبعدين اطلع لفوق في الشجرة لحد ما تشوف الحاوية دي — واللي بعد <code>fitem_id_</code> هو الاسم اللي تكتبه هنا.</p>
<ul>
<li><code>&lt;div id="fitem_id_name"&gt;</code> &larr; اسم الحقل هو <code>name</code></li>
<li><code>&lt;div id="fitem_id_introeditor"&gt;</code> &larr; اسم الحقل هو <code>introeditor</code></li>
<li><code>&lt;div id="fitem_id_config_text"&gt;</code> &larr; اسم الحقل هو <code>config_text</code></li>
</ul>
<p>أسرع طريقة تشوفهم كلهم مرة واحدة: وأنت في لوحة <i>Elements</i> اضغط <kbd>Ctrl</kbd>+<kbd>F</kbd> وابحث عن <code>fitem_id_</code>.</p>
<p><b>ما تقراش الاسم من الخانة الظاهرة قدامك.</b> بعد ما الإضافة تشتغل على حقل، الخانات اللي على الشاشة بتاعتها هي ومالهاش خاصية name — الحقل الحقيقي مخفي وراها. حاوية <code>fitem_id_</code> هي الصح دايماً.</p>
<p><b>نوع الصفحة.</b> موجود في وسم <code>&lt;body&gt;</code> بتاع الصفحة بالشكل ده: <code>class="… pagetype-mod-customcert-mod …"</code>. شيل البادئة <code>pagetype-</code>، فتبقى الصفحة دي <code>mod-customcert-mod</code>.</p>';

$string['extratextfields'] = 'حقول نصية قابلة للترجمة إضافية';
$string['extratextfields_desc'] = '<p><b>يضيف</b> حقولاً إلى الميزة. اسم حقل واحد في كل سطر، و <code>*</code> تطابق أي نص.</p>
<p>استخدمه لما تلاقي حقلاً يحمل نصاً معروضاً للمستخدم لكنه لسه خانة واحدة — غالباً حقلاً أضافه الموقع وليس حقلاً أصلياً في Moodle.</p>
<p>المربّع ده <b>يضيف على</b> القائمة اللي تحت ولا يستبدلها: كل اللي فيها شغّال تلقائياً من غير ما تكتب أي حاجة. ولو عايز <em>تشيل</em> حقلاً، استخدم إعداد الاستثناءات بدلاً من هنا.</p>
<p>المشمول حالياً:</p><pre>{$a}</pre>';

$string['extraexcludes'] = 'الاستثناءات';
$string['extraexcludes_desc'] = '<p><b>يشيل</b> حقولاً من الميزة فترجع خانة واحدة عادية. قاعدة في كل سطر بالشكل <code>نوع_الصفحة|اسم_الحقل</code>، و <code>*</code> تطابق أي نص. القواعد تسري على الحقول النصية والمحرّرات على السواء.</p>
<p>أمثلة:</p>
<ul>
<li><code>*|config_text</code> &mdash; حقل المحتوى في بلوك HTML، في كل الصفحات</li>
<li><code>*|introeditor</code> &mdash; وصف كل الأنشطة في الموقع</li>
<li><code>site-index|introeditor</code> &mdash; وصف الأنشطة، في الصفحة الرئيسية فقط</li>
<li><code>mod-quiz-*|*</code> &mdash; كل الحقول في كل صفحات الاختبارات</li>
<li><code>course-edit|shortname</code> &mdash; الاسم المختصر للمقرر فقط، وفي صفحة إعدادات المقرر فقط</li>
</ul>
<p>وده كمان الطريق الوحيد لإيقاف حقل من الحقول المشمولة افتراضياً: القائمة الافتراضية بتحدد مين <em>داخل</em>، وهذا الإعداد بيحدد مين <em>يخرج</em>. ولإيقاف كل المحرّرات مرة واحدة، شيل علامة الصح من «تضمين محرّرات النص الغني» بالأعلى بدل سردها هنا.</p>
<p>المستثنى حالياً:</p><pre>{$a}</pre>';

$string['translations'] = 'الترجمات';

$string['profilecategories'] = 'مجموعات حقول الملف الشخصي ثنائية اللغة';
$string['profilecategories_desc'] = '<p>حقول الملف الشخصي المخصّصة التي تكون <b>قيمتها</b> نصًا معروضًا للقارئ لا معرّفًا. اختر مجموعة، فيتحوّل كل حقل من نوع <i>نص</i> و<i>منطقة نص</i> بداخلها إلى خانة لكل لغة مثبّتة — في صفحة تعديل الملف الشخصي، وفي صفحة الحساب، وفي أي مكان آخر يظهر فيه الحقل. ويُحفظ ما يكتبه المستخدم بصيغة {mlang}، فيرى كل قارئ النص بلغته.</p>
<p>يسري هذا على الحقول النصية ومناطق النص فقط. أما القائمة المنسدلة أو مربّع الاختيار أو التاريخ أو الملف المرفوع فليس فيها نص يُترجم؛ وخيارات القائمة تُترجم في تعريف الحقل نفسه.</p>
<p>اترك المجموعة بلا تحديد إذا كانت تحمل معرّفات — فرقم جواز السفر والرقم القومي ليس لهما صيغة عربية وأخرى إنجليزية، وتقسيمهما إلى خانتين يفتح الباب للتضارب فقط.</p>
<p>وهذا هو الموضع الوحيد في الإضافة الذي تظهر فيه خانات اللغات لمن لا يملك صلاحية local/nit_mlang:edit: فالحقل يحمل بيانات صاحبه لا بيانات الموقع، والأمر مقصور على شاشات الملف الشخصي.</p>';

$string['profiletextareas'] = 'تضمين مناطق النص في تلك المجموعات';
$string['profiletextareas_desc'] = 'مُطفأ افتراضيًا، فالمجموعة المحدّدة تشمل حقول <i>النص</i> فقط. أما حقل <i>منطقة النص</i> فهو محرّر نص غني ويأخذ شريط تبويبات لغات بدل خانة لكل لغة — وهو عنصر مختلف في شكله، ويستحق أن يُفعَّل عن قصد. تفعيله يشمل مثلًا النبذة التعريفية والمؤهلات للمدرّب.';
