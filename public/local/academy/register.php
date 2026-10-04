<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. See <http://www.gnu.org/licenses/>.

/**
 * Bassthalk-style student registration — 3-step wizard (UI stage).
 *
 * Account creation / approval is wired in a later stage; this page renders the
 * measured 1:1 Bassthalk /register UI. Dropdown values are the agreed defaults
 * (admin-configurable later); the grade (الصف) list is the site's top-level
 * categories (the "Year" level).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/theme/nit/lib.php'); // theme_nit_brand_logo_url().

$PAGE->set_url(new moodle_url('/local/academy/register.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('طلب انشاء حساب');
$PAGE->set_heading('');

if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/'));
}

// الصف (Year) = site top-level visible categories.
$gradeoptions = $DB->get_records('course_categories', ['parent' => 0, 'visible' => 1], 'sortorder', 'id,name');

// Admin-configurable defaults (hardcoded for the UI stage).
$systems = ['عام', 'أزهر', 'باكالوريا'];
$divisions = [
    'عام'      => ['ادبى', 'علمى علوم', 'علمى رياضة'],
    'أزهر'     => ['علمى', 'ادبى'],
    'باكالوريا' => ['مسار طب وعلوم الحياة', 'مسار الهندسة وعلوم الحاسب', 'مسار ادارة الاعمال', 'مسار الاداب والفنون'],
];
$governorates = ['القاهرة', 'الجيزة', 'الإسكندرية', 'الدقهلية', 'البحر الأحمر', 'البحيرة', 'الفيوم', 'الغربية',
    'الإسماعيلية', 'المنوفية', 'المنيا', 'القليوبية', 'الوادي الجديد', 'السويس', 'أسوان', 'أسيوط', 'بني سويف',
    'بورسعيد', 'دمياط', 'الشرقية', 'جنوب سيناء', 'كفر الشيخ', 'مطروح', 'الأقصر', 'قنا', 'شمال سيناء', 'سوهاج'];
$religions = ['التربية الدينية الاسلامية', 'التربية الدينية المسيحية'];
$genders = ['ذكر', 'أنثى'];

$loginurl = (new moodle_url('/login/index.php'))->out(false);
$homeurl = (new moodle_url('/'))->out(false);

echo $OUTPUT->header();

// ---- icons ----
$ic = [
  'user' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>',
  'phone' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h3l2 5-2 1a12 12 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A17 17 0 0 1 4 5a2 2 0 0 1 2-2z"/></svg>',
  'id' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.6-1.6 2-2 3-2s2.4.4 3 2"/><path d="M14 9h5M14 12h5M14 15h3"/></svg>',
  'school' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-5 9 5-9 5-9-5z"/><path d="M7 12v5c0 1 2.2 2 5 2s5-1 5-2v-5"/></svg>',
  'job' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
  'mail' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>',
  'lock' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2.2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
  'eye' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>',
];

// text field with floating label
function reg_text($name, $label, $icon, $hint = '', $type = 'text', $pw = false) {
    $eye = $pw ? '<button type="button" class="reg-eye" tabindex="-1" onclick="var i=this.parentNode.querySelector(\'input\');var on=i.type===\'password\';i.type=on?\'text\':\'password\';">' . $GLOBALS['ic']['eye'] . '</button>' : '';
    $pwcls = $pw ? ' reg-haspw' : '';
    $h = $hint ? '<div class="reg-hint">' . s($hint) . '</div>' : '';
    return '<div class="reg-cell"><div class="reg-field' . $pwcls . '">'
        . '<input type="' . $type . '" name="' . $name . '" id="f_' . $name . '" placeholder=" " autocomplete="off" required>'
        . '<span class="reg-lab">' . $icon . '<span>' . s($label) . '</span></span>'
        . $eye
        . '</div>' . $h . '<div class="reg-err"></div></div>';
}
function reg_select($name, $placeholder, array $opts, $valuefield = null) {
    $o = '<option value="" selected disabled hidden></option>';
    foreach ($opts as $k => $v) {
        if (is_object($v)) { $o .= '<option value="' . (int)$v->id . '">' . format_string($v->name) . '</option>'; }
        else { $o .= '<option value="' . s($v) . '">' . s($v) . '</option>'; }
    }
    return '<div class="reg-cell"><div class="reg-field reg-sel">'
        . '<select name="' . $name . '" id="f_' . $name . '" data-ph="' . s($placeholder) . '" required>' . $o . '</select>'
        . '<span class="reg-chevron"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>'
        . '</div><div class="reg-err"></div></div>';
}
?>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Tajawal:wght@400;500;700&display=swap');
  /* reset the embedded layout wrapper */
  body, #page, #page-content, #region-main, [role="main"], .main-inner, div[role="main"] { margin:0!important; padding:0!important; max-width:none!important; width:auto!important; background:var(--nit-brand-g18-background)!important; }

  /* Every colour reads the Brand Colors "Bassthalk" group (g18) via .nit-brand-18
     (Appearance → NIT Design System → Bassthalk). */
  .nit-reg, .reg-modal-ov{ --ink:var(--nit-brand-textprimary); --muted:var(--nit-brand-textsecondary); --border:var(--nit-brand-borderprimary);
    --line:var(--nit-brand-bordersecondary); --field:var(--nit-brand-surface); --page:var(--nit-brand-background); --hover:var(--nit-brand-hoverbackground);
    --accent:var(--nit-brand-bthregisteraccent); --onaccent:var(--nit-brand-bthprevtext); --accent2:var(--nit-brand-bthregisterprogress);
    --icon:var(--nit-brand-bthfieldicon); --card:var(--nit-brand-background2); --imgteal:var(--nit-brand-bthregisterimagebg);
    --yellow:var(--nit-brand-bthprevbg); --onyellow:var(--nit-brand-bthprevtext); --err:var(--nit-brand-error);
    --ph:color-mix(in srgb, var(--nit-brand-textsecondary) 75%, var(--nit-brand-surface)); }
  .nit-reg{
    min-height:100vh; display:flex; gap:20px; padding:clamp(16px,3vw,40px); background:var(--page); color:var(--ink);
    direction:rtl; align-items:flex-start; font-family:'Tajawal','Almarai',system-ui,sans-serif; box-sizing:border-box; }
  .nit-reg *, .nit-reg *::before, .nit-reg *::after{ box-sizing:border-box; }

  .nit-reg-form{ flex:1 1 50%; background:var(--card); border-radius:12px; position:relative; padding:24px clamp(20px,2.8vw,40px) 40px; }
  .nit-reg-inner{ width:100%; max-width:512px; margin:0 auto; }

  .nit-reg-back{ position:absolute; top:24px; inset-inline-start:24px; display:inline-flex; align-items:center; gap:8px;
    border:1px solid var(--line); border-radius:10px; padding:9px 16px; background:var(--field); font-family:'Almarai',sans-serif;
    font-weight:700; font-size:14px; color:var(--ink); text-decoration:none; }
  .nit-reg-back:hover{ background:var(--hover)!important; color:var(--ink)!important; text-decoration:none; }

  .nit-reg-progress{ margin:52px 0 10px; }
  .nit-reg-progrow{ display:flex; justify-content:space-between; align-items:center; font-size:14px; font-family:'Tajawal'; margin-bottom:6px; }
  .nit-reg-progrow .pct{ color:var(--muted); }
  .nit-reg-progrow .step{ color:var(--accent); font-weight:700; }
  .nit-reg-track{ height:4px; background:var(--line); border-radius:4px; overflow:hidden; }
  .nit-reg-fill{ height:100%; background:var(--accent2); border-radius:4px; transition:width .25s ease; }

  .nit-reg-logo{ display:flex; justify-content:center; margin:14px 0 6px; }
  .nit-reg-logo img{ width:170px; height:44px; max-width:170px; max-height:44px; object-fit:contain; }
  .nit-reg h1{ font-family:'Tajawal'; font-weight:700; font-size:24px; color:var(--ink); text-align:center; margin:10px 0 0; }
  .nit-reg-sub{ font-family:'Tajawal'; font-weight:400; font-size:16px; color:var(--muted); text-align:center; line-height:1.7; margin:10px 0 24px; }

  .nit-reg-grid{ display:grid; grid-template-columns:1fr 1fr; gap:24px; }
  .nit-reg-grid.one{ grid-template-columns:1fr; }
  @media (max-width:520px){ .nit-reg-grid{ grid-template-columns:1fr; } }

  .reg-field{ position:relative; }
  .reg-field input, .reg-field select{ width:100%; height:63px; border:1px solid var(--border); border-radius:14px;
    background:var(--field); color:var(--ink); font-family:'Tajawal'; font-size:18px; padding:0 16px; text-align:start; direction:rtl; }
  .reg-field input:focus, .reg-field select:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent); }
  .reg-field.reg-haspw input{ padding-inline-end:48px; }
  .reg-eye{ position:absolute; inset-inline-end:14px; top:50%; transform:translateY(-50%); width:28px; height:28px;
    display:flex; align-items:center; justify-content:center; border:0; background:none; color:var(--muted); cursor:pointer; padding:0; }

  /* floating label (icon + text) */
  .reg-lab{ position:absolute; inset-inline-start:16px; top:50%; transform:translateY(-50%); display:flex; align-items:center; gap:8px;
    color:var(--muted); font-family:'Tajawal'; font-size:18px; pointer-events:none; transition:.15s ease; background:transparent; padding:0; }
  .reg-lab svg{ color:var(--icon); flex:none; }
  .reg-field input:focus ~ .reg-lab,
  .reg-field input:not(:placeholder-shown) ~ .reg-lab{ top:0; font-size:13px; background:var(--field); padding:0 6px; color:var(--icon); }
  .reg-hint{ font-family:'Tajawal'; font-size:12.5px; color:var(--muted); text-align:start; margin:6px 2px 0; line-height:1.5; }

  /* validation */
  .reg-field.invalid input, .reg-field.invalid select{ border-color:var(--err); box-shadow:0 0 0 3px color-mix(in srgb, var(--err) 12%, transparent); }
  .reg-err{ font-family:'Tajawal'; font-size:12.5px; color:var(--err); text-align:start; margin:6px 2px 0; display:none; }
  .reg-cell.invalid .reg-err{ display:block; }

  /* terms modal */
  .reg-modal-ov{ position:fixed; inset:0; background:color-mix(in srgb, var(--ink) 55%, transparent); display:none; align-items:center; justify-content:center; z-index:9999; padding:20px; }
  .reg-modal-ov.open{ display:flex; }
  .reg-modal{ background:var(--field); border-radius:16px; width:100%; max-width:640px; max-height:86vh; display:flex; flex-direction:column; overflow:hidden; direction:rtl; font-family:'Tajawal'; }
  .reg-modal-head{ background:var(--accent); color:var(--onaccent); padding:20px 22px; display:flex; align-items:flex-start; gap:12px; }
  .reg-modal-head .t{ font-weight:700; font-size:20px; }
  .reg-modal-head .s{ font-size:13px; opacity:.92; margin-top:4px; }
  .reg-modal-x{ margin-inline-start:auto; width:34px; height:34px; border-radius:50%; background:color-mix(in srgb, var(--onaccent) 20%, transparent); border:0; color:var(--onaccent); cursor:pointer; font-size:18px; line-height:1; flex:none; }
  .reg-modal-body{ padding:18px 22px; overflow-y:auto; display:flex; flex-direction:column; gap:14px; }
  .reg-modal-sec{ border:1px solid var(--line); border-radius:12px; padding:16px; }
  .reg-modal-sec h4{ font-weight:700; font-size:16px; color:var(--ink); margin:0 0 8px; }
  .reg-modal-sec p{ font-size:14px; color:var(--ink); line-height:1.8; margin:0 0 6px; }
  .reg-modal-foot{ padding:14px 22px 18px; border-top:1px solid var(--line); }
  .reg-modal-agree{ width:100%; height:50px; border:0; border-radius:12px; background:var(--accent); color:var(--onaccent); font-family:'Tajawal'; font-weight:700; font-size:16px; cursor:pointer; }

  /* selects */
  .reg-field.reg-sel select{ appearance:none; -webkit-appearance:none; color:var(--ink); padding-inline-end:44px; }
  .reg-field.reg-sel select:required:invalid{ color:var(--ph); }
  .reg-field.reg-sel select option{ color:var(--ink); }
  .reg-chevron{ position:absolute; inset-inline-end:14px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; display:flex; }

  .nit-reg-row{ margin-top:18px; }
  .nit-reg-btns{ display:flex; gap:12px; margin-top:26px; }
  .btn-next{ flex:1; height:52px; border-radius:12px; border:2px solid var(--accent); background:var(--accent); color:var(--onaccent);
    font-family:'Tajawal'; font-weight:500; font-size:16px; cursor:pointer; }
  .btn-next:hover{ background:color-mix(in srgb, var(--accent) 88%, black); border-color:color-mix(in srgb, var(--accent) 88%, black); }
  .btn-prev{ flex:0 0 32%; height:52px; border-radius:12px; border:2px solid var(--yellow); background:var(--yellow); color:var(--onyellow);
    font-family:'Tajawal'; font-weight:500; font-size:16px; cursor:pointer; }
  .btn-prev:hover{ filter:brightness(.96); }

  .nit-reg-terms{ display:flex; align-items:center; gap:10px; margin-top:20px;
    border:1px solid color-mix(in srgb, var(--accent) 30%, var(--field)); background:color-mix(in srgb, var(--accent) 6%, var(--field));
    border-radius:10px; padding:12px 14px; font-family:'Tajawal'; font-size:14px; color:var(--ink); }
  .nit-reg-terms input{ width:18px; height:18px; accent-color:var(--accent); flex:none; }
  .nit-reg-terms a, .nit-reg-terms a:hover{ color:var(--accent)!important; font-weight:700; text-decoration:none; }

  .nit-reg-bottom{ text-align:center; margin-top:18px; font-family:'Tajawal'; font-size:16px; color:var(--muted); }
  .nit-reg-bottom a, .nit-reg-bottom a:hover{ color:var(--muted)!important; text-decoration:none; }

  .reg-step{ display:none; }
  .reg-step.active{ display:block; }

  /* illustration card — sticky, viewport-tall, whole image shown (contain) */
  .nit-reg-side{ flex:1 1 50%; position:sticky; top:clamp(16px,3vw,40px);
    height:calc(100vh - 2*clamp(16px,3vw,40px)); border-radius:12px; overflow:hidden; background:var(--imgteal); }
  .nit-reg-side img, .nit-reg-side img.icon{ position:absolute!important; inset:0!important; width:100%!important; height:100%!important;
    max-width:none!important; max-height:none!important; object-fit:contain!important; display:block; }
  @media (max-width:991px){ .nit-reg-side{ display:none; } .nit-reg-form{ flex-basis:100%; } }
</style>

<div class="nit-reg nit-brand-18">
  <div class="nit-reg-form">
    <a class="nit-reg-back" href="<?php echo $homeurl; ?>"><span>الرجوع للرئيسية</span>
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
    </a>
    <div class="nit-reg-inner">
      <div class="nit-reg-progress">
        <div class="nit-reg-progrow"><span class="pct" id="reg-pct">30%</span><span class="step" id="reg-step-label">الخطوه الاولى</span></div>
        <div class="nit-reg-track"><div class="nit-reg-fill" id="reg-fill" style="width:30%"></div></div>
      </div>

      <div class="nit-reg-logo"><img src="<?php echo s(theme_nit_brand_logo_url()); ?>" alt="<?php echo s(format_string($SITE->fullname)); ?>"></div>
      <h1>طلب انشاء حساب :</h1>
      <p class="nit-reg-sub">ادخل بياناتك بشكل صحيح وسيتم مراجعة طلبك خلال ساعات لـ بضع ايام, وتقدر تسجل دخول عشان تشوف حالة الطلب بتاعك</p>

      <form method="post" action="<?php echo $PAGE->url->out(false); ?>" id="regform" autocomplete="off">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- STEP 1 -->
        <div class="reg-step active" data-step="1">
          <div class="nit-reg-grid">
            <?php echo reg_text('firstname', 'الاسم الأول', $ic['user'], 'اكتب اسمك بالعربي زي اللي موجود في البطاقة'); ?>
            <?php echo reg_text('secondname', 'الاسم الثاني', $ic['user'], 'اكتب اسمك بالعربي زي اللي موجود في البطاقة'); ?>
          </div>
          <div class="nit-reg-grid nit-reg-row">
            <?php echo reg_text('thirdname', 'الاسم الثالث', $ic['user'], 'اكتب اسمك بالعربي زي اللي موجود في البطاقة'); ?>
            <?php echo reg_text('lastname', 'الاسم الأخير', $ic['user'], 'اكتب اسمك بالعربي زي اللي موجود في البطاقة'); ?>
          </div>
          <div class="nit-reg-grid one nit-reg-row"><?php echo reg_text('phone', 'رقم الهاتف', $ic['phone'], '', 'tel'); ?></div>
          <div class="nit-reg-grid one nit-reg-row"><?php echo reg_select('grade', 'اختر الصف الدراسي', $gradeoptions); ?></div>
          <div class="nit-reg-grid one nit-reg-row"><?php echo reg_text('national', 'رقم الطالب القومي', $ic['id'], 'رقم بطاقة الطالب نفسه، مش ولي الأمر'); ?></div>
          <div class="nit-reg-btns"><button type="button" class="btn-next" data-next>التالي</button></div>
          <div class="nit-reg-bottom">يوجد لديك حساب بالفعل؟ <a href="<?php echo $loginurl; ?>">ادخل إلى حسابك الآن !</a></div>
        </div>

        <!-- STEP 2 -->
        <div class="reg-step" data-step="2">
          <div class="nit-reg-grid">
            <?php echo reg_text('fatherphone', 'رقم هاتف الأب', $ic['phone'], '', 'tel'); ?>
            <?php echo reg_text('motherphone', 'رقم هاتف الأم', $ic['phone'], '', 'tel'); ?>
          </div>
          <div class="nit-reg-grid nit-reg-row">
            <?php echo reg_text('school', 'اسم المدرسة', $ic['school']); ?>
            <?php echo reg_text('guardianjob', 'مهنة ولي الأمر', $ic['job']); ?>
          </div>
          <div class="nit-reg-grid nit-reg-row">
            <?php echo reg_select('studysystem', 'النظام الدراسي', $systems); ?>
            <?php echo reg_select('governorate', 'المحافظة', $governorates); ?>
          </div>
          <div class="nit-reg-grid one nit-reg-row"><?php echo reg_select('division', 'اختر الشعبة الدراسية', []); ?></div>
          <div class="nit-reg-btns"><button type="button" class="btn-prev" data-prev>السابق</button><button type="button" class="btn-next" data-next>التالي</button></div>
          <div class="nit-reg-bottom">يوجد لديك حساب بالفعل؟ <a href="<?php echo $loginurl; ?>">ادخل إلى حسابك الآن !</a></div>
        </div>

        <!-- STEP 3 -->
        <div class="reg-step" data-step="3">
          <div class="nit-reg-grid one"><?php echo reg_select('religion', 'ما مادة التربية الدينية التي تدرسها؟', $religions); ?></div>
          <div class="nit-reg-grid one nit-reg-row"><?php echo reg_select('gender', 'النوع', $genders); ?></div>
          <div class="nit-reg-grid one nit-reg-row"><?php echo reg_text('email', 'البريد الإلكتروني', $ic['mail'], '', 'email'); ?></div>
          <div class="nit-reg-grid nit-reg-row">
            <?php echo reg_text('password', 'كلمة السر', $ic['lock'], '', 'password', true); ?>
            <?php echo reg_text('password2', 'تأكيد كلمة السر', $ic['lock'], '', 'password', true); ?>
          </div>
          <label class="nit-reg-terms"><input type="checkbox" name="agree" id="f_agree"> <span>أوافق على <a href="#" class="reg-terms-open">الشروط والأحكام</a> واتفاقية شراء الكورس في منصة بسطتهالك.</span></label>
          <div class="reg-err" id="agree-err" style="margin-top:6px;"></div>
          <div class="nit-reg-btns"><button type="button" class="btn-prev" data-prev>السابق</button><button type="submit" class="btn-next">طلب انشاء حساب !</button></div>
          <div class="nit-reg-bottom">يوجد لديك حساب بالفعل؟ <a href="<?php echo $loginurl; ?>">ادخل إلى حسابك الآن !</a></div>
        </div>
      </form>
    </div>
  </div>
  <aside class="nit-reg-side"><?php echo $OUTPUT->image_icon('bassthalk_register', '', 'theme_nit'); ?></aside>
</div>

<div class="reg-modal-ov nit-brand-18" id="reg-terms-modal">
  <div class="reg-modal" role="dialog" aria-modal="true">
    <div class="reg-modal-head">
      <div><div class="t">اتفاقية شراء الكورس</div><div class="s">اقرأ البنود كويس قبل ما توافق، موافقتك الإلكترونية ملزمة.</div></div>
      <button type="button" class="reg-modal-x" data-close>&times;</button>
    </div>
    <div class="reg-modal-body">
      <div class="reg-modal-sec"><p>برجاء قراءة البنود التالية بعناية. بالضغط على زر «موافق على الشروط والأحكام» فإنك تقر بأنك قد قرأت هذه الاتفاقية وفهمتها وتوافق على الالتزام بجميع بنودها.</p></div>
      <div class="reg-modal-sec"><h4>أولاً: حقوق الملكية الفكرية</h4>
        <p>جميع المحتويات التعليمية المعروضة على منصة بسطتهالك، بما في ذلك الفيديوهات والملفات والملخصات والاختبارات والتسجيلات الصوتية والصور وأي مواد تعليمية أخرى، هي ملك للمنصة أو للجهات وأصحاب الحقوق الذين منحوا المنصة حق نشرها واستخدامها.</p>
        <p>يحظر نسخ أو تصوير أو تسجيل أو إعادة نشر أو توزيع أو بيع أو مشاركة أي جزء من المحتوى بأي وسيلة دون موافقة كتابية مسبقة من المنصة أو صاحب الحق.</p></div>
      <div class="reg-modal-sec"><h4>ثانيًا: سياسة الرصيد والمدفوعات</h4>
        <p>جميع المبالغ التي يتم شحنها في محفظة الطالب داخل منصة بسطتهالك لا يمكن استردادها نقدًا أو تحويلها إلى أموال خارج المنصة بعد إتمام عملية الشحن.</p>
        <p>يحق للطالب استخدام الرصيد المشحون بالكامل في شراء أي من الكورسات أو الخدمات التعليمية المتاحة داخل المنصة.</p></div>
      <div class="reg-modal-sec"><h4>ثالثًا: إلغاء الاشتراك في الكورس</h4>
        <p>بمجرد تفعيل الوصول إلى محتوى كورس، لا يمكن إلغاء الاشتراك أو استرداد قيمته، حيث يُعد المحتوى الرقمي خدمة مُستهلكة فور إتاحتها.</p></div>
    </div>
    <div class="reg-modal-foot"><button type="button" class="reg-modal-agree" data-agree>موافق علي جميع الشروط</button></div>
  </div>
</div>

<script>
(function(){
  var DIV = <?php echo json_encode($divisions, JSON_UNESCAPED_UNICODE); ?>;
  var DEFAULT_SYSTEM = 'عام'; // admin default (hardcoded for now)
  var steps = [].slice.call(document.querySelectorAll('.reg-step'));
  var meta = [{p:'30%',l:'الخطوه الاولى'},{p:'60%',l:'الخطوه التانيه'},{p:'90%',l:'الخطوه الاخيره'}];
  var cur = 0;
  function show(i){
    cur = Math.max(0, Math.min(steps.length-1, i));
    steps.forEach(function(s,idx){ s.classList.toggle('active', idx===cur); });
    document.getElementById('reg-pct').textContent = meta[cur].p;
    document.getElementById('reg-step-label').textContent = meta[cur].l;
    document.getElementById('reg-fill').style.width = meta[cur].p;
    window.scrollTo(0,0);
  }

  // ---- validation ----
  function setErr(field, msg){
    var cell = field.closest('.reg-cell') || field.closest('.reg-field') || field.parentNode;
    var f = field.closest('.reg-field'); if(f){ f.classList.add('invalid'); }
    if(cell){ cell.classList.add('invalid'); var e=cell.querySelector('.reg-err'); if(e){ e.textContent = msg; } }
  }
  function clearErr(stepEl){
    stepEl.querySelectorAll('.invalid').forEach(function(n){ n.classList.remove('invalid'); });
    stepEl.querySelectorAll('.reg-err').forEach(function(n){ n.textContent=''; });
  }
  function validateStep(stepEl){
    clearErr(stepEl);
    var ok = true, first = null;
    stepEl.querySelectorAll('input[required], select[required]').forEach(function(f){
      var v = (f.value||'').trim();
      var bad = '';
      if(!v){ bad = 'هذا الحقل مطلوب'; }
      else if(f.type==='email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)){ bad = 'بريد إلكتروني غير صحيح'; }
      else if((f.type==='tel' || /phone|national/.test(f.name)) && !/^[0-9]{7,15}$/.test(v)){ bad = 'ادخل رقمًا صحيحًا (أرقام فقط)'; }
      else if(f.id==='f_password' && v.length < 8){ bad = 'كلمة السر 8 أحرف على الأقل'; }
      else if(f.id==='f_password2'){ var p=document.getElementById('f_password'); if(p && v!==p.value){ bad = 'كلمتا السر غير متطابقتين'; } }
      if(bad){ ok=false; setErr(f, bad); if(!first){ first=f; } }
    });
    // terms on last step
    var agree = stepEl.querySelector('#f_agree');
    if(agree && !agree.checked){ ok=false; var ae=document.getElementById('agree-err'); if(ae){ ae.textContent='لازم توافق على الشروط والأحكام'; } if(!first){ first=agree; } }
    if(first){ first.focus(); try{ first.scrollIntoView({block:'center',behavior:'smooth'}); }catch(e){} }
    return ok;
  }

  document.querySelectorAll('[data-next]').forEach(function(b){ b.addEventListener('click', function(){
    if(validateStep(steps[cur])){ show(cur+1); }
  }); });
  document.querySelectorAll('[data-prev]').forEach(function(b){ b.addEventListener('click', function(){ show(cur-1); }); });
  // block final submit if last step invalid
  document.getElementById('regform').addEventListener('submit', function(ev){
    if(!validateStep(steps[cur])){ ev.preventDefault(); }
  });
  // clear a field's error as the user fixes it
  document.querySelectorAll('.reg-field input, .reg-field select').forEach(function(f){
    f.addEventListener('input', function(){ var c=f.closest('.reg-cell'); if(c){ c.classList.remove('invalid'); var e=c.querySelector('.reg-err'); if(e) e.textContent=''; } var ff=f.closest('.reg-field'); if(ff) ff.classList.remove('invalid'); });
  });

  // placeholder color for selects (show data-ph until a value is chosen)
  document.querySelectorAll('.reg-sel select').forEach(function(sel){
    var ph = sel.getAttribute('data-ph');
    var o0 = sel.querySelector('option[value=""]'); if(o0){ o0.textContent = ph; }
    function sync(){ sel.style.color = sel.value ? 'var(--ink)' : 'var(--ph)'; }
    sel.addEventListener('change', sync); sync();
  });

  // division depends on study system
  var sysSel = document.getElementById('f_studysystem');
  var divSel = document.getElementById('f_division');
  function fillDivisions(){
    var list = DIV[sysSel.value] || [];
    var ph = divSel.getAttribute('data-ph');
    divSel.innerHTML = '<option value="" selected disabled hidden>'+ph+'</option>' + list.map(function(d){ return '<option value="'+d+'">'+d+'</option>'; }).join('');
    divSel.style.color = 'var(--ph)';
  }
  if(sysSel && divSel){
    sysSel.addEventListener('change', fillDivisions);
    // admin default: pre-select the default system and fill its divisions.
    if(DEFAULT_SYSTEM && DIV[DEFAULT_SYSTEM]){ sysSel.value = DEFAULT_SYSTEM; sysSel.style.color = 'var(--ink)'; }
    fillDivisions();
  }

  // ---- terms modal ----
  var modal = document.getElementById('reg-terms-modal');
  function openModal(e){ if(e) e.preventDefault(); modal.classList.add('open'); }
  function closeModal(){ modal.classList.remove('open'); }
  document.querySelectorAll('.reg-terms-open').forEach(function(a){ a.addEventListener('click', openModal); });
  modal.querySelectorAll('[data-close]').forEach(function(b){ b.addEventListener('click', closeModal); });
  modal.addEventListener('click', function(e){ if(e.target===modal){ closeModal(); } });
  modal.querySelector('[data-agree]').addEventListener('click', function(){
    var a=document.getElementById('f_agree'); if(a){ a.checked=true; var ae=document.getElementById('agree-err'); if(ae) ae.textContent=''; } closeModal();
  });
})();
</script>
<?php
echo $OUTPUT->footer();
