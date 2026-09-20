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
 * The T1 "Profile & settings" page — the design, over the learner's REAL data.
 *
 * A bespoke layout (identity card + personal details + preferences + a settings
 * nav) that reads and writes the real user record + real preferences, and links
 * the other nav items to Moodle's real pages (change password, notification
 * preferences, payment history, certificates). Email stays read-only here because
 * Moodle guards email changes with a confirmation step on /user/edit.php — that
 * secure flow is where the email is changed.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$context = context_user::instance($USER->id);
$pageurl = new moodle_url('/local/academy/profile.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('profile') . ' — ' . format_string($SITE->shortname));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-profile-page');

$isar = (strpos(current_language(), 'ar') === 0);
$t = fn(string $en, string $ar) => $isar ? $ar : $en;

// ── Save (personal details + language preference) ────────────────────────────
if (data_submitted() && confirm_sesskey()) {
    $upd = new stdClass();
    $upd->id = (int) $USER->id;
    $upd->firstname = trim(required_param('firstname', PARAM_TEXT));
    $upd->lastname  = trim(required_param('lastname', PARAM_TEXT));
    $upd->phone1    = trim(optional_param('phone', '', PARAM_TEXT));
    $upd->description = optional_param('bio', '', PARAM_TEXT);
    $upd->descriptionformat = FORMAT_HTML;
    $lang = optional_param('lang', '', PARAM_LANG);
    if ($lang !== '') {
        $upd->lang = $lang;
    }
    if ($upd->firstname !== '' && $upd->lastname !== '') {
        try {
            user_update_user($upd, false, true);
            // Reflect immediately in this session.
            $USER->firstname = $upd->firstname;
            $USER->lastname  = $upd->lastname;
            if ($lang !== '') {
                $USER->lang = $lang;
            }
            redirect($pageurl, $t('Your changes were saved.', 'تم حفظ التغييرات.'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (\Throwable $ex) {
            redirect($pageurl, $t('Could not save your changes.', 'تعذّر حفظ التغييرات.'),
                null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

// ── Real data ────────────────────────────────────────────────────────────────
$fullname = fullname($USER);
$initials = \core_text::strtoupper(\core_text::substr(trim($USER->firstname), 0, 1)
    . \core_text::substr(trim($USER->lastname), 0, 1));
$joined = $USER->timecreated ? userdate($USER->timecreated, '%B %Y') : '';
$userpic = new user_picture($USER);
$userpic->size = 156;
$picurl = $userpic->get_url($PAGE)->out(false);
$hasrealpic = !empty($USER->picture);
$e = fn($s) => s($s);

// Real destinations for the settings nav.
$nav = [
    'security'      => ['label' => $t('Security', 'الأمان'),        'icon' => '🔒', 'url' => (new moodle_url('/login/change_password.php'))->out(false)],
    'notifications' => ['label' => $t('Notifications', 'الإشعارات'), 'icon' => '🔔', 'url' => (new moodle_url('/message/notificationpreferences.php', ['userid' => $USER->id]))->out(false)],
];
if (file_exists($CFG->dirroot . '/local/payments/history.php')) {
    $nav['billing'] = ['label' => $t('Billing & invoices', 'الفواتير'), 'icon' => '🧾', 'url' => (new moodle_url('/local/payments/history.php'))->out(false)];
}
$nav['certs'] = ['label' => $t('Certificates', 'الشهادات'), 'icon' => '🎓', 'url' => (new moodle_url('/course/index.php'))->out(false)];

echo $OUTPUT->header();
?>
<style>
  .nit-prof{ --t-accent: var(--nit-brand-primary, #0E7C66); --t-on: var(--nit-brand-on-primary,#fff);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; color:var(--t-ink,#16191D);
    max-width:1240px; margin:0 auto; padding:8px 4px 60px; }
  .nit-prof h1{ margin:0 0 28px; font-size:clamp(30px,4vw,38px); font-weight:250; letter-spacing:-0.03em; color:var(--t-ink,#16191D); }
  .nit-prof-grid{ display:grid; grid-template-columns:252px 1fr; gap:30px; align-items:start; }
  @media (max-width: 820px){ .nit-prof-grid{ grid-template-columns:1fr; } }
  .nit-prof-nav{ background:var(--t-bg,#fff); border:1px solid var(--t-border,#EDEDE9); border-radius:16px; padding:8px; display:flex; flex-direction:column; gap:2px; }
  .nit-prof-nav a{ display:flex; align-items:center; gap:11px; padding:11px 13px; border-radius:9px; font-size:14px; color:var(--t-muted2,#3D4752); text-decoration:none; }
  .nit-prof-nav a:hover{ background:var(--t-surface,#FAFAF8); color:var(--t-ink,#16191D); }
  .nit-prof-nav a.on{ background:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 9%, transparent); color:var(--t-accent); font-weight:600; }
  .nit-prof-nav a .ic{ width:20px; text-align:center; color:var(--t-muted,#8A8A82); }
  .nit-prof-nav a.on .ic{ color:var(--t-accent); }
  .nit-prof-main{ display:flex; flex-direction:column; gap:18px; }
  .nit-prof-card{ background:var(--t-bg,#fff); border:1px solid var(--t-border,#EDEDE9); border-radius:16px; }
  .nit-prof-id{ padding:26px; display:flex; align-items:center; gap:22px; flex-wrap:wrap; }
  .nit-prof-av{ width:78px; height:78px; border-radius:50%; object-fit:cover; background:linear-gradient(145deg, var(--t-accent), color-mix(in srgb, var(--t-accent) 60%, #16A98B)); color:#fff; display:grid; place-items:center; font-size:26px; font-weight:500; flex:none; }
  .nit-prof-id .who{ flex:1; min-width:180px; }
  .nit-prof-id .nm{ font-size:22px; font-weight:550; letter-spacing:-0.02em; }
  .nit-prof-id .sub{ font-size:13px; color:var(--t-muted,#8A8A82); margin-top:4px; }
  .nit-prof-idbtns{ display:flex; gap:10px; }
  .nit-prof-cardhd{ padding:18px 22px; border-bottom:1px solid var(--t-line,#F1F1ED); font-size:14px; font-weight:600; }
  .nit-prof-body{ padding:22px; display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  @media (max-width: 560px){ .nit-prof-body{ grid-template-columns:1fr; } }
  .nit-prof-body .full{ grid-column:1 / -1; }
  .nit-prof-lbl{ font-size:12px; color:var(--t-muted,#8A8A82); margin-bottom:8px; }
  .nit-prof-field{ width:100%; height:46px; border:1px solid var(--t-border2,#DCDCD7); border-radius:10px; background:var(--t-field,#FBFBF9); padding:0 14px; font:inherit; font-size:14px; color:var(--t-ink,#16191D); box-sizing:border-box; }
  .nit-prof-field:focus{ outline:none; border-color:var(--t-accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 16%, transparent); }
  .nit-prof-field[readonly]{ color:var(--t-muted,#8A8A82); }
  textarea.nit-prof-field{ height:88px; padding:12px 14px; line-height:1.7; }
  .nit-prof-pref{ padding:22px; display:flex; flex-direction:column; gap:20px; }
  .nit-prof-prow{ display:flex; align-items:center; justify-content:space-between; gap:16px; }
  .nit-prof-prow .txt{ font-size:14px; font-weight:600; }
  .nit-prof-prow .txt small{ display:block; font-weight:400; color:var(--t-muted,#8A8A82); margin-top:2px; }
  .nit-prof-langseg{ display:inline-flex; border:1px solid var(--t-border2,#DCDCD7); border-radius:9px; overflow:hidden; }
  .nit-prof-langseg button{ font:inherit; font-size:13px; font-weight:600; padding:8px 16px; border:0; background:var(--t-bg,#fff); color:var(--t-muted2,#3D4752); cursor:pointer; }
  .nit-prof-langseg button.on{ background:var(--t-ink,#16191D); color:var(--t-bg,#fff); }
  .btn-dark{ background:var(--t-ink,#16191D); color:var(--t-bg,#fff); border:0; padding:11px 18px; border-radius:9px; font-weight:600; font-size:13px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
  .btn-out{ background:var(--t-bg,#fff); color:var(--t-ink,#16191D); border:1px solid var(--t-border2,#DCDCD7); padding:11px 18px; border-radius:9px; font-weight:600; font-size:13px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
  .nit-prof-danger{ padding:18px 22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  .nit-prof-danger .txt{ font-size:14px; font-weight:600; color:#B4402F; }
  .nit-prof-danger .txt small{ display:block; font-weight:400; color:var(--t-muted,#8A8A82); margin-top:2px; }
  .nit-prof-danger a{ color:#B4402F; border-color:color-mix(in srgb,#B4402F 40%, var(--t-border2,#DCDCD7)); }
</style>
<form class="nit-prof" method="post" action="<?php echo $pageurl->out(false); ?>">
  <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
  <h1><?php echo $e($t('Profile & settings', 'الملف والإعدادات')); ?></h1>

  <div class="nit-prof-grid">
    <!-- settings nav -->
    <nav class="nit-prof-nav">
      <a class="on" href="<?php echo $pageurl->out(false); ?>"><span class="ic">👤</span><?php echo $e($t('Profile', 'الملف الشخصي')); ?></a>
      <?php foreach ($nav as $item): ?>
        <a href="<?php echo $e($item['url']); ?>"><span class="ic"><?php echo $item['icon']; ?></span><?php echo $e($item['label']); ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="nit-prof-main">
      <!-- identity -->
      <div class="nit-prof-card nit-prof-id">
        <?php if ($hasrealpic): ?>
          <img class="nit-prof-av" src="<?php echo $e($picurl); ?>" alt="<?php echo $e($fullname); ?>">
        <?php else: ?>
          <span class="nit-prof-av"><?php echo $e($initials); ?></span>
        <?php endif; ?>
        <div class="who">
          <div class="nm"><?php echo $e($fullname); ?></div>
          <div class="sub"><?php echo $e($USER->email); ?><?php echo $joined !== '' ? ' · ' . $e($t('Joined ', 'انضم ') . $joined) : ''; ?></div>
        </div>
        <div class="nit-prof-idbtns">
          <a class="btn-out" href="<?php echo (new moodle_url('/user/editadvanced.php', ['id' => $USER->id]))->out(false); ?>"><?php echo $e($t('Change photo', 'تغيير الصورة')); ?></a>
          <button type="submit" class="btn-dark"><?php echo $e($t('Save changes', 'حفظ التغييرات')); ?></button>
        </div>
      </div>

      <!-- personal details -->
      <div class="nit-prof-card">
        <div class="nit-prof-cardhd"><?php echo $e($t('Personal details', 'البيانات الشخصية')); ?></div>
        <div class="nit-prof-body">
          <div>
            <div class="nit-prof-lbl"><?php echo $e($t('First name', 'الاسم الأول')); ?></div>
            <input class="nit-prof-field" type="text" name="firstname" value="<?php echo $e($USER->firstname); ?>" required>
          </div>
          <div>
            <div class="nit-prof-lbl"><?php echo $e($t('Last name', 'اسم العائلة')); ?></div>
            <input class="nit-prof-field" type="text" name="lastname" value="<?php echo $e($USER->lastname); ?>" required>
          </div>
          <div>
            <div class="nit-prof-lbl"><?php echo $e($t('Email', 'البريد الإلكتروني')); ?></div>
            <input class="nit-prof-field" type="email" value="<?php echo $e($USER->email); ?>" readonly title="<?php echo $e($t('Change your email in account settings', 'غيّر بريدك من إعدادات الحساب')); ?>">
          </div>
          <div>
            <div class="nit-prof-lbl"><?php echo $e($t('Phone', 'الهاتف')); ?></div>
            <input class="nit-prof-field" type="tel" name="phone" dir="ltr" value="<?php echo $e($USER->phone1 ?? ''); ?>">
          </div>
          <div class="full">
            <div class="nit-prof-lbl"><?php echo $e($t('Bio', 'نبذة')); ?></div>
            <textarea class="nit-prof-field" name="bio" maxlength="2000"><?php echo $e(strip_tags($USER->description ?? '')); ?></textarea>
          </div>
        </div>
      </div>

      <!-- preferences (only real, persisted settings) -->
      <div class="nit-prof-card">
        <div class="nit-prof-cardhd"><?php echo $e($t('Preferences', 'التفضيلات')); ?></div>
        <div class="nit-prof-pref">
          <div class="nit-prof-prow">
            <div class="txt"><?php echo $e($t('Interface language', 'لغة الواجهة')); ?>
              <small><?php echo $e($t('Applies across the academy', 'تُطبَّق على كامل المنصة')); ?></small></div>
            <div class="nit-prof-langseg">
              <button type="button" data-lang="en" class="<?php echo $isar ? '' : 'on'; ?>">English</button>
              <button type="button" data-lang="ar" class="<?php echo $isar ? 'on' : ''; ?>">العربية</button>
            </div>
            <input type="hidden" name="lang" id="nit-prof-lang" value="<?php echo $isar ? 'ar' : 'en'; ?>">
          </div>
        </div>
      </div>

      <!-- delete account (links to Moodle's real deletion flow) -->
      <div class="nit-prof-card nit-prof-danger">
        <div class="txt"><?php echo $e($t('Delete account', 'حذف الحساب')); ?>
          <small><?php echo $e($t('Your enrolments and certificates will be removed.', 'ستُحذف تسجيلاتك وشهاداتك.')); ?></small></div>
        <a class="btn-out" href="<?php echo (new moodle_url('/user/edit.php', ['id' => $USER->id]))->out(false); ?>"><?php echo $e($t('Manage', 'إدارة')); ?></a>
      </div>
    </div>
  </div>
</form>
<script>
(function(){
  var seg = document.querySelector('.nit-prof-langseg');
  var input = document.getElementById('nit-prof-lang');
  if(seg && input){
    seg.addEventListener('click', function(ev){
      var b = ev.target.closest('button[data-lang]'); if(!b) return;
      seg.querySelectorAll('button').forEach(function(x){ x.classList.remove('on'); });
      b.classList.add('on'); input.value = b.getAttribute('data-lang');
    });
  }
})();
</script>
<?php
echo $OUTPUT->footer();
