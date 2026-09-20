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

// ── Try to render the full T1 player; fall back to a bare embed on any error ──
$rendered = false;
try {
    $isar = (strpos(current_language(), 'ar') === 0);
    $t = function (string $en, string $ar) use ($isar) { return $isar ? $ar : $en; };
    $e = fn($s) => s($s);

    $modinfo = get_fast_modinfo($course);

    // Flat, ordered list of navigable lessons across all sections (real modinfo order).
    $lessons = [];         // sequential: [ ['cmid','name','url','section','done','current'] ]
    $sections = [];        // grouped for the sidebar: [ ['name','num','items'=>[...]] ]
    $curindex = -1;

    foreach ($modinfo->get_section_info_all() as $secinfo) {
        if (!$secinfo->uservisible) {
            continue;
        }
        $secitems = [];
        $cmids = $modinfo->sections[$secinfo->section] ?? [];
        foreach ($cmids as $cmid) {
            $mod = $modinfo->cms[$cmid] ?? null;
            if (!$mod || !$mod->uservisible || $mod->modname === 'label') {
                continue;
            }
            $url = $mod->url ? $mod->url->out(false) : '';
            if ($url === '') {
                continue; // Non-viewable resource (nothing to navigate to).
            }
            $done = false;
            if ($completion->is_enabled($mod)) {
                $cdata = $completion->get_data($mod, false, $USER->id);
                $done = in_array((int) $cdata->completionstate,
                    [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
            }
            $iscurrent = ((int) $mod->id === (int) $cm->id);
            $entry = [
                'cmid'    => (int) $mod->id,
                'name'    => format_string($mod->name),
                'url'     => $url,
                'done'    => $done,
                'current' => $iscurrent,
                'video'   => ($mod->modname === 'vimeo' || $mod->modname === 'vdocipher'),
            ];
            $lessons[] = $entry;
            if ($iscurrent) {
                $curindex = count($lessons) - 1;
            }
            $secitems[] = $entry;
        }
        if ($secitems) {
            $sections[] = [
                'name' => $secinfo->name !== null && $secinfo->name !== ''
                    ? format_string($secinfo->name)
                    : get_section_name($course, $secinfo),
                'num'   => (int) $secinfo->section,
                'items' => $secitems,
            ];
        }
    }

    $total = count($lessons);
    $prev  = ($curindex > 0) ? $lessons[$curindex - 1] : null;
    $next  = ($curindex >= 0 && $curindex < $total - 1) ? $lessons[$curindex + 1] : null;
    $position = ($curindex >= 0) ? ($curindex + 1) : 1;

    // Course-level completion progress (real).
    $pct = null;
    $donecount = 0;
    foreach ($lessons as $l) { if ($l['done']) { $donecount++; } }
    try {
        $raw = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        if ($raw !== null) { $pct = (int) round($raw); }
    } catch (\Throwable $ig) { $pct = null; }
    if ($pct === null && $total > 0) {
        $pct = (int) round($donecount / $total * 100);
    }

    $courseurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
    $overview = !empty($moduleinstance->intro)
        ? format_module_intro('vimeo', $moduleinstance, $cm->id) : '';

    ob_start();
    ?>
<style>
  /* Structure (--t-*) inherited from the active template on :root (Phase 2), T1
     fallbacks; only the accent is brand. The video frame stays dark regardless. */
  .nit-player{ --t-accent:var(--nit-brand-primary,#0E7C66);
    --t-accent-soft:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 8%, var(--t-bg,#FFFFFF));
    --t-accent-ring:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 30%, var(--t-border,#DCE9E5));
    --t-on:var(--nit-brand-on-primary,#fff);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; color:var(--t-ink,#16191D);
    display:grid; grid-template-columns:1fr 372px; align-items:stretch; min-height:70vh; }
  .nit-player__main{ background:#0B0D11; display:flex; flex-direction:column; }
  .nit-player__video{ position:relative; aspect-ratio:16/9; background:#000; }
  .nit-player__below{ background:var(--t-bg,#FFFFFF); flex:1; padding:28px clamp(20px,3vw,34px) 44px; }
  .nit-player__head{ display:flex; align-items:flex-start; justify-content:space-between; gap:24px; flex-wrap:wrap; }
  .nit-player__eyebrow{ font-size:12px; color:var(--t-muted); }
  .nit-player__title{ margin:8px 0 0; font-size:clamp(22px,3vw,28px); font-weight:400; letter-spacing:-0.025em; color:var(--t-ink); }
  .nit-player__nav{ display:flex; gap:10px; flex:none; }
  .nit-btn{ cursor:pointer; font-family:inherit; font-size:13px; font-weight:600; padding:11px 18px; border-radius:9px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; border:1px solid var(--t-border2); background:var(--t-bg); color:var(--t-ink); }
  .nit-btn:hover{ background:var(--t-surface); color:var(--t-ink); }
  .nit-btn--primary{ background:var(--t-accent); border:0; color:var(--t-on); }
  .nit-btn--primary:hover{ filter:brightness(1.08); color:var(--t-on); }
  .nit-player__ov{ margin-top:24px; padding-top:22px; border-top:1px solid var(--t-border); font-size:15px; line-height:1.85; color:var(--t-muted,#3D4752); max-width:720px; }
  .nit-player__ov :is(p,ul,ol){ margin:0 0 12px; }
  /* sidebar */
  .nit-player__aside{ border-inline-start:1px solid var(--t-border); background:var(--t-surface); display:flex; flex-direction:column; }
  .nit-player__asidehd{ padding:22px 24px; border-bottom:1px solid var(--t-border); background:var(--t-bg); }
  .nit-player__coursename{ font-size:15px; font-weight:600; line-height:1.4; color:var(--t-ink); text-decoration:none; }
  .nit-player__progress{ display:flex; align-items:center; gap:12px; margin-top:14px; }
  .nit-player__bar{ flex:1; height:6px; border-radius:3px; background:var(--t-border); overflow:hidden; }
  .nit-player__bar > i{ display:block; height:100%; background:var(--t-accent); border-radius:3px; }
  .nit-player__pct{ font-size:12px; font-weight:600; color:var(--t-accent); min-width:34px; text-align:end; }
  .nit-player__count{ font-size:12px; color:var(--t-muted); margin-top:8px; }
  .nit-player__list{ flex:1; overflow:auto; padding-bottom:20px; }
  .nit-player__seclabel{ padding:16px 24px 8px; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--t-muted); font-weight:700; }
  .nit-player__items{ padding:0 12px; display:flex; flex-direction:column; gap:2px; }
  .nit-lesson{ display:flex; align-items:center; gap:12px; padding:12px; border-radius:10px; text-decoration:none; color:var(--t-muted,#55606B); border:1px solid transparent; }
  .nit-lesson:hover{ background:var(--t-bg); color:var(--t-ink); }
  .nit-lesson--current{ background:var(--t-bg); border-color:var(--t-accent-ring); }
  .nit-lesson__ic{ flex:none; width:22px; height:22px; border-radius:50%; display:grid; place-items:center; font-size:10px; background:var(--t-accent-soft); color:var(--t-accent); }
  .nit-lesson--current .nit-lesson__ic{ background:var(--t-accent); color:var(--t-on); }
  .nit-lesson--done .nit-lesson__ic{ background:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 14%, #fff); color:var(--t-accent); }
  .nit-lesson__name{ flex:1; font-size:13px; }
  .nit-lesson--current .nit-lesson__name{ font-weight:600; color:var(--t-ink); }
  @media (max-width: 900px){ .nit-player{ grid-template-columns:1fr; } .nit-player__aside{ border-inline-start:0; border-top:1px solid var(--t-border); } }
</style>
<div class="nit-player">
  <div class="nit-player__main">
    <div class="nit-player__video"><?php echo $iframe; ?></div>
    <div class="nit-player__below">
      <div class="nit-player__head">
        <div>
          <div class="nit-player__eyebrow"><?php echo $e($t('Lesson', 'الدرس') . ' ' . $position . ' ' . $t('of', 'من') . ' ' . max($total, 1)); ?></div>
          <h1 class="nit-player__title"><?php echo $e(format_string($moduleinstance->name)); ?></h1>
        </div>
        <div class="nit-player__nav">
          <?php if ($prev): ?>
            <a class="nit-btn" href="<?php echo $e($prev['url']); ?>">&larr; <?php echo $e($t('Previous', 'السابق')); ?></a>
          <?php endif; ?>
          <?php if ($next): ?>
            <a class="nit-btn nit-btn--primary" href="<?php echo $e($next['url']); ?>"><?php echo $e($t('Next lesson', 'الدرس التالي')); ?> &rarr;</a>
          <?php else: ?>
            <a class="nit-btn nit-btn--primary" href="<?php echo $e($courseurl); ?>"><?php echo $e($t('Back to course', 'العودة للدورة')); ?></a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($overview !== ''): ?>
        <div class="nit-player__ov"><?php echo $overview; ?></div>
      <?php endif; ?>
    </div>
  </div>

  <aside class="nit-player__aside">
    <div class="nit-player__asidehd">
      <a class="nit-player__coursename" href="<?php echo $e($courseurl); ?>"><?php echo $e(format_string($course->fullname)); ?></a>
      <?php if ($pct !== null): ?>
        <div class="nit-player__progress">
          <div class="nit-player__bar"><i style="width:<?php echo (int) $pct; ?>%;"></i></div>
          <span class="nit-player__pct"><?php echo (int) $pct; ?>%</span>
        </div>
        <div class="nit-player__count"><?php echo $e($donecount . ' ' . $t('of', 'من') . ' ' . max($total, 1) . ' ' . $t('lessons complete', 'دروس مكتملة')); ?></div>
      <?php endif; ?>
    </div>
    <div class="nit-player__list">
      <?php foreach ($sections as $si => $sec): ?>
        <div class="nit-player__seclabel"><?php echo $e(str_pad((string) ($si + 1), 2, '0', STR_PAD_LEFT) . ' · ' . $sec['name']); ?></div>
        <div class="nit-player__items">
          <?php foreach ($sec['items'] as $it): ?>
            <a class="nit-lesson <?php echo $it['current'] ? 'nit-lesson--current' : ($it['done'] ? 'nit-lesson--done' : ''); ?>" href="<?php echo $e($it['url']); ?>">
              <span class="nit-lesson__ic"><?php echo $it['current'] ? '&#9654;' : ($it['done'] ? '&#10003;' : ($it['video'] ? '&#9654;' : '&bull;')); ?></span>
              <span class="nit-lesson__name"><?php echo $e($it['name']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
    <?php
    echo ob_get_clean();
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
