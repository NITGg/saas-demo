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

// ── Change photo (its own small form, posted as soon as a picture is chosen) ──
if (optional_param('nitphoto', 0, PARAM_BOOL) && confirm_sesskey()) {
    $photoerror = \local_academy\local\account_photo::save_upload((int) $USER->id, $_FILES['photo'] ?? []);
    redirect($pageurl, $photoerror ?? $t('Photo updated.', 'تم تحديث الصورة.'), null,
        $photoerror ? \core\output\notification::NOTIFY_ERROR : \core\output\notification::NOTIFY_SUCCESS);
}

// ── Save (personal details + language preference) ────────────────────────────
if (data_submitted() && confirm_sesskey()) {
    // Teachers: the live-lesson card (bookings, subjects, weekly hours) posts with this form.
    $lessonerror = class_exists('\local_nit_lessons\local\profile_section')
        ? \local_nit_lessons\local\profile_section::save((int) $USER->id) : null;

    $upd = new stdClass();
    $upd->id = (int) $USER->id;
    $upd->firstname = trim(required_param('firstname', PARAM_TEXT));
    $upd->lastname  = trim(required_param('lastname', PARAM_TEXT));
    $upd->phone1    = trim(optional_param('phone', '', PARAM_TEXT));
    $parentphone    = trim(optional_param('parentphone', '', PARAM_TEXT));
    $upd->description = optional_param('bio', '', PARAM_TEXT);
    $upd->descriptionformat = FORMAT_HTML;
    $lang = optional_param('lang', '', PARAM_LANG);
    if ($lang !== '') {
        $upd->lang = $lang;
    }

    // Registration data (user profile fields, see \local_academy\local\user_fields).
    // A dropdown only accepts one of its own options; a division must belong to
    // the chosen study system.
    $custom = [];
    foreach (\local_academy\local\user_fields::definitions() as $shortname => $def) {
        if ($shortname === 'parentphone') {
            continue; // Saved below from its own input (its shortname is a local_parent setting).
        }
        $value = optional_param('pf_' . $shortname, null, PARAM_TEXT);
        if ($value === null) {
            continue; // Not on this form (e.g. the teacher title) — leave it as it is.
        }
        $value = trim($value);
        if ($def['type'] === 'menu' && $value !== ''
                && !in_array($value, \local_academy\local\user_fields::menu_options($shortname), true)) {
            $value = '';
        }
        $custom[$shortname] = $value;
    }
    $sysmap = \local_academy\local\academic_structure::map();
    $sys = $custom[\local_academy\local\academic_structure::USER_SYSTEM] ?? '';
    $div = $custom[\local_academy\local\academic_structure::USER_DIVISION] ?? '';
    if ($div !== '' && ($sys === '' || !in_array($div, $sysmap[$sys] ?? [], true))) {
        $custom[\local_academy\local\academic_structure::USER_DIVISION] = '';
    }
    // Names, phones and the national ID follow the registration rules, and the student's own
    // phone and national ID may not be another account's (\local_academy\local\student_rules).
    // Only changed values are checked, so older saved data does not block other changes.
    $rules = \local_academy\local\student_rules::class;
    $upd->phone1 = $rules::ascii_digits($upd->phone1);
    $parentphone = $rules::ascii_digits($parentphone);
    foreach (['nationalid', 'fatherphone', 'motherphone'] as $key) {
        if (isset($custom[$key])) {
            $custom[$key] = $rules::ascii_digits($custom[$key]);
        }
    }
    $saved = $DB->get_record('user', ['id' => $USER->id], 'firstname, lastname, phone1', MUST_EXIST);
    $profileerrors = $rules::profile_errors((int) $USER->id,
        $custom + ['firstname' => $upd->firstname, 'lastname' => $upd->lastname, 'phone' => $upd->phone1,
            'parentphone' => $parentphone],
        \local_academy\local\user_fields::values((int) $USER->id) + ['firstname' => (string) $saved->firstname,
            'lastname' => (string) $saved->lastname, 'phone' => (string) $saved->phone1]);
    if ($profileerrors) {
        // "Field: problem", so the student sees which of the fields to fix.
        $key = key($profileerrors);
        $labels = ['firstname' => get_string('firstname'), 'lastname' => get_string('lastname'), 'phone' => get_string('phone'),
            'parentphone' => $t("Guardian's phone", 'رقم هاتف ولي الأمر')];
        $fieldname = $DB->get_field('user_info_field', 'name', ['shortname' => $key]);
        $label = $labels[$key] ?? ($fieldname ? format_string($fieldname) : $key);
        redirect($pageurl, $label . ': ' . get_string(reset($profileerrors), 'local_academy'),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($upd->firstname !== '' && $upd->lastname !== '') {
        try {
            user_update_user($upd, false, true);
            // Reflect immediately in this session.
            $USER->firstname = $upd->firstname;
            $USER->lastname  = $upd->lastname;
            $USER->phone1    = $upd->phone1;
            if ($lang !== '') {
                $USER->lang = $lang;
            }

            require_once($CFG->dirroot . '/user/profile/lib.php');
            profile_save_custom_fields($USER->id, $custom);

            // Save parent phone to custom profile field.
            $parentfield = (string) (get_config('local_parent', 'parentphonefield') ?: 'parentphone');
            $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $parentfield]);
            if ($fieldid) {
                $existing = $DB->get_record('user_info_data', ['userid' => $USER->id, 'fieldid' => $fieldid]);
                if ($existing) {
                    $existing->data = $parentphone;
                    $DB->update_record('user_info_data', $existing);
                } else if ($parentphone !== '') {
                    $DB->insert_record('user_info_data', (object) [
                        'userid' => $USER->id,
                        'fieldid' => $fieldid,
                        'data' => $parentphone,
                        'dataformat' => 0,
                    ]);
                }
            }

            if ($lessonerror !== null) {
                redirect(new moodle_url($pageurl, [], 'lessons'), $lessonerror, null, \core\output\notification::NOTIFY_ERROR);
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

// Current parent phone.
$parentfield = (string) (get_config('local_parent', 'parentphonefield') ?: 'parentphone');
$parentfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $parentfield]);
$currentparentphone = '';
if ($parentfieldid) {
    $currentparentphone = (string) $DB->get_field('user_info_data', 'data', ['userid' => $USER->id, 'fieldid' => $parentfieldid]);
}

// Registration data (user profile fields) and the field builders for the cards.
$pf = \local_academy\local\user_fields::values((int) $USER->id);
$pfdefs = \local_academy\local\user_fields::definitions();
$pfinput = function (string $key, string $type = 'text', array $attrs = []) use ($pf, $pfdefs, $e, $t): string {
    $extra = '';
    foreach ($attrs as $name => $value) {
        $extra .= ' ' . $name . '="' . $e($value) . '"';
    }
    return '<div><div class="nit-prof-lbl">' . $e($t($pfdefs[$key]['name'][1], $pfdefs[$key]['name'][0])) . '</div>'
        . '<input class="nit-prof-field" type="' . $type . '" name="pf_' . $key . '" value="' . $e($pf[$key]) . '"' . $extra . '></div>';
};
$pfselect = function (string $key) use ($pf, $pfdefs, $e, $t): string {
    $opts = '<option value="">' . $e($t('Choose…', 'اختر…')) . '</option>';
    foreach (\local_academy\local\user_fields::menu_options($key) as $option) {
        $opts .= '<option value="' . $e($option) . '"' . ($option === $pf[$key] ? ' selected' : '') . '>' . format_string($option) . '</option>'; // Category names carry {mlang}.
    }
    return '<div><div class="nit-prof-lbl">' . $e($t($pfdefs[$key]['name'][1], $pfdefs[$key]['name'][0])) . '</div>'
        . '<select class="nit-prof-field" id="nit-pf-' . $key . '" name="pf_' . $key . '">' . $opts . '</select></div>';
};
$phoneattrs = ['dir' => 'ltr', 'pattern' => '^[+]?[0-9\s\-()]{7,25}$', 'placeholder' => $t('e.g. 01012345678', 'مثال: 01012345678'),
    'oninput' => "this.value = this.value.replace(/[^0-9+\\s\\-()]/g, '');"];

echo $OUTPUT->header();
?>
<style>
<?php // The nav, cards and fields are shared with the other account pages (account_shell). ?>
<?php echo \local_academy\local\account_shell::css(); ?>
</style>
<form class="nit-prof" method="post" action="<?php echo $pageurl->out(false); ?>">
  <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
  <h1><?php echo $e($t('Profile & settings', 'الملف والإعدادات')); ?></h1>

  <div class="nit-prof-grid">
    <!-- settings nav -->
    <?php echo \local_academy\local\account_shell::nav_html('profile'); ?>

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
          <?php if (\local_academy\local\account_photo::can_change((int) $USER->id)): // Uploads here; the input belongs to #nit-photo-form below. ?>
          <label class="btn-out" for="nit-photo-input" style="margin:0;"><?php echo $e($t('Change photo', 'تغيير الصورة')); ?></label>
          <input type="file" id="nit-photo-input" name="photo" form="nit-photo-form" accept="image/jpeg,image/png,image/gif,image/webp" hidden
            onchange="if (this.files.length) { this.form.submit(); }">
          <?php endif; ?>
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
          <?php echo $pfinput('secondname'); ?>
          <?php echo $pfinput('thirdname'); ?>
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
            <input class="nit-prof-field" type="tel" name="phone" dir="ltr"
                   pattern="^[+]?[0-9\s\-()]{7,25}$"
                   oninput="this.value = this.value.replace(/[^0-9+\s\-()]/g, '');"
                   placeholder="<?php echo $e($t('e.g. 01012345678', 'مثال: 01012345678')); ?>"
                   value="<?php echo $e($USER->phone1 ?? ''); ?>">
          </div>
          <?php echo $pfinput('nationalid', 'text', ['dir' => 'ltr', 'inputmode' => 'numeric', 'maxlength' => '14', 'pattern' => '[0-9]{14}',
              'oninput' => "this.value = this.value.replace(/\\D/g, '');"]); ?>
          <?php echo $pfselect('gender'); ?>
          <?php echo $pfselect('religion'); ?>
          <?php echo $pfselect('governorate'); ?>
          <?php echo $pfinput('school'); ?>
          <div class="full">
            <div class="nit-prof-lbl"><?php echo $e($t('Bio', 'نبذة')); ?></div>
            <textarea class="nit-prof-field" name="bio" maxlength="2000"><?php echo $e(strip_tags($USER->description ?? '')); ?></textarea>
          </div>
        </div>
      </div>

      <!-- study details -->
      <div class="nit-prof-card">
        <div class="nit-prof-cardhd"><?php echo $e($t('Study details', 'البيانات الدراسية')); ?></div>
        <div class="nit-prof-body">
          <?php echo $pfselect(\local_academy\local\academic_structure::USER_YEAR); ?>
          <?php echo $pfselect(\local_academy\local\academic_structure::USER_SYSTEM); ?>
          <?php echo $pfselect(\local_academy\local\academic_structure::USER_DIVISION); ?>
        </div>
      </div>

      <!-- guardian details -->
      <div class="nit-prof-card">
        <div class="nit-prof-cardhd"><?php echo $e($t('Guardian details', 'بيانات ولي الأمر')); ?></div>
        <div class="nit-prof-body">
          <?php echo $pfinput('fatherphone', 'tel', $phoneattrs); ?>
          <?php echo $pfinput('motherphone', 'tel', $phoneattrs); ?>
          <div>
            <div class="nit-prof-lbl"><?php echo $e($t('Parent phone', 'هاتف ولي الأمر')); ?></div>
            <input class="nit-prof-field" type="tel" name="parentphone" dir="ltr"
                   pattern="^[+]?[0-9\s\-()]{7,25}$"
                   oninput="this.value = this.value.replace(/[^0-9+\s\-()]/g, '');"
                   placeholder="<?php echo $e($t('Parent mobile number', 'رقم هاتف ولي الأمر')); ?>"
                   value="<?php echo $e($currentparentphone); ?>">
          </div>
          <?php echo $pfinput('guardianjob'); ?>
        </div>
      </div>

      <?php // Teachers: live lessons (from local_nit_lessons; '' for everyone else). ?>
      <?php echo class_exists('\local_nit_lessons\local\profile_section')
          ? \local_nit_lessons\local\profile_section::render((int) $USER->id) : ''; ?>

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
<form id="nit-photo-form" method="post" enctype="multipart/form-data" action="<?php echo $pageurl->out(false); ?>" hidden>
  <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>"><input type="hidden" name="nitphoto" value="1">
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
echo \local_academy\local\linked_selects::script('nit-pf-' . \local_academy\local\academic_structure::USER_SYSTEM,
    'nit-pf-' . \local_academy\local\academic_structure::USER_DIVISION);
echo $OUTPUT->footer();
