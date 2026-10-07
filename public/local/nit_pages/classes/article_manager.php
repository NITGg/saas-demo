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

namespace local_nit_pages;

defined('MOODLE_INTERNAL') || die();

/**
 * Article management service for local_nit_pages.
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class article_manager {

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT     = 'draft';

    /**
     * Get an article by its slug.
     *
     * @param string $slug
     * @return \stdClass|null
     */
    public static function get_article_by_slug(string $slug): ?\stdClass {
        global $DB;
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        return $DB->get_record('nit_articles', ['slug' => $slug]) ?: null;
    }

    /**
     * Get an article by its ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_article_by_id(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('nit_articles', ['id' => $id]) ?: null;
    }

    /**
     * Whether the given user can view this article.
     *
     * @param \stdClass $article
     * @param int|null $userid
     * @return bool
     */
    public static function can_view(\stdClass $article, ?int $userid = null): bool {
        global $USER;
        $uid = $userid ?? (int) $USER->id;

        if ($article->status === self::STATUS_DRAFT) {
            return has_capability('local/nit_pages:manage', \context_system::instance(), $uid);
        }

        // Future publish date is hidden from non-admins.
        if (!empty($article->published_at) && $article->published_at > time()) {
            return has_capability('local/nit_pages:manage', \context_system::instance(), $uid);
        }

        return true;
    }

    /**
     * Search and paginate articles.
     *
     * @param int $page zero-based page index
     * @param int $perpage items per page (default 9)
     * @param string $search query string
     * @param bool $onlypublished only published articles
     * @return array ['articles' => array, 'total' => int, 'page' => int, 'perpage' => int, 'pages' => int]
     */
    public static function get_articles(int $page = 0, int $perpage = 9, string $search = '', bool $onlypublished = true): array {
        global $DB;

        $params = [];
        $conditions = [];

        if ($onlypublished) {
            $conditions[] = 'status = :status AND published_at <= :now';
            $params['status'] = self::STATUS_PUBLISHED;
            $params['now'] = time();
        }

        $search = trim($search);
        if ($search !== '') {
            $conditions[] = '(' . $DB->sql_like('title_ar', ':q1', false) .
                            ' OR ' . $DB->sql_like('title_en', ':q2', false) .
                            ' OR ' . $DB->sql_like('summary_ar', ':q3', false) .
                            ' OR ' . $DB->sql_like('summary_en', ':q4', false) . ')';
            $params['q1'] = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
            $params['q3'] = '%' . $search . '%';
            $params['q4'] = '%' . $search . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $countsql = "SELECT COUNT(1) FROM {nit_articles} $where";
        $total = (int) $DB->count_records_sql($countsql, $params);

        $limitfrom = $page * $perpage;
        $sql = "SELECT * FROM {nit_articles} $where ORDER BY published_at DESC, id DESC";
        $records = $DB->get_records_sql($sql, $params, $limitfrom, $perpage);

        $totalpages = ($perpage > 0 && $total > 0) ? (int) ceil($total / $perpage) : 0;

        return [
            'articles' => array_values($records),
            'total'    => $total,
            'page'     => $page,
            'perpage'  => $perpage,
            'pages'    => $totalpages,
        ];
    }

    /**
     * Save (create or update) an article.
     *
     * @param array|\stdClass $data
     * @param int|null $userid
     * @return \stdClass
     */
    public static function save_article($data, ?int $userid = null): \stdClass {
        global $DB, $USER;
        $record = (object) $data;
        $now = time();

        $authorid = $userid ?: (!empty($record->author_id) ? (int) $record->author_id : (int) $USER->id);
        if ($authorid <= 0) {
            $authorid = (int) $USER->id;
        }

        $record->title_ar = trim((string) ($record->title_ar ?? ''));
        $record->title_en = trim((string) ($record->title_en ?? ''));
        $record->summary_ar = trim((string) ($record->summary_ar ?? ''));
        $record->summary_en = trim((string) ($record->summary_en ?? ''));
        $record->body_ar = trim((string) ($record->body_ar ?? ''));
        $record->body_en = trim((string) ($record->body_en ?? ''));
        $record->cover_image = trim((string) ($record->cover_image ?? ''));
        $record->status = in_array($record->status ?? '', [self::STATUS_PUBLISHED, self::STATUS_DRAFT], true)
            ? $record->status : self::STATUS_DRAFT;

        if (!empty($record->published_at)) {
            $record->published_at = (int) $record->published_at;
        } else if ($record->status === self::STATUS_PUBLISHED) {
            $record->published_at = $now;
        } else {
            $record->published_at = null;
        }

        $existingid = !empty($record->id) ? (int) $record->id : 0;
        $base = !empty($record->slug) ? $record->slug : ($record->title_en ?: $record->title_ar);
        $record->slug = self::unique_slug($base, $existingid);

        $record->timemodified = $now;

        if ($existingid > 0) {
            $DB->update_record('nit_articles', $record);
            return $DB->get_record('nit_articles', ['id' => $existingid]);
        }

        $record->author_id = $authorid;
        $record->timecreated = $now;
        $record->id = $DB->insert_record('nit_articles', $record);

        return $DB->get_record('nit_articles', ['id' => $record->id]);
    }

    /**
     * Delete an article.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_article(int $id): bool {
        global $DB;
        $fs = get_file_storage();
        $sysctx = \context_system::instance();
        $fs->delete_area_files($sysctx->id, 'local_nit_pages', 'article_cover', $id);
        return $DB->delete_records('nit_articles', ['id' => $id]);
    }

    /**
     * Toggle article status between published and draft.
     *
     * @param int $id
     * @return string new status
     */
    public static function toggle_status(int $id): string {
        global $DB;
        $article = self::get_article_by_id($id);
        if (!$article) {
            return self::STATUS_DRAFT;
        }

        $now = time();
        if ($article->status === self::STATUS_PUBLISHED) {
            $newstatus = self::STATUS_DRAFT;
            $pubdate = $article->published_at;
        } else {
            $newstatus = self::STATUS_PUBLISHED;
            $pubdate = !empty($article->published_at) ? $article->published_at : $now;
        }

        $DB->update_record('nit_articles', (object) [
            'id'           => $article->id,
            'status'       => $newstatus,
            'published_at' => $pubdate,
            'timemodified' => $now,
        ]);

        return $newstatus;
    }

    /**
     * Generate a unique slug for an article.
     *
     * @param string $text
     * @param int $ignoreid article id to ignore on edit
     * @return string
     */
    public static function unique_slug(string $text, int $ignoreid = 0): string {
        global $DB;
        $slug = page_manager::clean_slug($text);
        if ($slug === '') {
            $slug = 'article';
        }

        $base = $slug;
        $counter = 1;
        while (true) {
            $params = ['slug' => $slug];
            $sql = 'slug = :slug';
            if ($ignoreid > 0) {
                $sql .= ' AND id != :id';
                $params['id'] = $ignoreid;
            }
            if (!$DB->record_exists_select('nit_articles', $sql, $params)) {
                return $slug;
            }
            $counter++;
            $slug = $base . '-' . $counter;
        }
    }

    /**
     * Get the formatted author name for an article.
     *
     * @param int $authorid
     * @return string
     */
    public static function get_author_name(int $authorid): string {
        global $DB;
        if ($authorid <= 0) {
            return get_string('platform_team', 'local_nit_pages');
        }
        $author = $DB->get_record('user', ['id' => $authorid], 'id, firstname, lastname');
        return $author ? fullname($author) : get_string('platform_team', 'local_nit_pages');
    }

    public static function get_by_id(int $id): ?\stdClass {
        return self::get_article_by_id($id);
    }

    public static function delete(int $id): bool {
        return self::delete_article($id);
    }

    public static function save($data, ?int $userid = null): \stdClass {
        return self::save_article($data, $userid);
    }

    public static function url(string $slug): \moodle_url {
        return new \moodle_url('/local/nit_pages/article.php', ['a' => $slug]);
    }

    public static function cover_url($articleorid): ?\moodle_url {
        if (is_numeric($articleorid)) {
            $article = self::get_article_by_id((int)$articleorid);
        } else {
            $article = $articleorid;
        }
        if (!$article) {
            return null;
        }
        if (!empty($article->cover_image) && preg_match('!^https?://!', $article->cover_image)) {
            return new \moodle_url($article->cover_image);
        }
        $fs = get_file_storage();
        $sysctx = \context_system::instance();
        $files = $fs->get_area_files($sysctx->id, 'local_nit_pages', 'article_cover', $article->id, 'id DESC', false);
        if ($files) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url($sysctx->id, 'local_nit_pages', 'article_cover', $article->id, '/', $file->get_filename());
        }
        if (!empty($article->cover_image)) {
            return new \moodle_url($article->cover_image);
        }
        return null;
    }

    public static function list_articles(array $filters = []): array {
        global $DB;
        $status = $filters['status'] ?? '';
        $search = trim($filters['search'] ?? '');
        $page = (int) ($filters['page'] ?? 0);
        $perpage = (int) ($filters['perpage'] ?? 20);

        $conditions = [];
        $params = [];

        if ($status !== '') {
            $conditions[] = 'a.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $conditions[] = '(' . $DB->sql_like('a.title_ar', ':q1', false) .
                            ' OR ' . $DB->sql_like('a.title_en', ':q2', false) .
                            ' OR ' . $DB->sql_like('a.summary_ar', ':q3', false) .
                            ' OR ' . $DB->sql_like('a.summary_en', ':q4', false) . ')';
            $params['q1'] = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
            $params['q3'] = '%' . $search . '%';
            $params['q4'] = '%' . $search . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {nit_articles} a $where", $params);

        $sql = "SELECT a.*, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS author_name
                  FROM {nit_articles} a
             LEFT JOIN {user} u ON u.id = a.author_id
                $where
              ORDER BY a.published_at DESC, a.id DESC";

        $limitfrom = $page * $perpage;
        $records = $DB->get_records_sql($sql, $params, $limitfrom, $perpage);

        return [
            'articles' => array_values($records),
            'total'    => $total,
            'page'     => $page,
            'perpage'  => $perpage,
        ];
    }
}
