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
 * Public Terms of Service / Privacy Policy pages.
 *
 *   GET /local/multitopics/legal.php?doc=terms|privacy [&lang=ar|en] [&embedded=1]
 *
 * The mobile app opens these in a webview (design_system.php → links block); the
 * web shows the same content inside the theme. `embedded=1` returns a bare,
 * chrome-less HTML document (no navbar/footer) — that is what the app requests.
 *
 * Content is per academy: an admin can override it with the config keys
 *   local_multitopics/{terms,privacy}_{en,ar}   (raw HTML)
 * otherwise a sensible bilingual default is shown, personalised with the site
 * name. Anonymous + read-only.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Read the embedded flag from the raw query BEFORE bootstrap so the app's
// chrome-less request can run session-less (never bounced to a login/theme).
$embedded = !empty($_GET['embedded']);
if ($embedded) {
    define('NO_MOODLE_COOKIES', true);
}

require(__DIR__ . '/../../config.php');

global $SITE, $CFG, $OUTPUT, $PAGE;

$doc = optional_param('doc', 'terms', PARAM_ALPHANUMEXT);
$doc = str_replace('-', '', $doc);   // accept 'delete-account' as 'delete'
if (!in_array($doc, ['terms', 'privacy', 'delete'], true)) {
    $doc = 'terms';
}
$lang = optional_param('lang', '', PARAM_ALPHA);
if ($lang !== 'ar' && $lang !== 'en') {
    $lang = (strpos(current_language(), 'ar') === 0) ? 'ar' : 'en';
}
$isar = ($lang === 'ar');
$sitename = format_string($SITE->fullname ?? 'Academy');
$support  = trim((string) ($CFG->supportemail ?? ''));
// The app + developer name shown on the store listing (one published app). Set
// platform-wide via local_multitopics/app_name; falls back to the site name.
$appname = trim((string) (get_config('local_multitopics', 'app_name') ?: $sitename));

// ── Titles ───────────────────────────────────────────────────────────────────
$titles = [
    'terms'   => ['en' => 'Terms of Service',   'ar' => 'شروط الاستخدام'],
    'privacy' => ['en' => 'Privacy Policy',      'ar' => 'سياسة الخصوصية'],
    'delete'  => ['en' => 'Delete Your Account', 'ar' => 'حذف حسابك'],
];
$title = $titles[$doc][$lang];

// ── Content: admin override (config) or the bilingual default ─────────────────
$override = get_config('local_multitopics', $doc . '_' . $lang);
if (is_string($override) && trim($override) !== '') {
    $body = $override;   // trusted admin-authored HTML.
} else {
    $body = local_multitopics_legal_default($doc, $isar, $appname, $support);
}

// ── Render ────────────────────────────────────────────────────────────────────
if ($embedded) {
    // Bare, self-contained document for the app webview — no theme chrome.
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $dir = $isar ? 'rtl' : 'ltr';
    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="' . $e($lang) . '" dir="' . $dir . '"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $e($title) . '</title>'
        . '<style>'
        . ':root{color-scheme:light dark;}'
        . 'body{margin:0;padding:20px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Tahoma,Arial,sans-serif;'
        . 'line-height:1.8;color:#1a2230;background:#fff;font-size:15px;}'
        . 'h1{font-size:22px;margin:0 0 4px;} h2{font-size:17px;margin:22px 0 8px;}'
        . '.updated{color:#6b7280;font-size:13px;margin-bottom:20px;}'
        . 'a{color:#1e7d67;} ul{padding-inline-start:20px;}'
        . '@media (prefers-color-scheme: dark){body{background:#0f151c;color:#e7edf3;}.updated{color:#9aa6b0;}a{color:#4bb699;}}'
        . '</style></head><body>'
        . '<h1>' . $e($title) . '</h1>'
        . '<div class="updated">' . ($isar ? 'آخر تحديث' : 'Last updated') . ': ' . date('Y') . '</div>'
        . $body
        . '</body></html>';
    exit;
}

// Themed web page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/multitopics/legal.php', ['doc' => $doc]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($sitename);
echo $OUTPUT->header();
echo html_writer::tag('h1', s($title));
echo html_writer::div(($isar ? 'آخر تحديث' : 'Last updated') . ': ' . date('Y'),
    '', ['style' => 'color:var(--nit-brand-textsecondary,#6b7280);margin-bottom:20px;']);
echo html_writer::div($body, '', ['style' => 'max-width:820px;line-height:1.8;']);
echo $OUTPUT->footer();

/**
 * The default (editable) Terms / Privacy body as HTML, personalised with the
 * academy name. Boilerplate starting point — academies should review it.
 *
 * @param string $doc 'terms' | 'privacy'
 * @param bool $isar Arabic?
 * @param string $name academy name
 * @param string $support support email ('' if none)
 * @return string HTML
 */
function local_multitopics_legal_default(string $doc, bool $isar, string $name, string $support): string {
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $contact = $support !== ''
        ? ($isar ? "راسلنا على <a href=\"mailto:$support\">$support</a>." : "Contact us at <a href=\"mailto:$support\">$support</a>.")
        : ($isar ? 'تواصل معنا عبر بيانات التواصل داخل التطبيق.' : 'Reach us through the contact details in the app.');

    if ($doc === 'terms') {
        if ($isar) {
            return "<p>مرحبًا بك في «$n». باستخدامك للمنصة والتطبيق فإنك توافق على الشروط التالية.</p>"
                . "<h2>الحساب</h2><ul>"
                . "<li>أنت مسؤول عن سرية بيانات دخولك وعن كل نشاط يتم عبر حسابك.</li>"
                . "<li>يجب أن تكون البيانات التي تقدّمها صحيحة وحديثة.</li></ul>"
                . "<h2>المحتوى التعليمي</h2><ul>"
                . "<li>المحتوى (الفيديوهات والملفات والاختبارات) مملوك للمنصة أو لمقدّميه، ومخصّص لاستخدامك الشخصي فقط.</li>"
                . "<li>يُمنع تسجيل المحتوى أو إعادة نشره أو مشاركته بأي وسيلة.</li></ul>"
                . "<h2>المدفوعات</h2><ul>"
                . "<li>الاشتراكات والكورسات المدفوعة تُتاح بعد إتمام الدفع، وتخضع لسياسة الاسترداد المعلنة عند الشراء.</li></ul>"
                . "<h2>إيقاف الخدمة</h2>"
                . "<p>يحق للمنصة إيقاف أو إنهاء الحساب عند مخالفة هذه الشروط.</p>"
                . "<h2>التواصل</h2><p>$contact</p>";
        }
        return "<p>Welcome to “$n”. By using this platform and app you agree to the following terms.</p>"
            . "<h2>Your account</h2><ul>"
            . "<li>You are responsible for keeping your login details private and for all activity on your account.</li>"
            . "<li>The information you provide must be accurate and up to date.</li></ul>"
            . "<h2>Course content</h2><ul>"
            . "<li>All content (videos, files, quizzes) belongs to the platform or its providers and is for your personal use only.</li>"
            . "<li>Recording, redistributing or sharing the content in any form is prohibited.</li></ul>"
            . "<h2>Payments</h2><ul>"
            . "<li>Paid subscriptions and courses are unlocked after payment and are subject to the refund policy shown at purchase.</li></ul>"
            . "<h2>Suspension</h2>"
            . "<p>We may suspend or terminate an account that breaches these terms.</p>"
            . "<h2>Contact</h2><p>$contact</p>";
    }

    if ($doc === 'delete') {
        // The registered-email step; softened when no support address is set.
        $mail = $support !== ''
            ? ($isar ? "<a href=\"mailto:$support?subject=%D8%AD%D8%B0%D9%81%20%D8%A7%D9%84%D8%AD%D8%B3%D8%A7%D8%A8\">$support</a>"
                     : "<a href=\"mailto:$support?subject=Delete%20my%20account\">$support</a>")
            : ($isar ? 'بريد الدعم' : 'our support email');
        if ($isar) {
            return "<p>توضّح هذه الصفحة كيفية طلب حذف حسابك والبيانات المرتبطة به في تطبيق «$n».</p>"
                . "<h2>كيفية طلب الحذف</h2><ol>"
                . "<li><b>من داخل التطبيق:</b> افتح «$n» ثم اذهب إلى <b>الملف الشخصي ← الإعدادات ← حذف الحساب</b> وأكّد العملية.</li>"
                . "<li><b>عبر البريد الإلكتروني:</b> أرسل من بريدك المسجَّل رسالة إلى $mail بعنوان <b>«حذف الحساب»</b>. نتحقق من الطلب وننفّذه خلال 30 يومًا.</li>"
                . "</ol>"
                . "<h2>البيانات التي تُحذف</h2><ul>"
                . "<li>ملفك الشخصي: الاسم والبريد الإلكتروني ورقم الهاتف والصورة الشخصية.</li>"
                . "<li>بياناتك التعليمية: التسجيلات في الكورسات والتقدّم ومحاولات الاختبارات والتسليمات.</li>"
                . "<li>بيانات الدخول وبيانات الجهاز/الجلسة.</li></ul>"
                . "<h2>البيانات التي يتم الاحتفاظ بها ومدّتها</h2><ul>"
                . "<li><b>سجلات الدفع والفواتير</b> يُحتفظ بها حتى <b>5 سنوات</b> حسبما تقتضيه قوانين المحاسبة والضرائب، ثم تُحذف، ولا تُستخدم لأي غرض آخر.</li>"
                . "<li><b>النسخ الاحتياطية</b> التي تحتوي على بياناتك تُمحى ضمن دورتها المعتادة خلال <b>30 يومًا</b> من الحذف.</li></ul>"
                . "<p>تُنفَّذ جميع الطلبات خلال 30 يومًا. $contact</p>";
        }
        return "<p>This page explains how to request deletion of your account and associated data in the “$n” app.</p>"
            . "<h2>How to request deletion</h2><ol>"
            . "<li><b>In the app:</b> open “$n”, go to <b>Profile → Settings → Delete account</b>, and confirm.</li>"
            . "<li><b>By email:</b> from your registered email address, send a message to $mail with the subject <b>“Delete my account”</b>. We verify the request and process it within 30 days.</li>"
            . "</ol>"
            . "<h2>What is deleted</h2><ul>"
            . "<li>Your profile: name, email, phone number and profile photo.</li>"
            . "<li>Your learning data: course enrolments, progress, quiz attempts and submissions.</li>"
            . "<li>Your login credentials and device/session data.</li></ul>"
            . "<h2>What is kept, and for how long</h2><ul>"
            . "<li><b>Payment &amp; invoice records</b> are retained for up to <b>5 years</b> where required by applicable tax/accounting law, then deleted. They are not used for any other purpose.</li>"
            . "<li><b>Backups</b> containing your data are purged on their normal rotation, within <b>30 days</b> of the deletion request.</li></ul>"
            . "<p>Requests are completed within 30 days. $contact</p>";
    }

    // Privacy.
    if ($isar) {
        return "<p>تحترم «$n» خصوصيتك. توضّح هذه السياسة البيانات التي نجمعها وكيفية استخدامها.</p>"
            . "<h2>البيانات التي نجمعها</h2><ul>"
            . "<li>بيانات الحساب: الاسم والبريد الإلكتروني ورقم الهاتف.</li>"
            . "<li>بيانات الاستخدام: تقدّمك في الكورسات ونتائج الاختبارات.</li>"
            . "<li>بيانات تقنية: نوع الجهاز والإصدار لأغراض التشغيل والدعم.</li></ul>"
            . "<h2>كيف نستخدمها</h2><ul>"
            . "<li>لتقديم الخدمة التعليمية وحفظ تقدّمك.</li>"
            . "<li>للتواصل معك بخصوص حسابك واشتراكاتك.</li>"
            . "<li>لتحسين المنصة وحماية المحتوى من إعادة النشر.</li></ul>"
            . "<h2>المشاركة</h2>"
            . "<p>لا نبيع بياناتك. نشاركها فقط مع مزوّدي الخدمة اللازمين لتشغيل المنصة (مثل بوابات الدفع) وبالقدر المطلوب.</p>"
            . "<h2>حقوقك</h2>"
            . "<p>يمكنك طلب تعديل بياناتك أو حذف حسابك في أي وقت.</p>"
            . "<h2>التواصل</h2><p>$contact</p>";
    }
    return "<p>“$n” respects your privacy. This policy explains what we collect and how we use it.</p>"
        . "<h2>What we collect</h2><ul>"
        . "<li>Account data: your name, email and phone number.</li>"
        . "<li>Usage data: your course progress and quiz results.</li>"
        . "<li>Technical data: device type and app version, for operation and support.</li></ul>"
        . "<h2>How we use it</h2><ul>"
        . "<li>To provide the learning service and save your progress.</li>"
        . "<li>To contact you about your account and subscriptions.</li>"
        . "<li>To improve the platform and protect content from redistribution.</li></ul>"
        . "<h2>Sharing</h2>"
        . "<p>We do not sell your data. We share it only with the service providers needed to run the platform (e.g. payment gateways), and only as required.</p>"
        . "<h2>Your rights</h2>"
        . "<p>You can ask us to correct your data or delete your account at any time.</p>"
        . "<h2>Contact</h2><p>$contact</p>";
}
