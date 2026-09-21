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
 * Options (Site administration → Plugins → Local plugins → Academy):
 *  - player_markcomplete: a "Mark as complete" button for manual-completion lessons.
 *  - player_lockorder: lessons open in order — a lesson is locked until every
 *    earlier lesson with completion tracking is complete.
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

    /** Whether the "lock lessons in order" option is on. */
    public static function lock_order(): bool {
        return (bool) get_config('local_academy', 'player_lockorder');
    }

    /** Whether the "Mark as complete" button is enabled. */
    public static function mark_complete_enabled(): bool {
        $v = get_config('local_academy', 'player_markcomplete');
        return $v === false || $v === null || (bool) $v; // default on
    }

    /**
     * The course's lessons in order with completion state — the one walk every
     * player screen uses. Staff (course:update) are never locked.
     *
     * @param \stdClass $course
     * @param int|null $currentcmid
     * @return array{lessons: array, sections: array, curindex: int}
     */
    public static function walk(\stdClass $course, ?int $currentcmid = null): array {
        global $USER;
        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);
        $lock = self::lock_order() && !has_capability('moodle/course:update', \context_course::instance($course->id));
        $lessons = [];
        $sections = [];
        $curindex = -1;
        $blocked = false; // becomes true after the first incomplete tracked lesson
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
                $tracked = $completion->is_enabled($mod) != COMPLETION_TRACKING_NONE;
                $manual = $completion->is_enabled($mod) == COMPLETION_TRACKING_MANUAL;
                $done = false;
                if ($tracked) {
                    $cdata = $completion->get_data($mod, false, $USER->id);
                    $done = in_array((int) $cdata->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                }
                $locked = $lock && $blocked;
                $entry = [
                    'cm'      => $mod,
                    'cmid'    => (int) $mod->id,
                    'name'    => format_string($mod->name),
                    'url'     => $locked ? '' : self::url_for($mod),
                    'done'    => $done,
                    'tracked' => $tracked,
                    'manual'  => $manual,
                    'locked'  => $locked,
                    'current' => $currentcmid !== null && (int) $mod->id === $currentcmid,
                    'video'   => in_array($mod->modname, self::VIDEO_MODS, true),
                    'type'    => $mod->modname,
                    'typename' => get_string('modulename', $mod->modname),
                ];
                if ($tracked && !$done) {
                    $blocked = true;
                }
                $lessons[] = $entry;
                if ($entry['current']) {
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
        return ['lessons' => $lessons, 'sections' => $sections, 'curindex' => $curindex];
    }

    /**
     * The lesson a learner should resume at: the first not-yet-completed lesson,
     * else the first lesson. Null when the course has no lesson at all.
     *
     * @param \stdClass $course
     * @return cm_info|null
     */
    public static function resume_cm(\stdClass $course): ?cm_info {
        $w = self::walk($course);
        $first = null;
        foreach ($w['lessons'] as $l) {
            if ($first === null) {
                $first = $l['cm'];
            }
            if ($l['tracked'] && !$l['done']) {
                return $l['cm'];
            }
        }
        return $first;
    }

    /**
     * Is this lesson locked for the current user (lock-order on and an earlier
     * tracked lesson is incomplete)?
     *
     * @param \stdClass $course
     * @param int $cmid
     * @return bool
     */
    public static function is_locked(\stdClass $course, int $cmid): bool {
        if (!self::lock_order()) {
            return false;
        }
        foreach (self::walk($course)['lessons'] as $l) {
            if ($l['cmid'] === $cmid) {
                return $l['locked'];
            }
        }
        return false;
    }

    /**
     * Enforce the lock: a locked lesson bounces to the learner's resume lesson
     * with a notice. Call before any output on a lesson page.
     *
     * @param \stdClass $course
     * @param int $cmid
     */
    public static function require_unlocked(\stdClass $course, int $cmid): void {
        if (!self::is_locked($course, $cmid)) {
            return;
        }
        $resume = self::resume_cm($course);
        $url = $resume ? self::url_for($resume) : (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        redirect($url, get_string('player_locked', 'local_academy'), null, \core\output\notification::NOTIFY_WARNING);
    }

    /** A small inline SVG per lesson type. */
    private static function icon(string $type, bool $video): string {
        if ($video) {
            return '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>';
        }
        switch ($type) {
            case 'quiz':
                return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 11l2 2 4-4"/><rect x="4" y="4" width="16" height="16" rx="3"/></svg>';
            case 'assign':
                return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
            case 'forum':
                return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>';
            case 'resource':
            case 'folder':
                return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';
            case 'url':
                return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>';
            default:
                return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h6"/></svg>';
        }
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
        global $USER, $PAGE;
        $e = fn($s) => s($s);
        $s = fn(string $key) => get_string($key, 'local_academy');

        $w = self::walk($course, (int) $cm->id);
        $lessons = $w['lessons'];
        $sections = $w['sections'];
        $curindex = $w['curindex'];
        $current = $curindex >= 0 ? $lessons[$curindex] : null;

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
        $showmark = $current && $current['manual'] && self::mark_complete_enabled();
        $nexturl = $next && $next['url'] !== '' ? $next['url'] : '';
        $nextlockednote = $next && $next['url'] === '';

        ob_start();
        ?>
<style>
  .nit-player{ --t-accent:var(--nit-brand-primary,#0E7C66);
    --t-accent-soft:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 8%, var(--t-bg,#FFFFFF));
    --t-accent-ring:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 30%, var(--t-border,#DCE9E5));
    --t-on:var(--nit-brand-on-primary,#fff);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; color:var(--t-ink,#16191D);
    display:grid; grid-template-columns:minmax(0,1fr) 360px; align-items:stretch; min-height:calc(100vh - 120px); }
  .nit-player__main{ background:<?php echo $stagebg; ?>; display:flex; flex-direction:column; min-width:0; }
  .nit-player__video{ position:relative; aspect-ratio:16/9; background:#000; }
  .nit-player__video iframe{ position:absolute; inset:0; width:100%; height:100%; border:0; }
  .nit-player__frame{ position:relative; background:var(--t-bg,#FFFFFF); flex:1; display:flex; }
  .nit-player__frame iframe{ display:block; width:100%; min-height:70vh; border:0; flex:1; }
  .nit-player__below{ background:var(--t-bg,#FFFFFF); padding:22px clamp(20px,3vw,34px) 34px; border-top:1px solid var(--t-border,#EDEDE9); }
  .nit-player__head{ display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .nit-player__eyebrow{ display:flex; align-items:center; gap:10px; font-size:12px; color:var(--t-muted,#6E7781); }
  .nit-player__type{ display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; background:var(--t-accent-soft); color:var(--t-accent); font-weight:700; font-size:11px; letter-spacing:.06em; text-transform:uppercase; }
  .nit-player__title{ margin:8px 0 0; font-size:clamp(20px,2.6vw,26px); font-weight:500; letter-spacing:-0.02em; color:var(--t-ink); }
  .nit-player__nav{ display:flex; gap:10px; flex:none; flex-wrap:wrap; align-items:center; }
  .nit-btn{ cursor:pointer; font-family:inherit; font-size:13px; font-weight:600; padding:11px 18px; border-radius:9px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; white-space:nowrap; border:1px solid var(--t-border2,#DCDCD7); background:var(--t-bg,#fff); color:var(--t-ink); transition:background-color .15s ease,color .15s ease,border-color .15s ease; }
  .nit-btn:hover{ border-color:var(--t-accent); color:var(--t-accent); text-decoration:none; }
  .nit-btn--primary{ background:var(--t-accent); border-color:var(--t-accent); color:var(--t-on); }
  .nit-btn--primary:hover{ background:color-mix(in srgb, var(--t-accent) 85%, #000); border-color:color-mix(in srgb, var(--t-accent) 85%, #000); color:var(--t-on); }
  .nit-btn--done{ background:var(--t-accent-soft); border-color:transparent; color:var(--t-accent); cursor:default; }
  .nit-btn[disabled]{ opacity:.55; cursor:default; }
  .nit-player__ov{ margin-top:20px; padding-top:18px; border-top:1px solid var(--t-border,#EDEDE9); font-size:15px; line-height:1.85; color:var(--t-muted,#3D4752); max-width:720px; }
  .nit-player__ov :is(p,ul,ol){ margin:0 0 12px; }
  .nit-player__aside{ border-inline-start:1px solid var(--t-border,#EDEDE9); background:var(--t-surface,#FAFAF8); display:flex; flex-direction:column; }
  .nit-player__asidehd{ padding:20px 22px; border-bottom:1px solid var(--t-border,#EDEDE9); background:var(--t-bg,#fff); }
  .nit-player__back{ font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--t-muted,#6E7781); text-decoration:none; font-weight:700; }
  .nit-player__back:hover{ color:var(--t-accent); }
  .nit-player__coursename{ display:block; margin-top:6px; font-size:15px; font-weight:600; line-height:1.4; color:var(--t-ink); text-decoration:none; }
  .nit-player__progress{ display:flex; align-items:center; gap:12px; margin-top:12px; }
  .nit-player__bar{ flex:1; height:6px; border-radius:3px; background:var(--t-border,#EDEDE9); overflow:hidden; }
  .nit-player__bar > i{ display:block; height:100%; background:var(--t-accent); border-radius:3px; }
  .nit-player__pct{ font-size:12px; font-weight:700; color:var(--t-accent); min-width:34px; text-align:end; }
  .nit-player__count{ font-size:12px; color:var(--t-muted,#6E7781); margin-top:6px; }
  .nit-player__list{ flex:1; overflow:auto; padding-bottom:20px; }
  .nit-player__seclabel{ padding:16px 22px 6px; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--t-muted,#6E7781); font-weight:700; }
  .nit-player__items{ padding:0 10px; display:flex; flex-direction:column; gap:2px; }
  .nit-lesson{ display:flex; align-items:center; gap:12px; padding:11px 12px; border-radius:10px; text-decoration:none; color:var(--t-muted,#55606B); border:1px solid transparent; }
  a.nit-lesson:hover{ background:var(--t-bg,#fff); color:var(--t-ink); text-decoration:none; }
  .nit-lesson--current{ background:var(--t-bg,#fff); border-color:var(--t-accent-ring); }
  .nit-lesson--locked{ opacity:.55; cursor:not-allowed; }
  .nit-lesson__ic{ flex:none; width:26px; height:26px; border-radius:50%; display:grid; place-items:center; background:var(--t-accent-soft); color:var(--t-accent); }
  .nit-lesson--current .nit-lesson__ic{ background:var(--t-accent); color:var(--t-on); }
  .nit-lesson--done .nit-lesson__ic{ background:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 14%, #fff); color:var(--t-accent); }
  .nit-lesson--locked .nit-lesson__ic{ background:var(--t-border,#EDEDE9); color:var(--t-muted,#6E7781); }
  .nit-lesson__name{ flex:1; font-size:13px; line-height:1.35; }
  .nit-lesson--current .nit-lesson__name{ font-weight:600; color:var(--t-ink); }
  .nit-lesson__type{ font-size:10px; color:var(--t-muted2,#8A8A82); text-transform:uppercase; letter-spacing:.06em; flex:none; }
  @media (max-width: 960px){ .nit-player{ grid-template-columns:1fr; min-height:0; } .nit-player__aside{ border-inline-start:0; border-top:1px solid var(--t-border,#EDEDE9); } }
</style>
<div class="nit-player" data-cmid="<?php echo (int) $cm->id; ?>">
  <div class="nit-player__main">
    <?php echo $stagehtml; ?>
    <div class="nit-player__below">
      <div class="nit-player__head">
        <div>
          <div class="nit-player__eyebrow">
            <span><?php echo $e($s('player_lesson') . ' ' . $position . ' ' . $s('player_of') . ' ' . max($total, 1)); ?></span>
            <?php if ($current): ?><span class="nit-player__type"><?php echo self::icon($current['type'], $current['video']); ?> <?php echo $e($current['typename']); ?></span><?php endif; ?>
          </div>
          <h1 class="nit-player__title"><?php echo $e(format_string($cm->name)); ?></h1>
        </div>
        <div class="nit-player__nav">
          <?php if ($showmark): ?>
            <?php if ($current['done']): ?>
              <span class="nit-btn nit-btn--done">&#10003; <?php echo $e($s('player_completed')); ?></span>
            <?php else: ?>
              <button type="button" class="nit-btn" id="nit-mark-complete" data-next="<?php echo $e($nexturl); ?>">&#10003; <?php echo $e($s('player_markcomplete_btn')); ?></button>
            <?php endif; ?>
          <?php elseif ($current && $current['tracked'] && $current['done']): ?>
            <span class="nit-btn nit-btn--done">&#10003; <?php echo $e($s('player_completed')); ?></span>
          <?php endif; ?>
          <?php if ($prev && $prev['url'] !== ''): ?>
            <a class="nit-btn" href="<?php echo $e($prev['url']); ?>">&larr; <?php echo $e(get_string('previous')); ?></a>
          <?php endif; ?>
          <?php if ($next): ?>
            <?php if ($nexturl !== ''): ?>
              <a class="nit-btn nit-btn--primary" href="<?php echo $e($nexturl); ?>"><?php echo $e(get_string('next')); ?> &rarr;</a>
            <?php else: ?>
              <span class="nit-btn nit-btn--primary" disabled title="<?php echo $e($s('player_locked')); ?>">&#128274; <?php echo $e(get_string('next')); ?></span>
            <?php endif; ?>
          <?php else: ?>
            <a class="nit-btn nit-btn--primary" href="<?php echo $e($courseurl); ?>"><?php echo $e(get_string('course')); ?> &rarr;</a>
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
      <a class="nit-player__back" href="<?php echo $e((new moodle_url('/my/'))->out(false)); ?>">&larr; <?php echo $e(get_string('myhome')); ?></a>
      <a class="nit-player__coursename" href="<?php echo $e($courseurl); ?>"><?php echo $e(format_string($course->fullname)); ?></a>
      <?php if ($pct !== null): ?>
        <div class="nit-player__progress">
          <div class="nit-player__bar"><i style="width:<?php echo (int) $pct; ?>%;"></i></div>
          <span class="nit-player__pct"><?php echo (int) $pct; ?>%</span>
        </div>
        <div class="nit-player__count"><?php echo $e($donecount . ' ' . $s('player_of') . ' ' . max($total, 1)); ?></div>
      <?php endif; ?>
    </div>
    <div class="nit-player__list">
      <?php foreach ($sections as $si => $sec): ?>
        <div class="nit-player__seclabel"><?php echo $e(str_pad((string) ($si + 1), 2, '0', STR_PAD_LEFT) . ' · ' . $sec['name']); ?></div>
        <div class="nit-player__items">
          <?php foreach ($sec['items'] as $it): ?>
            <?php
            $cls = 'nit-lesson' . ($it['current'] ? ' nit-lesson--current' : '') . ($it['done'] ? ' nit-lesson--done' : '') . ($it['locked'] ? ' nit-lesson--locked' : '');
            $glyph = $it['locked'] ? '&#128274;' : ($it['done'] && !$it['current'] ? '&#10003;' : self::icon($it['type'], $it['video']));
            $tag = $it['locked'] ? 'span' : 'a';
            ?>
            <<?php echo $tag; ?> class="<?php echo $cls; ?>"<?php if (!$it['locked']): ?> href="<?php echo $e($it['url']); ?>"<?php else: ?> title="<?php echo $e($s('player_locked')); ?>"<?php endif; ?>>
              <span class="nit-lesson__ic"><?php echo $glyph; ?></span>
              <span class="nit-lesson__name"><?php echo $e($it['name']); ?></span>
              <?php if (!$it['video']): ?><span class="nit-lesson__type"><?php echo $e($it['typename']); ?></span><?php endif; ?>
            </<?php echo $tag; ?>>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
<script>
(function () {
  // Never render the player inside another player (a course link opened in the
  // lesson frame): break out to the top window.
  if (window.top !== window.self) { try { window.top.location.href = window.location.href; } catch (e) { /* ignore */ } return; }
  var b = document.getElementById('nit-mark-complete');
  if (!b) { return; }
  b.addEventListener('click', function () {
    b.disabled = true;
    var cmid = parseInt(document.querySelector('.nit-player').getAttribute('data-cmid'), 10);
    var url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey + '&info=core_completion_update_activity_completion_status_manually';
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify([{ index: 0, methodname: 'core_completion_update_activity_completion_status_manually', args: { cmid: cmid, completed: true } }]) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d[0] && !d[0].error) { var n = b.getAttribute('data-next'); window.location.href = n || window.location.href; return; }
        b.disabled = false;
      })
      .catch(function () { b.disabled = false; });
  });
})();
</script>
        <?php
        return (string) ob_get_clean();
    }
}
