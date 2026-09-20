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

namespace local_academy;

use cm_info;
use moodle_url;

/**
 * The T1 "course player" frame — ONE layout for every lesson type.
 *
 * Video lessons (mod_vimeo / mod_vdocipher) render their embed in the stage;
 * every other activity (page, resource, quiz, forum, …) renders inside the same
 * frame through /local/academy/player.php, which embeds the real activity view
 * chrome-free. The sidebar is the live curriculum (real modinfo order,
 * visibility and completion), so a learner moves through the course without
 * ever leaving the player.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player {
    /** Module types whose own view page IS the player (they render the stage). */
    const VIDEO_MODS = ['vimeo', 'vdocipher'];

    /**
     * The player URL for a course module: video lessons open their module page,
     * everything else opens inside the generic player frame.
     *
     * @param cm_info $cm
     * @return string
     */
    public static function url_for(cm_info $cm): string {
        if (in_array($cm->modname, self::VIDEO_MODS, true) && $cm->url) {
            return $cm->url->out(false);
        }
        return (new moodle_url('/local/academy/player.php', ['cmid' => $cm->id]))->out(false);
    }

    /**
     * Whether a module is a navigable lesson (something the player can show).
     *
     * @param cm_info $cm
     * @return bool
     */
    public static function is_lesson(cm_info $cm): bool {
        return $cm->uservisible && $cm->modname !== 'label' && $cm->url !== null && !$cm->deletioninprogress;
    }

    /**
     * The lesson a learner should resume at: the first not-yet-completed lesson,
     * else the first lesson. Null when the course has no lesson at all.
     *
     * @param \stdClass $course
     * @return cm_info|null
     */
    public static function resume_cm(\stdClass $course): ?cm_info {
        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);
        $first = null;
        foreach ($modinfo->get_section_info_all() as $secinfo) {
            if (!$secinfo->uservisible) {
                continue;
            }
            foreach (($modinfo->sections[$secinfo->section] ?? []) as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if (!$cm || !self::is_lesson($cm)) {
                    continue;
                }
                if ($first === null) {
                    $first = $cm;
                }
                if ($completion->is_enabled($cm)
                        && (int) $completion->get_data($cm, false)->completionstate === COMPLETION_INCOMPLETE) {
                    return $cm;
                }
            }
        }
        return $first;
    }

    /**
     * Render the player frame around a stage.
     *
     * @param cm_info $cm        the current lesson
     * @param \stdClass $course
     * @param string $stagehtml  the stage content (an embed iframe / the activity frame)
     * @param string $overview   optional formatted intro shown below the title
     * @param bool $darkstage    dark letterbox behind the stage (video) or the page surface
     * @return string HTML
     */
    public static function render(cm_info $cm, \stdClass $course, string $stagehtml, string $overview = '',
            bool $darkstage = true): string {
        global $USER;
        $isar = (strpos(current_language(), 'ar') === 0);
        $t = function (string $en, string $ar) use ($isar) {
            return $isar ? $ar : $en;
        };
        $e = fn($s) => s($s);

        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);

        $lessons = [];
        $sections = [];
        $curindex = -1;
        foreach ($modinfo->get_section_info_all() as $secinfo) {
            if (!$secinfo->uservisible) {
                continue;
            }
            $secitems = [];
            foreach (($modinfo->sections[$secinfo->section] ?? []) as $cmid) {
                $mod = $modinfo->cms[$cmid] ?? null;
                if (!$mod || !self::is_lesson($mod)) {
                    continue;
                }
                $done = false;
                if ($completion->is_enabled($mod)) {
                    $cdata = $completion->get_data($mod, false, $USER->id);
                    $done = in_array((int) $cdata->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                }
                $iscurrent = ((int) $mod->id === (int) $cm->id);
                $entry = [
                    'cmid'    => (int) $mod->id,
                    'name'    => format_string($mod->name),
                    'url'     => self::url_for($mod),
                    'done'    => $done,
                    'current' => $iscurrent,
                    'video'   => in_array($mod->modname, self::VIDEO_MODS, true),
                    'type'    => $mod->modname,
                ];
                $lessons[] = $entry;
                if ($iscurrent) {
                    $curindex = count($lessons) - 1;
                }
                $secitems[] = $entry;
            }
            if ($secitems) {
                $sections[] = [
                    'name'  => ($secinfo->name !== null && $secinfo->name !== '')
                        ? format_string($secinfo->name) : get_section_name($course, $secinfo),
                    'items' => $secitems,
                ];
            }
        }

        $total = count($lessons);
        $prev = ($curindex > 0) ? $lessons[$curindex - 1] : null;
        $next = ($curindex >= 0 && $curindex < $total - 1) ? $lessons[$curindex + 1] : null;
        $position = ($curindex >= 0) ? ($curindex + 1) : 1;

        $pct = null;
        $donecount = 0;
        foreach ($lessons as $l) {
            if ($l['done']) {
                $donecount++;
            }
        }
        try {
            $raw = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            if ($raw !== null) {
                $pct = (int) round($raw);
            }
        } catch (\Throwable $ig) {
            $pct = null;
        }
        if ($pct === null && $total > 0) {
            $pct = (int) round($donecount / $total * 100);
        }

        $courseurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $stagebg = $darkstage ? '#0B0D11' : 'var(--t-bg,#FFFFFF)';

        ob_start();
        ?>
<style>
  .nit-player{ --t-accent:var(--nit-brand-primary,#0E7C66);
    --t-accent-soft:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 8%, var(--t-bg,#FFFFFF));
    --t-accent-ring:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 30%, var(--t-border,#DCE9E5));
    --t-on:var(--nit-brand-on-primary,#fff);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; color:var(--t-ink,#16191D);
    display:grid; grid-template-columns:1fr 372px; align-items:stretch; min-height:70vh; }
  .nit-player__main{ background:<?php echo $stagebg; ?>; display:flex; flex-direction:column; min-width:0; }
  .nit-player__video{ position:relative; aspect-ratio:16/9; background:#000; }
  .nit-player__video iframe{ position:absolute; inset:0; width:100%; height:100%; border:0; }
  .nit-player__frame{ position:relative; background:var(--t-bg,#FFFFFF); }
  .nit-player__frame iframe{ display:block; width:100%; min-height:70vh; border:0; }
  .nit-player__below{ background:var(--t-bg,#FFFFFF); flex:1; padding:28px clamp(20px,3vw,34px) 44px; }
  .nit-player__head{ display:flex; align-items:flex-start; justify-content:space-between; gap:24px; flex-wrap:wrap; }
  .nit-player__eyebrow{ font-size:12px; color:var(--t-muted); }
  .nit-player__title{ margin:8px 0 0; font-size:clamp(22px,3vw,28px); font-weight:400; letter-spacing:-0.025em; color:var(--t-ink); }
  .nit-player__nav{ display:flex; gap:10px; flex:none; }
  .nit-btn{ cursor:pointer; font-family:inherit; font-size:13px; font-weight:600; padding:11px 18px; border-radius:9px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; border:1px solid var(--t-border2); background:var(--t-bg); color:var(--t-ink); }
  .nit-btn:hover{ background:var(--t-surface); color:var(--t-ink); }
  .nit-btn--primary{ background:var(--t-accent); border:0; color:var(--t-on); }
  .nit-btn--primary:hover{ background:color-mix(in srgb, var(--t-accent) 85%, #000); color:var(--t-on); }
  .nit-player__ov{ margin-top:24px; padding-top:22px; border-top:1px solid var(--t-border); font-size:15px; line-height:1.85; color:var(--t-muted,#3D4752); max-width:720px; }
  .nit-player__ov :is(p,ul,ol){ margin:0 0 12px; }
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
  .nit-lesson__type{ font-size:10px; color:var(--t-muted2,#8A8A82); text-transform:uppercase; letter-spacing:.06em; }
  @media (max-width: 900px){ .nit-player{ grid-template-columns:1fr; } .nit-player__aside{ border-inline-start:0; border-top:1px solid var(--t-border); } }
</style>
<div class="nit-player">
  <div class="nit-player__main">
    <?php echo $stagehtml; ?>
    <div class="nit-player__below">
      <div class="nit-player__head">
        <div>
          <div class="nit-player__eyebrow"><?php echo $e($t('Lesson', 'الدرس') . ' ' . $position . ' ' . $t('of', 'من') . ' ' . max($total, 1)); ?></div>
          <h1 class="nit-player__title"><?php echo $e(format_string($cm->name)); ?></h1>
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
              <?php if (!$it['video']): ?><span class="nit-lesson__type"><?php echo $e(get_string('modulename', $it['type'])); ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
