<?php
/**
 * Display a Vimeo Video activity: the embedded player for the logged-in user.
 *
 * Access is enforced by Moodle (require_login + capability) and, on Vimeo's side,
 * by embed-whitelist privacy on the academy domain. There is no OTP/watermark.
 *
 * Presentation is the T1 "Player" screen from the academy app designs: a dark
 * frame around the REAL Vimeo embed (Vimeo owns the in-player controls), a lesson
 * header with real Previous / Next navigation, and a curriculum sidebar built from
 * the course's real modinfo + completion state. Durations are not shown because
 * Vimeo lengths are not stored locally (real data only). The T1 rule holds: the
 * structure is hardcoded light, only the accent tracks --nit-brand-primary.
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // course module id

$cm      = get_coursemodule_from_id('vimeo', $id, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$moduleinstance = $DB->get_record('vimeo', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
if (class_exists('\local_academy\player')) {
    \local_academy\player::require_unlocked($course, (int) $cm->id);
}

$context = context_module::instance($cm->id);
require_capability('mod/vimeo:view', $context);

// Log the view / completion.
$event = \mod_vimeo\event\course_module_viewed::create([
    'objectid' => $moduleinstance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('vimeo', $moduleinstance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url(new moodle_url('/mod/vimeo/view.php', ['id' => $cm->id]));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth'); // Immersive edge-to-edge player (keeps navbar + footer).

echo $OUTPUT->header();

// No video configured yet — keep the original clear warning.
if (empty($moduleinstance->videoid)) {
    echo $OUTPUT->notification(get_string('err_novideo', 'local_vimeo'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

// The real Vimeo embed src.
$src = 'https://player.vimeo.com/video/' . rawurlencode($moduleinstance->videoid);
if (!empty($moduleinstance->videohash)) {
    $src .= '?h=' . rawurlencode($moduleinstance->videohash);
}
$iframe = '<iframe src="' . s($src) . '" title="' . s(format_string($moduleinstance->name)) . '"'
    . ' style="position:absolute;inset:0;width:100%;height:100%;border:0;"'
    . ' allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';

// ── The T1 player frame (shared with every other lesson type through
// local_academy\player); falls back to a bare embed on any error. ──
$rendered = false;
try {
    $modinfo = get_fast_modinfo($course);
    $cminfo = $modinfo->get_cm($cm->id);
    $overview = !empty($moduleinstance->intro)
        ? format_module_intro('vimeo', $moduleinstance, $cm->id) : '';
    echo \local_academy\player::render($cminfo, $course,
        '<div class="nit-player__video">' . $iframe . '</div>', $overview, true);
    $rendered = true;
} catch (\Throwable $ex) {
    debugging('mod_vimeo T1 player failed, using bare embed: ' . $ex->getMessage(), DEBUG_DEVELOPER);
}

// Fallback: the original simple, centred embed (keeps the page working).
if (!$rendered) {
    if (!empty($moduleinstance->intro)) {
        echo $OUTPUT->box(format_module_intro('vimeo', $moduleinstance, $cm->id), 'generalbox', 'intro');
    }
    echo '<div style="position:relative;width:100%;max-width:960px;margin:1rem auto;aspect-ratio:16/9;background:#000;">'
        . $iframe . '</div>';
}

echo $OUTPUT->footer();
