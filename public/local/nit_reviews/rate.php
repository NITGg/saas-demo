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
 * Rate a course — a simple server-side form for enrolled learners.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course   = get_course($courseid);

require_login($course);
$context = context_course::instance($courseid);

$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
$PAGE->set_url(new moodle_url('/local/nit_reviews/rate.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('ratecourse', 'local_nit_reviews'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

if (!\local_nit_reviews\api::can_rate($courseid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('mustenrol', 'local_nit_reviews'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->continue_button($courseurl);
    echo $OUTPUT->footer();
    exit;
}

$existing = \local_nit_reviews\api::get_user_review($courseid);

// Handle submit.
if (data_submitted() && confirm_sesskey()) {
    $rating = optional_param('rating', 0, PARAM_INT);
    $review = trim(optional_param('review', '', PARAM_TEXT));
    if ($rating >= 1 && $rating <= 5) {
        \local_nit_reviews\api::save($courseid, $rating, $review);
        redirect($courseurl, get_string('reviewsaved', 'local_nit_reviews'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$curr = $existing ? (int) $existing->rating : 0;
$currtext = $existing ? (string) $existing->review : '';
$e = fn($s) => s($s);

echo $OUTPUT->header();
?>
<style>
  .nit-rate{ max-width:520px; margin:0 auto; font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; }
  .nit-rate h2{ font-weight:250; letter-spacing:-0.02em; font-size:clamp(24px,3vw,32px); color:var(--t-ink,#16191D); }
  .nit-rate .stars{ display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:6px; margin:14px 0 18px; }
  .nit-rate .stars input{ display:none; }
  .nit-rate .stars label{ font-size:34px; line-height:1; color:var(--t-border,#DCDCD7); cursor:pointer; transition:color .1s; }
  .nit-rate .stars label:hover, .nit-rate .stars label:hover ~ label,
  .nit-rate .stars input:checked ~ label{ color:var(--nit-brand-primary,#0E7C66); }
  .nit-rate textarea{ width:100%; min-height:110px; border:1px solid var(--t-border2,#DCDCD7); border-radius:12px; padding:12px 14px; font:inherit; background:var(--t-bg,#fff); color:var(--t-ink,#16191D); }
  .nit-rate .lbl{ font-size:13px; color:var(--t-muted,#6E7781); font-weight:600; margin-bottom:6px; }
  .nit-rate .btn-solid{ margin-top:16px; background:var(--nit-brand-primary,#0E7C66); color:var(--nit-brand-on-primary,#fff); border:0; padding:13px 22px; border-radius:10px; font-weight:600; cursor:pointer; font-size:15px; }
</style>
<div class="nit-rate">
  <h2><?php echo $e(get_string('ratecourse', 'local_nit_reviews')); ?></h2>
  <form method="post" action="<?php echo $PAGE->url->out(false); ?>">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <div class="lbl"><?php echo $e(get_string('yourrating', 'local_nit_reviews')); ?></div>
    <div class="stars">
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" id="nit-star-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo $curr === $i ? 'checked' : ''; ?>>
        <label for="nit-star-<?php echo $i; ?>" aria-label="<?php echo $i; ?>">&#9733;</label>
      <?php endfor; ?>
    </div>
    <div class="lbl"><?php echo $e(get_string('yourreview', 'local_nit_reviews')); ?></div>
    <textarea name="review" maxlength="2000"><?php echo $e($currtext); ?></textarea>
    <div>
      <button type="submit" class="btn-solid">
        <?php echo $e($existing ? get_string('updatereview', 'local_nit_reviews') : get_string('submitreview', 'local_nit_reviews')); ?>
      </button>
    </div>
  </form>
</div>
<?php
echo $OUTPUT->footer();
