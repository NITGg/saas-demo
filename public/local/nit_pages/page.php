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
 * Public and admin view for a static CMS page.
 *
 *   GET /local/nit_pages/page.php?p=<slug> [&embedded=1] [&edit=0|1] [&lang=ar|en]
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$embedded = !empty($_GET['embedded']);
if ($embedded) {
    define('NO_MOODLE_COOKIES', true);
}

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_nit_pages\page_manager;
use local_nit_pages\article_manager;

global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE;

$slug = optional_param('p', '', PARAM_RAW_TRIMMED);
if ($slug === '') {
    $slug = optional_param('slug', '', PARAM_RAW_TRIMMED);
}

// Support resolving by id if p is numeric.
if (is_numeric($slug) && (int)$slug > 0) {
    $page = page_manager::get_page_by_id((int)$slug);
} else {
    $page = page_manager::get_page_by_slug($slug);
}

if (!$page) {
    throw new \moodle_exception('page_not_found', 'local_nit_pages');
}

$context = \context_system::instance();

// Check language.
$reqlang = optional_param('lang', '', PARAM_ALPHA);
if ($reqlang === 'ar' || $reqlang === 'en') {
    force_current_language($reqlang);
}
$currlang = current_language();
$isar = (strpos($currlang, 'ar') === 0);

// Check access permissions.
$isadmin = has_capability('local/nit_pages:manage', $context);
if ($page->status === page_manager::STATUS_DRAFT && !$isadmin) {
    throw new \moodle_exception('page_not_found', 'local_nit_pages');
}
if (!empty($page->loggedin_only) && (!isloggedin() || isguestuser())) {
    require_login();
}

$title = $isar ? $page->title_ar : $page->title_en;
$seodesc = $isar ? $page->seo_desc_ar : $page->seo_desc_en;

// ── EMBEDDED MODE (bare webview for mobile app) ──────────────────────────────
if ($embedded) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $dir = $isar ? 'rtl' : 'ltr';
    $contenthtml = page_manager::get_page_html($page, $isar ? 'ar' : 'en');
    $updatedstr = $page->timemodified ? userdate($page->timemodified, get_string('strftimedate', 'langconfig')) : date('Y');

    $e = fn($s) => htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="' . ($isar ? 'ar' : 'en') . '" dir="' . $dir . '"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $e($title) . '</title>'
        . '<style>'
        . ':root{color-scheme:light dark;}'
        . 'body{margin:0;padding:20px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;'
        . 'line-height:1.8;color:#1a2230;background:#fff;font-size:15px;}'
        . 'h1{font-size:24px;margin:0 0 8px;font-weight:800;}'
        . '.updated{color:#64748b;font-size:13px;margin-bottom:24px;font-weight:600;}'
        . 'a{color:#1565c0;text-decoration:none;} a:hover{text-decoration:underline;}'
        . '@media (prefers-color-scheme: dark){body{background:#0c141f;color:#eef3f9;}.updated{color:#94a3b8;}a{color:#7fabdb;}}'
        . '</style></head><body>'
        . '<h1>' . $e($title) . '</h1>'
        . '<div class="updated">' . get_string('last_updated', 'local_nit_pages') . ': ' . $e($updatedstr) . '</div>'
        . $contenthtml
        . '</body></html>';
    exit;
}

// ── NORMAL WEB PAGE ──────────────────────────────────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/local/nit_pages/page.php', ['p' => ($isar ? $page->slug_ar : $page->slug_en)]));
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_pagetype('local-nit_pages-page');
$PAGE->set_subpage((string) $page->id);
$PAGE->set_title($title . ' - ' . format_string($SITE->shortname));
$PAGE->set_heading($title);

// Handle inline edit toggle for admins.
$edittoggle = optional_param('edit', -1, PARAM_INT);
$reqsesskey = optional_param('sesskey', '', PARAM_RAW);
if ($edittoggle !== -1 && $isadmin) {
    if (!empty($reqsesskey) && confirm_sesskey($reqsesskey)) {
        $USER->editing = ($edittoggle === 1);
    }
    redirect($PAGE->url);
}

// SEO meta tags.
$canonicalurl = (new \moodle_url('/local/nit_pages/page.php', ['p' => ($isar ? $page->slug_ar : $page->slug_en)]))->out(false);
$shareimage = !empty($page->share_image) ? $page->share_image : ($OUTPUT->get_logo_url() ? $OUTPUT->get_logo_url()->out(false) : '');

$seometa = '<link rel="canonical" href="' . s($canonicalurl) . '">' . "\n";
$seometa .= '<meta property="og:type" content="website">' . "\n";
$seometa .= '<meta property="og:title" content="' . s($title) . '">' . "\n";
$seometa .= '<meta property="og:url" content="' . s($canonicalurl) . '">' . "\n";
if ($seodesc !== '') {
    $seometa .= '<meta name="description" content="' . s($seodesc) . '">' . "\n";
    $seometa .= '<meta property="og:description" content="' . s($seodesc) . '">' . "\n";
}
if ($shareimage !== '') {
    $seometa .= '<meta property="og:image" content="' . s($shareimage) . '">' . "\n";
    $seometa .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
}
$CFG->additionalhtmlhead = ($CFG->additionalhtmlhead ?? '') . "\n" . $seometa;

// Admin toolbar setup (rendered at the top of the page in fullwidth template).
if ($isadmin) {
    $isediting = !empty($USER->editing);
    $toggleediturl = new \moodle_url($PAGE->url, ['edit' => $isediting ? 0 : 1, 'sesskey' => sesskey()]);
    $editsettingsurl = new \moodle_url('/local/nit_pages/edit_page.php', ['id' => $page->id]);
    $dashboardurl = new \moodle_url('/local/nit_pages/index.php');

    $statusbadge = ($page->status === page_manager::STATUS_PUBLISHED)
        ? '<span class="badge" style="background:#dcfce7;color:#15803d;font-size:12px;font-weight:700;padding:5px 12px;border-radius:9999px;">' . get_string('published', 'local_nit_pages') . '</span>'
        : '<span class="badge" style="background:#fef3c7;color:#b45309;font-size:12px;font-weight:700;padding:5px 12px;border-radius:9999px;">' . get_string('draft', 'local_nit_pages') . '</span>';

    $accessbadge = !empty($page->loggedin_only)
        ? '<span class="badge" style="background:#f1f5f9;color:#475569;font-size:12px;font-weight:700;padding:5px 12px;border-radius:9999px;">' . get_string('loggedin_only', 'local_nit_pages') . '</span>'
        : '<span class="badge" style="background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700;padding:5px 12px;border-radius:9999px;">' . ($isar ? 'عام للجميع' : 'Public') . '</span>';

    $editbtntext = $isediting
        ? ($isar ? '✓ إنهاء وضع التحرير' : '✓ Finish Editing')
        : ($isar ? '✏️ تعديل المحتوى (وضع التحرير)' : '✏️ Edit Content (Edit Mode)');

    $editbtnstyle = $isediting
        ? 'background:#16a34a;border-color:#16a34a;color:#ffffff;'
        : 'background:#1b75d0;border-color:#1b75d0;color:#ffffff;';

    $tiphtml = $isediting
        ? '<div style="background:#eff6ff;color:#1e40af;padding:12px 24px;border-top:1px solid #dbeafe;font-size:13px;font-weight:600;display:flex;align-items:center;gap:10px;justify-content:center;text-align:center;">' .
            '💡 ' . ($isar
                ? 'وضع التحرير مفعّل الآن: اضغط على القائمة (⋮ أو ⚙️) أعلى أي بلوك لتعديل محتواه، أو استخدم زر (+ إضافة كتلة) لإضافة قسم جديد.'
                : 'Edit mode active: click (⋮) on any block to edit its content, or click (+ Add a block) to add new sections.') .
          '</div>'
        : '';

    $GLOBALS['NIT_ADMIN_TOOLBAR'] = '
    <div class="nit-admin-cms-toolbar d-print-none" style="background:#ffffff;border-bottom:2px solid #e2e8f0;box-shadow:0 4px 16px rgba(0,0,0,0.06);margin-top:96px;font-family:\'Tajawal\',\'Almarai\',sans-serif;position:relative;z-index:90;">
      <div style="max-width:1400px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <span style="font-weight:800;color:#0f172a;font-size:16px;">📄 ' . s($title) . '</span>
          ' . $statusbadge . '
          ' . $accessbadge . '
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <a href="' . $toggleediturl->out() . '" class="btn fw-bold" style="' . $editbtnstyle . 'border-radius:10px;padding:8px 20px;font-size:13px;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);text-decoration:none;">
            ' . $editbtntext . '
          </a>
          <a href="' . $editsettingsurl->out() . '" class="btn btn-outline-secondary fw-bold" style="border-radius:10px;padding:8px 16px;font-size:13px;color:#334155;border-color:#cbd5e1;text-decoration:none;">
            ⚙️ ' . get_string('edit_settings', 'local_nit_pages') . '
          </a>
          <a href="' . $dashboardurl->out() . '" class="btn btn-outline-secondary" style="border-radius:10px;padding:8px 16px;font-size:13px;color:#64748b;border-color:#e2e8f0;text-decoration:none;">
            ← ' . get_string('back_to_pages', 'local_nit_pages') . '
          </a>
        </div>
      </div>
      ' . $tiphtml . '
    </div>';
}

echo $OUTPUT->header();

// If this is the Articles page, render the automatic articles list underneath the block area!
if ($page->default_key === 'articles') {
    $searchq = optional_param('q', '', PARAM_TEXT);
    $artpage = optional_param('page', 0, PARAM_INT);
    $perpage = 9;

    $artdata = article_manager::get_articles($artpage, $perpage, $searchq, !$isadmin);
    $articles = $artdata['articles'];
    $totalarticles = $artdata['total'];
    $totalpages = $artdata['pages'];

    echo '<div class="nit-articles-container" style="max-width:1200px;margin:0 auto;padding:24px 20px 80px;font-family:\'Almarai\',\'Tajawal\',sans-serif;">';

    // Search bar.
    echo '
    <div style="margin-bottom:36px;display:flex;justify-content:center;">
      <form action="' . $PAGE->url->out(false) . '" method="get" style="display:flex;max-width:540px;width:100%;gap:10px;">
        <input type="hidden" name="p" value="' . s($slug) . '">
        <input type="text" name="q" value="' . s($searchq) . '" placeholder="' . s(get_string('search_articles', 'local_nit_pages')) . '" class="form-control" style="border-radius:50px;padding:12px 20px;font-size:15px;background:var(--nit-brand-surface, #121e2d);border:1px solid var(--nit-brand-border, #223244);color:var(--nit-brand-textprimary, #eef3f9);">
        <button type="submit" class="btn btn-primary" style="border-radius:50px;padding:0 24px;font-weight:700;">' . ($isar ? 'بحث' : 'Search') . '</button>
      </form>
    </div>';

    if (empty($articles)) {
        echo '<div style="text-align:center;padding:60px 20px;color:var(--nit-brand-textsecondary, #94a3b8);font-size:16px;">'
            . get_string('no_articles_found', 'local_nit_pages') . '</div>';
    } else {
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:28px;">';
        foreach ($articles as $art) {
            $arttitle = $isar ? $art->title_ar : $art->title_en;
            $artsummary = $isar ? $art->summary_ar : $art->summary_en;
            $arturl = new \moodle_url('/local/nit_pages/article.php', ['a' => $art->slug]);
            $authorname = article_manager::get_author_name((int)$art->author_id);
            $pubdate = $art->published_at ? userdate($art->published_at, get_string('strftimedate', 'langconfig')) : '';

            $coverstyle = 'aspect-ratio:16/9;background:linear-gradient(135deg, #1c2a3a 0%, #121e2d 100%);border-radius:14px;overflow:hidden;position:relative;display:flex;align-items:center;justify-content:center;color:#7fabdb;font-size:32px;';
            if (!empty($art->cover_image)) {
                $coverstyle .= 'background-image:url(\'' . s($art->cover_image) . '\');background-size:cover;background-position:center;';
                $covercontent = '';
            } else {
                $covercontent = '📰';
            }

            echo '
            <article style="background:var(--nit-brand-surface, #121e2d);border:1px solid var(--nit-brand-border, #223244);border-radius:18px;padding:16px;display:flex;flex-direction:column;transition:transform .2s ease, box-shadow .2s ease;" onmouseover="this.style.transform=\'translateY(-4px)\';this.style.boxShadow=\'0 14px 30px rgba(0,0,0,0.18)\';" onmouseout="this.style.transform=\'none\';this.style.boxShadow=\'none\';">
              <a href="' . $arturl->out() . '" style="text-decoration:none;">
                <div style="' . $coverstyle . '">' . $covercontent . '</div>
              </a>
              <div style="padding:16px 8px 8px;display:flex;flex-direction:column;flex:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--nit-brand-textsecondary, #94a3b8);margin-bottom:10px;">
                  <span>' . s($authorname) . '</span>
                  <span>' . s($pubdate) . '</span>
                </div>
                <h3 style="margin:0 0 10px;font-size:18px;font-weight:700;line-height:1.4;">
                  <a href="' . $arturl->out() . '" style="color:var(--nit-brand-textprimary, #eef3f9);text-decoration:none;">' . s($arttitle) . '</a>
                </h3>
                <p style="margin:0 0 16px;font-size:14px;color:var(--nit-brand-textsecondary, #94a3b8);line-height:1.6;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">' . s($artsummary) . '</p>
                <div style="margin-top:auto;">
                  <a href="' . $arturl->out() . '" style="color:var(--nit-brand-primary, #5488c4);font-weight:700;font-size:14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    ' . get_string('read_more', 'local_nit_pages') . ' →
                  </a>
                </div>
              </div>
            </article>';
        }
        echo '</div>';

        // Pagination.
        if ($totalpages > 1) {
            echo '<div style="display:flex;justify-content:center;gap:8px;margin-top:40px;">';
            for ($p = 0; $p < $totalpages; $p++) {
                $purl = new \moodle_url($PAGE->url, ['page' => $p, 'q' => $searchq]);
                $iscurr = ($p === $artpage);
                echo '<a href="' . $purl->out() . '" class="btn btn-sm ' . ($iscurr ? 'btn-primary' : 'btn-outline-secondary') . '" style="border-radius:8px;min-width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-weight:700;">' . ($p + 1) . '</a>';
            }
            echo '</div>';
        }
    }

    echo '</div>';
}

echo $OUTPUT->footer();
