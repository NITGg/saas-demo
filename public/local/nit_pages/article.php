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
 * Article detail page.
 *
 *   GET /local/nit_pages/article.php?a=<slug>
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_pages\article_manager;

global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE;

$slug = optional_param('a', '', PARAM_RAW_TRIMMED);
if ($slug === '') {
    $slug = optional_param('slug', '', PARAM_RAW_TRIMMED);
}

if (is_numeric($slug) && (int)$slug > 0) {
    $article = article_manager::get_article_by_id((int)$slug);
} else {
    $article = article_manager::get_article_by_slug($slug);
}

if (!$article) {
    throw new \moodle_exception('article_not_found', 'local_nit_pages');
}

$context = \context_system::instance();
$isadmin = has_capability('local/nit_pages:manage', $context);

if (!article_manager::can_view($article, (int)$USER->id)) {
    throw new \moodle_exception('article_not_found', 'local_nit_pages');
}

$currlang = current_language();
$isar = (strpos($currlang, 'ar') === 0);

$title = $isar ? $article->title_ar : $article->title_en;
$summary = $isar ? $article->summary_ar : $article->summary_en;
$body = $isar ? $article->body_ar : $article->body_en;
if (trim($body) === '') {
    $body = $isar ? $article->body_en : $article->body_ar;
}

$authorname = article_manager::get_author_name((int)$article->author_id);
$pubdate = $article->published_at ? userdate($article->published_at, get_string('strftimedate', 'langconfig')) : '';

$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/local/nit_pages/article.php', ['a' => $article->slug]));
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title($title . ' - ' . format_string($SITE->shortname));
$PAGE->set_heading($title);

// SEO meta tags.
$canonicalurl = (new \moodle_url('/local/nit_pages/article.php', ['a' => $article->slug]))->out(false);
$seometa = '<link rel="canonical" href="' . s($canonicalurl) . '">' . "\n";
$seometa .= '<meta property="og:type" content="article">' . "\n";
$seometa .= '<meta property="og:title" content="' . s($title) . '">' . "\n";
$seometa .= '<meta property="og:url" content="' . s($canonicalurl) . '">' . "\n";
if (!empty($summary)) {
    $seometa .= '<meta name="description" content="' . s($summary) . '">' . "\n";
    $seometa .= '<meta property="og:description" content="' . s($summary) . '">' . "\n";
}
if (!empty($article->cover_image)) {
    $seometa .= '<meta property="og:image" content="' . s($article->cover_image) . '">' . "\n";
    $seometa .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
}
if (!empty($article->published_at)) {
    $seometa .= '<meta property="article:published_time" content="' . date('c', $article->published_at) . '">' . "\n";
}
$CFG->additionalhtmlhead = ($CFG->additionalhtmlhead ?? '') . "\n" . $seometa;

echo $OUTPUT->header();

// Admin toolbar if admin.
if ($isadmin) {
    $editurl = new \moodle_url('/local/nit_pages/edit_article.php', ['id' => $article->id]);
    $manageurl = new \moodle_url('/local/nit_pages/manage_articles.php');
    $statusbadge = ($article->status === article_manager::STATUS_PUBLISHED)
        ? '<span class="badge bg-success" style="font-size:12px;">' . get_string('published', 'local_nit_pages') . '</span>'
        : '<span class="badge bg-warning text-dark" style="font-size:12px;">' . get_string('draft', 'local_nit_pages') . '</span>';

    echo '
    <div class="nit-admin-cms-toolbar d-print-none" style="background:var(--nit-brand-surface, #121e2d);border-bottom:1px solid var(--nit-brand-border, #223244);padding:10px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-family:\'Almarai\',sans-serif;font-size:13px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-weight:700;color:var(--nit-brand-textprimary, #eef3f9);">' . get_string('admin_toolbar', 'local_nit_pages') . ':</span>
        ' . $statusbadge . '
        <span style="color:var(--nit-brand-textsecondary, #94a3b8);font-size:12px;">ID: ' . $article->id . '</span>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <a href="' . $editurl->out() . '" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-weight:700;">
          ' . get_string('edit_article', 'local_nit_pages') . '
        </a>
        <a href="' . $manageurl->out() . '" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;">
          ' . get_string('manage_articles', 'local_nit_pages') . '
        </a>
      </div>
    </div>';
}

$articlespageurl = new \moodle_url('/local/nit_pages/page.php', ['p' => 'articles']);

echo '
<div class="nit-article-view" style="max-width:880px;margin:0 auto;padding:40px 20px 80px;font-family:\'Almarai\',\'Tajawal\',sans-serif;line-height:1.8;">
  <nav style="margin-bottom:24px;font-size:14px;color:var(--nit-brand-textsecondary, #94a3b8);">
    <a href="' . (new \moodle_url('/'))->out() . '" style="color:var(--nit-brand-textsecondary, #94a3b8);text-decoration:none;">' . ($isar ? 'الرئيسية' : 'Home') . '</a>
    <span style="margin:0 8px;">/</span>
    <a href="' . $articlespageurl->out() . '" style="color:var(--nit-brand-textsecondary, #94a3b8);text-decoration:none;">' . get_string('manage_articles', 'local_nit_pages') . '</a>
    <span style="margin:0 8px;">/</span>
    <span style="color:var(--nit-brand-textprimary, #eef3f9);">' . s($title) . '</span>
  </nav>

  <header style="margin-bottom:32px;">
    <h1 style="font-size:clamp(28px,3.8vw,44px);font-weight:800;color:var(--nit-brand-textprimary, #eef3f9);line-height:1.3;margin:0 0 16px;">
      ' . s($title) . '
    </h1>
    <div style="display:flex;align-items:center;gap:16px;color:var(--nit-brand-textsecondary, #94a3b8);font-size:14px;flex-wrap:wrap;">
      <span style="display:flex;align-items:center;gap:6px;">✍️ <strong>' . s($authorname) . '</strong></span>
      <span>•</span>
      <span>📅 ' . s($pubdate) . '</span>
    </div>
  </header>';

if (!empty($article->cover_image)) {
    echo '
    <div style="border-radius:18px;overflow:hidden;margin-bottom:36px;box-shadow:0 12px 30px rgba(0,0,0,0.15);max-height:450px;display:flex;align-items:center;justify-content:center;background:#121e2d;">
      <img src="' . s($article->cover_image) . '" alt="' . s($title) . '" style="width:100%;height:auto;object-fit:cover;max-height:450px;">
    </div>';
}

if (!empty($summary)) {
    echo '
    <div style="background:var(--nit-brand-surface, #121e2d);border-inline-start:4px solid var(--nit-brand-primary, #5488c4);border-radius:12px;padding:20px 24px;margin-bottom:32px;font-size:16px;font-weight:600;color:var(--nit-brand-textprimary, #eef3f9);line-height:1.75;">
      ' . s($summary) . '
    </div>';
}

echo '
  <div class="nit-article-body" style="font-size:16px;color:var(--nit-brand-textprimary, #eef3f9);line-height:1.9;">
    ' . format_text($body, FORMAT_HTML, ['filter' => true, 'noclean' => true]) . '
  </div>

  <footer style="margin-top:56px;padding-top:24px;border-top:1px solid var(--nit-brand-border, #223244);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <a href="' . $articlespageurl->out() . '" class="btn btn-outline-secondary" style="border-radius:10px;font-weight:700;">
      ← ' . get_string('back_to_articles', 'local_nit_pages') . '
    </a>
  </footer>
</div>';

echo $OUTPUT->footer();
