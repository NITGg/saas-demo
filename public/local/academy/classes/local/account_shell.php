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

namespace local_academy\local;

/**
 * The account pages' shared frame: the "Profile & settings" left nav and the card styles of
 * /local/academy/profile.php. Other pages of the account (the teacher profile of local_nit_tutors)
 * print the same nav and styles, so they look like one screen.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class account_shell {

    /**
     * The nav items, in order: [key => {label, icon, url, on}].
     *
     * @param string $active key of the current page: profile, tutor, …
     * @return array
     */
    public static function nav(string $active): array {
        global $CFG, $USER;
        $isar = strpos(current_language(), 'ar') === 0;
        $t = fn(string $en, string $ar) => $isar ? $ar : $en;
        $url = fn(string $path, array $params = []) => (new \moodle_url($path, $params))->out(false);

        $nav = ['profile' => ['label' => $t('Profile', 'الملف الشخصي'), 'icon' => '👤', 'url' => $url('/local/academy/profile.php')]];
        // Teachers and applicants: their public teacher profile + application (local_nit_tutors).
        $tutor = null;
        if (class_exists('\local_nit_tutors\local\application')) {
            $known = \local_academy\teacher_manager::is_teacher((int) $USER->id)
                || \local_nit_tutors\local\application::get((int) $USER->id);
            $tutor = ['label' => $known ? $t('Teacher profile', 'ملف المدرس') : $t('Apply to teach', 'انضم كمدرس'),
                'icon' => '🧑‍🏫', 'url' => $url('/local/nit_tutors/application.php')];
            if ($known) {
                $nav['tutor'] = $tutor;
                $tutor = null;
            }
        }
        $nav['security'] = ['label' => $t('Security', 'الأمان'), 'icon' => '🔒', 'url' => $url('/login/change_password.php')];
        $nav['notifications'] = ['label' => $t('Notifications', 'الإشعارات'), 'icon' => '🔔',
            'url' => $url('/message/notificationpreferences.php', ['userid' => $USER->id])];
        if (file_exists($CFG->dirroot . '/local/payments/history.php')) {
            $nav['billing'] = ['label' => $t('Billing & invoices', 'الفواتير'), 'icon' => '🧾', 'url' => $url('/local/payments/history.php')];
        }
        $nav['certs'] = ['label' => $t('Certificates', 'الشهادات'), 'icon' => '🎓', 'url' => $url('/course/index.php')];
        if ($tutor) {
            $nav['tutor'] = $tutor; // Not a teacher yet: "Apply to teach" goes last.
        }
        foreach ($nav as $key => &$item) {
            $item['on'] = $key === $active;
        }
        return $nav;
    }

    /**
     * The nav as HTML.
     *
     * @param string $active
     * @return string
     */
    public static function nav_html(string $active): string {
        $out = '<nav class="nit-prof-nav">';
        foreach (self::nav($active) as $item) {
            $out .= '<a' . ($item['on'] ? ' class="on" aria-current="page"' : '') . ' href="' . s($item['url']) . '">'
                . '<span class="ic">' . $item['icon'] . '</span>' . s($item['label']) . '</a>';
        }
        return $out . '</nav>';
    }

    /**
     * The shared styles (without the <style> tag).
     *
     * @return string
     */
    public static function css(): string {
        return <<<'CSS'
  .nit-prof{ --t-accent: var(--nit-brand-primary, #0E7C66); --t-on: var(--nit-brand-on-primary,#fff);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; color:var(--t-ink,#16191D);
    max-width:1240px; margin:0 auto; padding:8px 4px 60px; }
  .nit-prof h1{ margin:0 0 28px; font-size:clamp(30px,4vw,38px); font-weight:250; letter-spacing:-0.03em; color:var(--t-ink,#16191D); }
  .nit-prof-grid{ display:grid; grid-template-columns:252px minmax(0,1fr); gap:30px; align-items:start; }
  @media (max-width: 820px){ .nit-prof-grid{ grid-template-columns:minmax(0,1fr); } }
  .nit-prof-nav{ background:var(--t-bg,#fff); border:1px solid var(--t-border,#EDEDE9); border-radius:16px; padding:8px; display:flex; flex-direction:column; gap:2px; }
  .nit-prof-nav a{ display:flex; align-items:center; gap:11px; padding:11px 13px; border-radius:9px; font-size:14px; color:var(--t-muted2,#3D4752); text-decoration:none; }
  .nit-prof-nav a:hover{ background:var(--t-surface,#FAFAF8); color:var(--t-ink,#16191D); }
  .nit-prof-nav a.on{ background:color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 9%, transparent); color:var(--t-accent); font-weight:600; }
  .nit-prof-nav a .ic{ width:20px; text-align:center; color:var(--t-muted,#8A8A82); }
  .nit-prof-nav a.on .ic{ color:var(--t-accent); }
  .nit-prof-main{ display:flex; flex-direction:column; gap:18px; min-width:0; }
  .nit-prof-card{ background:var(--t-bg,#fff); border:1px solid var(--t-border,#EDEDE9); border-radius:16px; }
  .nit-prof-id{ padding:26px; display:flex; align-items:center; gap:22px; flex-wrap:wrap; }
  .nit-prof-av{ width:78px; height:78px; border-radius:50%; object-fit:cover; background:linear-gradient(145deg, var(--t-accent), color-mix(in srgb, var(--t-accent) 60%, #16A98B)); color:#fff; display:grid; place-items:center; font-size:26px; font-weight:500; flex:none; }
  .nit-prof-id .who{ flex:1; min-width:min(180px, 100%); overflow-wrap:anywhere; }
  .nit-prof-id .nm{ font-size:22px; font-weight:550; letter-spacing:-0.02em; }
  .nit-prof-id .sub{ font-size:13px; color:var(--t-muted,#8A8A82); margin-top:4px; }
  .nit-prof-idbtns{ display:flex; flex-wrap:wrap; gap:10px; }
  .nit-prof-cardhd{ padding:18px 22px; border-bottom:1px solid var(--t-line,#F1F1ED); font-size:14px; font-weight:600; }
  .nit-prof-body{ padding:22px; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px; }
  @media (max-width: 560px){ .nit-prof-body{ grid-template-columns:minmax(0,1fr); } }
  .nit-prof-body .full{ grid-column:1 / -1; }
  .nit-prof-lbl{ font-size:12px; color:var(--t-muted,#8A8A82); margin-bottom:8px; }
  .nit-prof-field{ width:100%; height:46px; border:1px solid var(--t-border2,#DCDCD7); border-radius:10px; background:var(--t-field,#FBFBF9); padding:0 14px; font:inherit; font-size:14px; color:var(--t-ink,#16191D); box-sizing:border-box; }
  .nit-prof-field:focus{ outline:none; border-color:var(--t-accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--nit-brand-primary,#0E7C66) 16%, transparent); }
  .nit-prof-field[readonly]{ color:var(--t-muted,#8A8A82); }
  textarea.nit-prof-field{ height:88px; padding:12px 14px; line-height:1.7; }
  .nit-prof-pref{ padding:22px; display:flex; flex-direction:column; gap:20px; }
  .nit-prof-prow{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; }
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
CSS;
    }
}
