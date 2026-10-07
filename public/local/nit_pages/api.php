<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Mobile JSON API for Static Pages and Articles (CMS).
 *
 *   GET /local/nit_pages/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * Functions:
 *   - get_pages
 *   - get_page
 *   - get_articles
 *   - get_article
 *
 * Supports public visitors and the shared pre-login token.
 * Optional `lang=ar|en` parameter.
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_pages\page_manager;
use local_nit_pages\article_manager;

api::boot();

$user = api::authenticate(false);
$userid = api::public_viewer();

$lang = optional_param('lang', '', PARAM_LANG);
if ($lang !== '') {
    force_current_language($lang);
} else {
    $lang = current_language();
}
$isar = (strpos($lang, 'ar') === 0);

$isadmin = false;
if ($userid > 0) {
    $isadmin = has_capability('local/nit_pages:manage', \context_system::instance(), $userid);
}

api::run(function (string $function) use ($userid, $isadmin, $lang, $isar) {
    switch ($function) {
        // List published pages accessible to the viewer.
        case 'get_pages':
            $allpages = page_manager::get_all_pages(!$isadmin, ($userid === 0));
            $items = [];
            foreach ($allpages as $p) {
                if (!page_manager::can_view($p, $userid)) {
                    continue;
                }
                $primaryslug = $isar ? ($p->slug_ar ?: $p->slug_en) : ($p->slug_en ?: $p->slug_ar);
                $title = $isar ? ($p->title_ar ?: $p->title_en) : ($p->title_en ?: $p->title_ar);
                $embeddedurl = new \moodle_url('/local/nit_pages/page.php', [
                    'p' => $primaryslug,
                    'embedded' => 1,
                    'lang' => $lang,
                ]);
                $weburl = new \moodle_url('/local/nit_pages/page.php', [
                    'p' => $primaryslug,
                    'lang' => $lang,
                ]);

                $items[] = [
                    'id'           => (int) $p->id,
                    'slug'         => $primaryslug,
                    'slug_ar'      => $p->slug_ar,
                    'slug_en'      => $p->slug_en,
                    'title'        => $title,
                    'title_ar'     => $p->title_ar,
                    'title_en'     => $p->title_en,
                    'is_default'   => !empty($p->is_default),
                    'default_key'  => $p->default_key ?? '',
                    'updated_at'   => (int) $p->timemodified,
                    'embedded_url' => api::file_url($embeddedurl->out(false)),
                    'url'          => api::file_url($weburl->out(false)),
                ];
            }
            return ['pages' => $items];

        // Retrieve a single static page with resolved HTML content.
        case 'get_page':
            $slug = optional_param('slug', '', PARAM_RAW_TRIMMED);
            if ($slug === '') {
                $slug = optional_param('p', '', PARAM_RAW_TRIMMED);
            }
            $id = optional_param('id', 0, PARAM_INT);
            $key = optional_param('key', '', PARAM_ALPHANUMEXT);

            $page = null;
            if ($id > 0) {
                $page = page_manager::get_page_by_id($id);
            } else if ($key !== '') {
                $page = page_manager::get_page_by_default_key($key);
            } else if ($slug !== '') {
                $page = page_manager::get_page_by_slug($slug);
            }

            if (!$page) {
                api::fail('notfound', get_string('page_not_found', 'local_nit_pages'));
            }

            if (!page_manager::can_view($page, $userid)) {
                if (!empty($page->loggedin_only) && $userid === 0) {
                    api::fail('authrequired', get_string('err_authrequired', 'local_academy'), 401);
                }
                api::fail('nopermission', get_string('err_nopermission', 'local_academy'));
            }

            $primaryslug = $isar ? ($page->slug_ar ?: $page->slug_en) : ($page->slug_en ?: $page->slug_ar);
            $title = $isar ? ($page->title_ar ?: $page->title_en) : ($page->title_en ?: $page->title_ar);
            $seodesc = $isar ? ($page->seo_desc_ar ?: $page->seo_desc_en) : ($page->seo_desc_en ?: $page->seo_desc_ar);

            $embeddedurl = new \moodle_url('/local/nit_pages/page.php', [
                'p' => $primaryslug,
                'embedded' => 1,
                'lang' => $lang,
            ]);
            $weburl = new \moodle_url('/local/nit_pages/page.php', [
                'p' => $primaryslug,
                'lang' => $lang,
            ]);

            $contenthtml = page_manager::get_page_html($page, $lang);

            return [
                'id'           => (int) $page->id,
                'slug'         => $primaryslug,
                'slug_ar'      => $page->slug_ar,
                'slug_en'      => $page->slug_en,
                'title'        => $title,
                'title_ar'     => $page->title_ar,
                'title_en'     => $page->title_en,
                'seo_desc'     => $seodesc ?: '',
                'share_image'  => $page->share_image ? api::file_url($page->share_image) : '',
                'is_default'   => !empty($page->is_default),
                'default_key'  => $page->default_key ?? '',
                'updated_at'   => (int) $page->timemodified,
                'embedded_url' => api::file_url($embeddedurl->out(false)),
                'url'          => api::file_url($weburl->out(false)),
                'content_html' => $contenthtml,
            ];

        // Search and paginate published articles.
        case 'get_articles':
            [$pageidx, $perpage] = api::paging(9, 50);
            $search = optional_param('q', optional_param('search', '', PARAM_RAW), PARAM_RAW);

            $res = article_manager::get_articles($pageidx, $perpage, $search, !$isadmin);

            $articles = [];
            foreach ($res['articles'] as $art) {
                $arttitle = $isar ? ($art->title_ar ?: $art->title_en) : ($art->title_en ?: $art->title_ar);
                $artsummary = $isar ? ($art->summary_ar ?: $art->summary_en) : ($art->summary_en ?: $art->summary_ar);
                $covermoodleurl = article_manager::cover_url($art);
                $coverurl = $covermoodleurl ? api::file_url($covermoodleurl->out(false)) : '';
                $authorname = article_manager::get_author_name((int) $art->author_id);
                $arturl = new \moodle_url('/local/nit_pages/article.php', ['a' => $art->slug, 'lang' => $lang]);

                $articles[] = [
                    'id'           => (int) $art->id,
                    'slug'         => $art->slug,
                    'title'        => $arttitle,
                    'title_ar'     => $art->title_ar,
                    'title_en'     => $art->title_en,
                    'summary'      => $artsummary,
                    'summary_ar'   => $art->summary_ar,
                    'summary_en'   => $art->summary_en,
                    'cover_image'  => $coverurl,
                    'author_name'  => $authorname,
                    'status'       => $art->status,
                    'published_at' => (int) ($art->published_at ?? $art->timecreated),
                    'url'          => api::file_url($arturl->out(false)),
                ];
            }

            return [
                'articles' => $articles,
                'total'    => $res['total'],
                'page'     => $res['page'],
                'perpage'  => $res['perpage'],
                'pages'    => $res['pages'],
            ];

        // Detail of a single article.
        case 'get_article':
            $slug = optional_param('slug', optional_param('a', '', PARAM_RAW_TRIMMED), PARAM_RAW_TRIMMED);
            $id = optional_param('id', 0, PARAM_INT);

            $art = null;
            if ($id > 0) {
                $art = article_manager::get_article_by_id($id);
            } else if ($slug !== '') {
                if (is_numeric($slug) && (int) $slug > 0) {
                    $art = article_manager::get_article_by_id((int) $slug);
                } else {
                    $art = article_manager::get_article_by_slug($slug);
                }
            }

            if (!$art || !article_manager::can_view($art, $userid)) {
                api::fail('notfound', get_string('article_not_found', 'local_nit_pages'));
            }

            $arttitle = $isar ? ($art->title_ar ?: $art->title_en) : ($art->title_en ?: $art->title_ar);
            $artsummary = $isar ? ($art->summary_ar ?: $art->summary_en) : ($art->summary_en ?: $art->summary_ar);
            $artbody = $isar ? ($art->body_ar ?: $art->body_en) : ($art->body_en ?: $art->body_ar);
            if (trim($artbody) === '') {
                $artbody = $isar ? $art->body_en : $art->body_ar;
            }

            $covermoodleurl = article_manager::cover_url($art);
            $coverurl = $covermoodleurl ? api::file_url($covermoodleurl->out(false)) : '';
            $authorname = article_manager::get_author_name((int) $art->author_id);
            $arturl = new \moodle_url('/local/nit_pages/article.php', ['a' => $art->slug, 'lang' => $lang]);

            return [
                'id'           => (int) $art->id,
                'slug'         => $art->slug,
                'title'        => $arttitle,
                'title_ar'     => $art->title_ar,
                'title_en'     => $art->title_en,
                'summary'      => $artsummary,
                'summary_ar'   => $art->summary_ar,
                'summary_en'   => $art->summary_en,
                'body_html'    => format_text($artbody, FORMAT_HTML),
                'cover_image'  => $coverurl,
                'author_name'  => $authorname,
                'status'       => $art->status,
                'published_at' => (int) ($art->published_at ?? $art->timecreated),
                'url'          => api::file_url($arturl->out(false)),
            ];
    }

    return api::unknown();
});
