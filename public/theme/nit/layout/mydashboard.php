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
 * NIT learner dashboard layout (/my) — the T1 "Welcome back" dashboard.
 *
 * Renders the user's REAL enrolled courses + completion progress (no dashboard
 * blocks, no design-only placeholders). Same shell as the full-width layout.
 *
 * @package   theme_nit
 * @copyright 2026 NIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};

// ── Real data: the current user's enrolled courses + completion progress ─────
$courses = enrol_get_my_courses('*', 'fullname ASC');
unset($courses[SITEID]);

$cards = [];
$completed = 0;
$inprogress = 0;
foreach ($courses as $course) {
    if (empty($course->visible)) {
        continue;
    }
    $pct = null;
    $ccompletion = new completion_info($course);
    if ($ccompletion->is_enabled()) {
        $raw = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        if ($raw !== null) {
            $pct = (int) round($raw);
        }
    }
    if ($pct === 100) {
        $completed++;
    } else if ($pct !== null && $pct > 0) {
        $inprogress++;
    }
    // Course thumbnail from the overview files.
    $img = '';
    $clist = new core_course_list_element($course);
    foreach ($clist->get_course_overviewfiles() as $f) {
        if ($f->is_valid_image()) {
            $img = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(),
                $f->get_filearea(), $f->get_itemid() ?: null, $f->get_filepath(), $f->get_filename())->out(false);
            break;
        }
    }
    $catname = '';
    try {
        $catname = core_course_category::get($course->category, IGNORE_MISSING)?->get_formatted_name() ?? '';
    } catch (\Throwable $e) {
        $catname = '';
    }
    $cards[] = [
        'name'    => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
        'url'     => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'img'     => $img,
        'cat'     => $catname,
        'teacher' => function_exists('theme_nit_course_teacher') ? theme_nit_course_teacher((int) $course->id) : '',
        'pct'     => $pct,
    ];
}
// In-progress first (highest progress under 100), then not-started, then completed.
usort($cards, static function ($a, $b) {
    $rank = static function ($c) {
        if ($c['pct'] === null) { return 1; }
        if ($c['pct'] >= 100) { return 2; }
        return 0;
    };
    $ra = $rank($a); $rb = $rank($b);
    if ($ra !== $rb) { return $ra <=> $rb; }
    return (int) $b['pct'] <=> (int) $a['pct'];
});
$total = count($cards);
$firstname = $USER->firstname !== '' ? $USER->firstname : $t('there', 'بك');

// ── Build the dashboard HTML ─────────────────────────────────────────────────
$e = fn($s) => s($s);
ob_start();
?>
<style>
  .nit-dash{
    /* Structure tokens (--t-*) come from the ACTIVE TEMPLATE on :root (Phase 2);
       only the accent comes from the academy brand. T1 literals are fallbacks. */
    --t-accent: var(--nit-brand-primary); --t-on: var(--nit-brand-on-primary, #fff);
    --t-accent-soft: color-mix(in srgb, var(--nit-brand-primary) 9%, transparent);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif;
    background:var(--t-bg, #FFFFFF); color:var(--t-ink, #16191D);
    min-height:100vh; padding:clamp(24px,4vw,48px) 20px 60px;
  }
  .nit-dash-wrap{ max-width:1180px; margin:0 auto; }
  .nit-dash-eyebrow{ font-size:12px; letter-spacing:.18em; text-transform:uppercase; color:var(--t-accent); font-weight:700; }
  .nit-dash-h1{ margin:12px 0 0; font-size:clamp(30px,4vw,46px); font-weight:250; letter-spacing:-0.03em; }
  .nit-dash-sub{ margin:8px 0 0; font-size:15px; color:var(--t-muted); }
  .nit-dash-stats{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:18px; margin:32px 0; }
  .nit-dash-stat{ border:1px solid var(--t-border); border-radius:16px; padding:22px; }
  .nit-dash-stat .n{ font-size:34px; font-weight:250; letter-spacing:-0.02em; }
  .nit-dash-stat .l{ font-size:13px; color:var(--t-muted); margin-top:4px; }
  .nit-dash-secttitle{ font-size:clamp(20px,2.4vw,26px); font-weight:250; letter-spacing:-0.02em; margin:8px 0 18px; }
  .nit-dash-list{ display:flex; flex-direction:column; gap:2px; }
  .nit-dash-row{ display:grid; grid-template-columns:132px 1fr auto; gap:20px; align-items:center; padding:18px 0; border-top:1px solid var(--t-border); }
  .nit-dash-row:first-child{ border-top:0; }
  .nit-dash-thumb{ width:132px; height:74px; border-radius:12px; background:repeating-linear-gradient(135deg,#EFEFEC 0 11px,#F7F7F5 11px 22px) center/cover no-repeat; }
  .nit-dash-cat{ font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--t-accent); font-weight:700; }
  .nit-dash-title{ font-size:18px; font-weight:550; letter-spacing:-0.015em; margin:4px 0 0; color:var(--t-ink); }
  .nit-dash-title a{ color:inherit; text-decoration:none; }
  .nit-dash-prog{ display:flex; align-items:center; gap:12px; margin-top:12px; max-width:520px; }
  .nit-dash-bar{ flex:1; height:6px; border-radius:6px; background:var(--t-border); overflow:hidden; }
  .nit-dash-bar > i{ display:block; height:100%; background:var(--t-accent); border-radius:6px; }
  .nit-dash-pct{ font-size:13px; color:var(--t-muted); font-weight:600; min-width:38px; text-align:end; }
  .nit-dash-resume{ background:var(--t-ink); color:var(--t-bg); border:0; padding:12px 26px; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none; white-space:nowrap; }
  .nit-dash-resume:hover{ filter:brightness(1.12); color:var(--t-bg); }
  .nit-dash-empty{ border:1px solid var(--t-border); border-radius:16px; padding:40px; text-align:center; color:var(--t-muted); }
  @media (max-width: 640px){ .nit-dash-row{ grid-template-columns:1fr; } .nit-dash-thumb{ width:100%; height:150px; } }
</style>
<div class="nit-dash">
  <div class="nit-dash-wrap">
    <div class="nit-dash-eyebrow"><?= $t('Dashboard', 'لوحتي') ?></div>
    <h1 class="nit-dash-h1"><?= $t('Welcome back, ', 'مرحبًا مجددًا، ') . $e($firstname) ?></h1>
    <p class="nit-dash-sub">
      <?php if ($inprogress > 0): ?>
        <?= $inprogress . ' ' . $t('course(s) in progress.', 'دورة قيد التقدّم.') ?>
      <?php elseif ($total > 0): ?>
        <?= $t('Keep going — pick up where you left off.', 'واصِل من حيث توقّفت.') ?>
      <?php else: ?>
        <?= $t('Start learning today.', 'ابدأ التعلّم اليوم.') ?>
      <?php endif; ?>
    </p>

    <div class="nit-dash-stats">
      <div class="nit-dash-stat"><div class="n"><?= (int) $inprogress ?></div><div class="l"><?= $t('In progress', 'قيد التقدّم') ?></div></div>
      <div class="nit-dash-stat"><div class="n"><?= (int) $completed ?></div><div class="l"><?= $t('Completed', 'مكتملة') ?></div></div>
      <div class="nit-dash-stat"><div class="n"><?= (int) $total ?></div><div class="l"><?= $t('Enrolled courses', 'دورة مسجّلة') ?></div></div>
    </div>

    <h2 class="nit-dash-secttitle"><?= $t('Continue learning', 'واصِل التعلّم') ?></h2>
    <?php if ($total === 0): ?>
      <div class="nit-dash-empty">
        <?= $t('You are not enrolled in any courses yet.', 'لست مسجّلاً في أي دورات بعد.') ?>
        <a href="<?= (new moodle_url('/course/index.php'))->out(false) ?>" style="color:var(--t-accent);font-weight:600;text-decoration:none;"> <?= $t('Browse courses →', 'تصفّح الدورات ←') ?></a>
      </div>
    <?php else: ?>
      <div class="nit-dash-list">
        <?php foreach ($cards as $c): ?>
          <div class="nit-dash-row">
            <div class="nit-dash-thumb"<?= $c['img'] !== '' ? ' style="background-image:url(\'' . $e($c['img']) . '\');"' : '' ?>></div>
            <div>
              <?php if ($c['cat'] !== ''): ?><div class="nit-dash-cat"><?= $c['cat'] ?></div><?php endif; ?>
              <div class="nit-dash-title"><a href="<?= $e($c['url']) ?>"><?= $c['name'] ?></a></div>
              <?php if ($c['pct'] !== null): ?>
                <div class="nit-dash-prog">
                  <div class="nit-dash-bar"><i style="width:<?= (int) $c['pct'] ?>%;"></i></div>
                  <span class="nit-dash-pct"><?= (int) $c['pct'] ?>%</span>
                </div>
              <?php elseif ($c['teacher'] !== ''): ?>
                <div class="nit-dash-prog" style="color:var(--t-muted);font-size:13px;">👤 <?= $e($c['teacher']) ?></div>
              <?php endif; ?>
            </div>
            <a class="nit-dash-resume" href="<?= $e($c['url']) ?>"><?= ($c['pct'] !== null && $c['pct'] > 0) ? $t('Resume', 'متابعة') : $t('Start', 'ابدأ') ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$dashboardhtml = ob_get_clean();

$bodyattributes = $OUTPUT->body_attributes(['uses-drawers', 'nit-fullwidth-page']);
$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'dashboardhtml' => $dashboardhtml,
];

echo $OUTPUT->render_from_template('theme_nit/mydashboard', $templatecontext);
