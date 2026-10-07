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
 * Page management service for local_nit_pages.
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_manager {

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT     = 'draft';

    /**
     * Get a page by its Arabic or English slug, or its default key.
     *
     * @param string $slug
     * @return \stdClass|null
     */
    public static function get_page_by_slug(string $slug): ?\stdClass {
        global $DB;
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $sql = "SELECT * FROM {nit_pages}
                 WHERE slug_ar = :slug1
                    OR slug_en = :slug2
                    OR default_key = :slug3";
        $records = $DB->get_records_sql($sql, [
            'slug1' => $slug,
            'slug2' => $slug,
            'slug3' => $slug,
        ], 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Get a default page by its default key (about, contact, terms, privacy, faq, articles).
     *
     * @param string $key
     * @return \stdClass|null
     */
    public static function get_page_by_default_key(string $key): ?\stdClass {
        global $DB;
        return $DB->get_record('nit_pages', ['default_key' => $key]) ?: null;
    }

    /**
     * Get a page by its ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_page_by_id(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('nit_pages', ['id' => $id]) ?: null;
    }

    /**
     * Check if a given user can view the page.
     *
     * @param \stdClass $page
     * @param int|null $userid
     * @return bool
     */
    public static function can_view(\stdClass $page, ?int $userid = null): bool {
        global $USER;
        $uid = $userid ?? (int) $USER->id;

        // Drafts are visible only to admins/managers with manage capability.
        if ($page->status === self::STATUS_DRAFT) {
            return has_capability('local/nit_pages:manage', \context_system::instance(), $uid);
        }

        // Logged-in only pages.
        if (!empty($page->loggedin_only)) {
            return $uid > 0 && !isguestuser($uid);
        }

        // Published public pages.
        return true;
    }

    /**
     * Get all pages for management or API.
     *
     * @param bool $onlypublished
     * @param bool $onlypublic (exclude loggedin_only)
     * @return array
     */
    public static function get_all_pages(bool $onlypublished = false, bool $onlypublic = false): array {
        global $DB;
        $params = [];
        $conditions = [];

        if ($onlypublished) {
            $conditions[] = 'status = :status';
            $params['status'] = self::STATUS_PUBLISHED;
        }
        if ($onlypublic) {
            $conditions[] = 'loggedin_only = 0';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return $DB->get_records_sql("SELECT * FROM {nit_pages} $where ORDER BY is_default DESC, id ASC", $params);
    }

    /**
     * Save (create or update) a page.
     *
     * @param array|\stdClass $data
     * @return \stdClass the saved page
     */
    public static function save_page($data): \stdClass {
        global $DB;
        $record = (object) $data;
        $now = time();

        $record->slug_ar = self::clean_slug($record->slug_ar ?? '');
        $record->slug_en = self::clean_slug($record->slug_en ?? '');
        $record->title_ar = trim((string) ($record->title_ar ?? ''));
        $record->title_en = trim((string) ($record->title_en ?? ''));
        $record->seo_desc_ar = trim((string) ($record->seo_desc_ar ?? ''));
        $record->seo_desc_en = trim((string) ($record->seo_desc_en ?? ''));
        $record->share_image = trim((string) ($record->share_image ?? ''));
        $record->status = in_array($record->status ?? '', [self::STATUS_PUBLISHED, self::STATUS_DRAFT], true)
            ? $record->status : self::STATUS_PUBLISHED;
        $record->loggedin_only = !empty($record->loggedin_only) ? 1 : 0;
        $record->timemodified = $now;

        if (!empty($record->id)) {
            $DB->update_record('nit_pages', $record);
            return $DB->get_record('nit_pages', ['id' => $record->id]);
        }

        $record->timecreated = $now;
        $record->is_default = !empty($record->is_default) ? 1 : 0;
        $record->default_key = $record->default_key ?? null;
        $record->id = $DB->insert_record('nit_pages', $record);

        return $DB->get_record('nit_pages', ['id' => $record->id]);
    }

    /**
     * Delete a page. Default pages cannot be deleted.
     *
     * @param int $id
     * @return bool
     * @throws \moodle_exception if page is a default page
     */
    public static function delete_page(int $id): bool {
        global $DB;
        $page = self::get_page_by_id($id);
        if (!$page) {
            return false;
        }

        if (!empty($page->is_default)) {
            throw new \moodle_exception('err_cannot_delete_default', 'local_nit_pages');
        }

        // Delete associated block instances scoped to this subpage.
        $subpage = (string) $page->id;
        $blocks = $DB->get_records('block_instances', [
            'pagetypepattern' => 'local-nit_pages-page',
            'subpagepattern'  => $subpage,
        ]);
        foreach ($blocks as $b) {
            blocks_delete_instance($b);
        }

        return $DB->delete_records('nit_pages', ['id' => $page->id]);
    }

    /**
     * Toggle published / draft status.
     *
     * @param int $id
     * @return string new status
     */
    public static function toggle_status(int $id): string {
        global $DB;
        $page = self::get_page_by_id($id);
        if (!$page) {
            return self::STATUS_DRAFT;
        }

        $newstatus = ($page->status === self::STATUS_PUBLISHED) ? self::STATUS_DRAFT : self::STATUS_PUBLISHED;
        $DB->update_record('nit_pages', (object) [
            'id'           => $page->id,
            'status'       => $newstatus,
            'timemodified' => time(),
        ]);
        return $newstatus;
    }

    /**
     * Clean and format a slug.
     *
     * @param string $slug
     * @return string
     */
    public static function clean_slug(string $slug): string {
        $slug = trim($slug);
        $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Get the resolved HTML content of a page by rendering its nit_section blocks.
     *
     * @param \stdClass $page
     * @param string $lang optional language code ('ar' or 'en')
     * @return string rendered HTML
     */
    public static function get_page_html(\stdClass $page, string $lang = ''): string {
        global $DB;
        if ($lang !== '') {
            $prevlang = current_language();
            force_current_language($lang);
        }

        $blocks = $DB->get_records('block_instances', [
            'pagetypepattern' => 'local-nit_pages-page',
            'subpagepattern'  => (string) $page->id,
        ], 'defaultweight ASC, id ASC');

        $htmlparts = [];
        foreach ($blocks as $bi) {
            if ($bi->blockname === 'nit_section') {
                $cfg = !empty($bi->configdata) ? unserialize(base64_decode($bi->configdata)) : new \stdClass();
                $mode = $cfg->mode ?? 'legacy';
                if ($mode === 'html') {
                    $raw = $cfg->htmltext ?? '';
                } else if ($mode === 'visual') {
                    $raw = $cfg->visualtext ?? '';
                } else {
                    $raw = $cfg->text ?? '';
                }
                if (trim($raw) !== '') {
                    $rel = self::site_relative_urls($raw);
                    $htmlparts[] = format_text($rel, FORMAT_HTML, ['filter' => true, 'noclean' => true]);
                }
            }
        }

        if ($lang !== '' && isset($prevlang)) {
            force_current_language($prevlang);
        }

        return implode("\n", $htmlparts);
    }

    /**
     * Point root-relative URLs at this site when Moodle lives in a sub-folder.
     *
     * @param string $text
     * @return string
     */
    public static function site_relative_urls(string $text): string {
        global $CFG;
        if (class_exists('\block_nit_section', false)) {
            return \block_nit_section::site_relative_urls($text);
        }
        $path = rtrim((string) parse_url($CFG->wwwroot, PHP_URL_PATH), '/');
        if ($path === '' || $text === '') {
            return $text;
        }
        $notours = '(?!/|' . preg_quote(ltrim($path, '/'), '~') . '(?:/|["\')?#]))';
        return preg_replace(
            '~(\b(?:src|href|action|poster|data-src)\s*=\s*["\']|\bfetch\(\s*["\']|\burl\(\s*["\']?)/' . $notours . '~i',
            '$1' . $path . '/',
            $text
        );
    }

    /**
     * Ensure all 6 default pages and their starter nit_section blocks exist.
     * Called during install and upgrade.
     */
    public static function ensure_default_pages(): void {
        global $DB, $CFG;

        $defaults = [
            'about' => [
                'slug_ar'     => 'عن-المنصة',
                'slug_en'     => 'about',
                'title_ar'    => 'عن المنصة',
                'title_en'    => 'About Us',
                'seo_desc_ar' => 'تعرف على منصة بسطتهالك ورؤيتنا في تبسيط التعليم وتهيئة الطلاب للتفوق.',
                'seo_desc_en' => 'Learn about Bassthalk platform and our mission to simplify education.',
            ],
            'contact' => [
                'slug_ar'     => 'تواصل-معنا',
                'slug_en'     => 'contact',
                'title_ar'    => 'تواصل معنا',
                'title_en'    => 'Contact Us',
                'seo_desc_ar' => 'تواصل مع فريق منصة بسطتهالك للاستفسارات والدعم الفني.',
                'seo_desc_en' => 'Get in touch with Bassthalk platform support and inquiries.',
            ],
            'terms' => [
                'slug_ar'     => 'الشروط-والأحكام',
                'slug_en'     => 'terms',
                'title_ar'    => 'الشروط والأحكام',
                'title_en'    => 'Terms and Conditions',
                'seo_desc_ar' => 'شروط وأحكام استخدام منصة بسطتهالك والخدمات التعليمية المقدمة.',
                'seo_desc_en' => 'Terms of service and conditions for using Bassthalk platform.',
            ],
            'privacy' => [
                'slug_ar'     => 'سياسة-الخصوصية',
                'slug_en'     => 'privacy',
                'title_ar'    => 'سياسة الخصوصية',
                'title_en'    => 'Privacy Policy',
                'seo_desc_ar' => 'سياسة الخصوصية وحماية بيانات المستخدمين في منصة بسطتهالك.',
                'seo_desc_en' => 'Privacy policy and user data protection on Bassthalk platform.',
            ],
            'faq' => [
                'slug_ar'     => 'الأسئلة-الشائعة',
                'slug_en'     => 'faq',
                'title_ar'    => 'الأسئلة الشائعة',
                'title_en'    => 'FAQ',
                'seo_desc_ar' => 'إجابات على أكثر الأسئلة شيوعاً حول الدورات والاشتراكات والمنصة.',
                'seo_desc_en' => 'Frequently asked questions about courses, plans, and platform.',
            ],
            'articles' => [
                'slug_ar'     => 'المقالات',
                'slug_en'     => 'articles',
                'title_ar'    => 'المقالات',
                'title_en'    => 'Articles',
                'seo_desc_ar' => 'أحدث المقالات والنصائح التعليمية والتربوية من نخبة المعلمين.',
                'seo_desc_en' => 'Latest educational articles, guides and study tips.',
            ],
        ];

        $now = time();
        $sysctx = \context_system::instance();

        foreach ($defaults as $key => $def) {
            $existing = $DB->get_record('nit_pages', ['default_key' => $key]);
            if (!$existing) {
                $pageid = $DB->insert_record('nit_pages', (object) [
                    'slug_ar'       => $def['slug_ar'],
                    'slug_en'       => $def['slug_en'],
                    'title_ar'      => $def['title_ar'],
                    'title_en'      => $def['title_en'],
                    'seo_desc_ar'   => $def['seo_desc_ar'],
                    'seo_desc_en'   => $def['seo_desc_en'],
                    'share_image'   => '',
                    'status'        => self::STATUS_PUBLISHED,
                    'is_default'    => 1,
                    'default_key'   => $key,
                    'loggedin_only' => 0,
                    'timecreated'   => $now,
                    'timemodified'  => $now,
                ]);
            } else {
                $pageid = $existing->id;
                // Ensure default key and is_default are set.
                if (empty($existing->is_default) || $existing->default_key !== $key) {
                    $DB->update_record('nit_pages', (object) [
                        'id'          => $pageid,
                        'is_default'  => 1,
                        'default_key' => $key,
                    ]);
                }
            }

            // Check if a block already exists for this page.
            $subpage = (string) $pageid;
            $hasblock = $DB->record_exists('block_instances', [
                'pagetypepattern' => 'local-nit_pages-page',
                'subpagepattern'  => $subpage,
                'blockname'       => 'nit_section',
            ]);

            if (!$hasblock) {
                $html = self::generate_starter_html($key);
                $config = new \stdClass();
                $config->mode = 'html';
                $config->htmltext = $html;
                $config->width = 'full';
                $config->alignment = 'stretch';
                $config->plain = 1;
                $config->showtitle = 0;

                $bi = (object) [
                    'blockname'         => 'nit_section',
                    'parentcontextid'   => $sysctx->id,
                    'showinsubcontexts' => 0,
                    'pagetypepattern'   => 'local-nit_pages-page',
                    'subpagepattern'    => $subpage,
                    'defaultregion'     => 'fullwidth-top',
                    'defaultweight'     => 0,
                    'configdata'        => base64_encode(serialize($config)),
                    'timecreated'       => $now,
                    'timemodified'      => $now,
                ];
                $DB->insert_record('block_instances', $bi);
            }
        }
    }

    /**
     * Generate bassthalk-styled starter HTML for each default page.
     *
     * @param string $key
     * @return string
     */
    /**
     * Generate bassthalk-styled starter HTML for each default page.
     *
     * @param string $key
     * @return string
     */
    public static function generate_starter_html(string $key): string {
        global $CFG;
        $supportemail = trim((string) ($CFG->supportemail ?? 'hello@bassthalk.com'));
        if ($supportemail === '') {
            $supportemail = 'hello@bassthalk.com';
        }

        switch ($key) {
            case 'about':
                return <<<HTML
<div dir="auto" class="nit-page-section nit-page-about" style="background:#ffffff;color:#0f172a;padding:clamp(56px,7vw,96px) 20px;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="max-width:1160px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:56px;">
      <span style="display:inline-block;background:#eff6ff;color:#1b75d0;border:1px solid #bfdbfe;border-radius:50px;padding:6px 20px;font-size:14px;font-weight:700;margin-bottom:16px;">
        {mlang en}About Bassthalk{mlang}{mlang ar}عن منصة بسطتهالك{mlang}
      </span>
      <h1 style="font-size:clamp(28px,3.5vw,46px);font-weight:800;margin:0 0 16px;line-height:1.35;color:#0f172a!important;">
        {mlang en}Simplifying learning for secondary school and beyond{mlang}{mlang ar}تهيئة الطالب لكامل جوانب الثانوية العامة وما بعدها{mlang}
      </h1>
      <p style="font-size:17px;line-height:1.85;color:#475569!important;max-width:760px;margin:0 auto;">
        {mlang en}Bassthalk platform is designed to make education accessible, engaging and straightforward. We bring top teachers, structured lesson paths, and comprehensive practice tests together in one seamless platform.{mlang}{mlang ar}تم بناء منصة بسطتهالك لتكون وجهتك الأولى نحو التفوق الدراسي. نجمع بين نخبة المدرسين، والمناهج المنظمة، والمتابعة الدقيقة لمساعدة كل طالب على استيعاب المواد وتحقيق أعلى النتائج بأبسط الطرق.{mlang}
      </p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:28px;margin-top:40px;">
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:32px 28px;box-shadow:0 10px 30px -5px rgba(0,0,0,0.05);text-align:start;">
        <div style="width:52px;height:52px;border-radius:14px;background:#eff6ff;color:#1b75d0;display:grid;place-items:center;font-size:24px;margin-bottom:20px;border:1px solid #dbeafe;">🎯</div>
        <h3 style="font-size:20px;font-weight:700;color:#0f172a!important;margin:0 0 12px;font-family:'Tajawal',sans-serif;">{mlang en}Our Vision{mlang}{mlang ar}رؤيتنا{mlang}</h3>
        <p style="font-size:15px;line-height:1.8;color:#64748b!important;margin:0;">{mlang en}Providing the highest quality online education accessible to every student across Egypt and the Arab world.{mlang}{mlang ar}أن يستطيع الدارس الحصول على أفضل خدمات تعليمية عبر الإنترنت بجودة عالية وتجربة تفاعلية متكاملة.{mlang}</p>
      </div>

      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:32px 28px;box-shadow:0 10px 30px -5px rgba(0,0,0,0.05);text-align:start;">
        <div style="width:52px;height:52px;border-radius:14px;background:#eff6ff;color:#1b75d0;display:grid;place-items:center;font-size:24px;margin-bottom:20px;border:1px solid #dbeafe;">🚀</div>
        <h3 style="font-size:20px;font-weight:700;color:#0f172a!important;margin:0 0 12px;font-family:'Tajawal',sans-serif;">{mlang en}Our Mission{mlang}{mlang ar}رسالتنا{mlang}</h3>
        <p style="font-size:15px;line-height:1.8;color:#64748b!important;margin:0;">{mlang en}Simplifying complex subjects through engaging, structured learning paths and continuous assessment.{mlang}{mlang ar}تبسيط المناهج المعقدة وشرحها بأسلوب مشوق يربط الفهم بالتطبيق العملي وحل الأسئلة والامتحانات.{mlang}</p>
      </div>

      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:32px 28px;box-shadow:0 10px 30px -5px rgba(0,0,0,0.05);text-align:start;">
        <div style="width:52px;height:52px;border-radius:14px;background:#eff6ff;color:#1b75d0;display:grid;place-items:center;font-size:24px;margin-bottom:20px;border:1px solid #dbeafe;">💬</div>
        <h3 style="font-size:20px;font-weight:700;color:#0f172a!important;margin:0 0 12px;font-family:'Tajawal',sans-serif;">{mlang en}Our Goal{mlang}{mlang ar}هدفنا{mlang}</h3>
        <p style="font-size:15px;line-height:1.8;color:#64748b!important;margin:0;">{mlang en}Empowering students to achieve academic excellence and build confidence for their future university careers.{mlang}{mlang ar}مساعدة الطلاب وأولياء الأمور على تخطي ضغوط الثانوية العامة بثقة وتفوق والوصول إلى كليات أحلامهم.{mlang}</p>
      </div>
    </div>
  </div>
</div>
HTML;

            case 'contact':
                return <<<HTML
<div dir="auto" class="nit-page-section nit-page-contact" style="background:#ffffff;color:#0f172a;padding:clamp(56px,7vw,96px) 20px;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="max-width:1160px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:56px;">
      <span style="display:inline-block;background:#eff6ff;color:#1b75d0;border:1px solid #bfdbfe;border-radius:50px;padding:6px 20px;font-size:14px;font-weight:700;margin-bottom:16px;">
        {mlang en}Contact Support{mlang}{mlang ar}فريق الدعم والتواصل{mlang}
      </span>
      <h1 style="font-size:clamp(28px,3.5vw,46px);font-weight:800;margin:0 0 16px;line-height:1.35;color:#0f172a!important;">
        {mlang en}We are here to support you{mlang}{mlang ar}تواصل معنا في أي وقت{mlang}
      </h1>
      <p style="font-size:17px;line-height:1.85;color:#475569!important;max-width:720px;margin:0 auto;">
        {mlang en}Have questions about course registration, subscriptions, or payments? Contact our team anytime through the channels below.{mlang}{mlang ar}لديك سؤال حول التسجيل، باقات الدروس أو المدفوعات؟ فريق الدعم متاح للإجابة على كل استفساراتك.{mlang}
      </p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:24px;margin-bottom:48px;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:28px 24px;text-align:center;">
        <div style="width:52px;height:52px;border-radius:14px;background:#eff6ff;color:#1b75d0;display:grid;place-items:center;font-size:24px;margin:0 auto 16px;">✉️</div>
        <h4 style="font-size:17px;font-weight:700;color:#0f172a!important;margin:0 0 8px;">{mlang en}Email Support{mlang}{mlang ar}البريد الإلكتروني{mlang}</h4>
        <a href="mailto:{$supportemail}" style="color:#1b75d0;text-decoration:none;font-weight:600;font-size:15px;">{$supportemail}</a>
      </div>

      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:28px 24px;text-align:center;">
        <div style="width:52px;height:52px;border-radius:14px;background:#f0fdf4;color:#16a34a;display:grid;place-items:center;font-size:24px;margin:0 auto 16px;">💬</div>
        <h4 style="font-size:17px;font-weight:700;color:#0f172a!important;margin:0 0 8px;">{mlang en}WhatsApp{mlang}{mlang ar}واتساب الدعم{mlang}</h4>
        <span style="color:#0f172a;font-weight:600;font-size:15px;direction:ltr;display:inline-block;">+20 100 000 0000</span>
      </div>

      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:28px 24px;text-align:center;">
        <div style="width:52px;height:52px;border-radius:14px;background:#fff7ed;color:#ea580c;display:grid;place-items:center;font-size:24px;margin:0 auto 16px;">⏰</div>
        <h4 style="font-size:17px;font-weight:700;color:#0f172a!important;margin:0 0 8px;">{mlang en}Working Hours{mlang}{mlang ar}أوقات العمل{mlang}</h4>
        <span style="color:#475569;font-size:14px;">{mlang en}Saturday - Thursday: 9 AM - 9 PM{mlang}{mlang ar}السبت - الخميس: ٩ ص - ٩ م{mlang}</span>
      </div>

      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:28px 24px;text-align:center;">
        <div style="width:52px;height:52px;border-radius:14px;background:#faf5ff;color:#9333ea;display:grid;place-items:center;font-size:24px;margin:0 auto 16px;">📍</div>
        <h4 style="font-size:17px;font-weight:700;color:#0f172a!important;margin:0 0 8px;">{mlang en}Location{mlang}{mlang ar}المقر الرئيسي{mlang}</h4>
        <span style="color:#475569;font-size:14px;">{mlang en}Cairo, Egypt{mlang}{mlang ar}القاهرة، مصر{mlang}</span>
      </div>
    </div>
  </div>
</div>
HTML;

            case 'terms':
                require_once($CFG->dirroot . '/local/multitopics/legal.php');
                $cfg_terms_en = get_config('local_multitopics', 'terms_en');
                $cfg_terms_ar = get_config('local_multitopics', 'terms_ar');
                $body_en = ($cfg_terms_en !== false && trim((string) $cfg_terms_en) !== '') ? (string) $cfg_terms_en :
                    local_multitopics_legal_default('terms', false, 'Bassthalk', $supportemail);
                $body_ar = ($cfg_terms_ar !== false && trim((string) $cfg_terms_ar) !== '') ? (string) $cfg_terms_ar :
                    local_multitopics_legal_default('terms', true, 'بسطتهالك', $supportemail);

                return <<<HTML
<div dir="auto" class="nit-page-section nit-page-legal" style="background:#f8fafc;color:#0f172a;padding:clamp(56px,7vw,96px) 20px;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="max-width:920px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:clamp(32px,5vw,56px);box-shadow:0 10px 40px -10px rgba(0,0,0,0.05);">
    <h1 style="font-size:clamp(26px,3vw,38px);font-weight:800;color:#0f172a!important;margin:0 0 12px;font-family:'Tajawal',sans-serif;">
      {mlang en}Terms and Conditions{mlang}{mlang ar}الشروط والأحكام{mlang}
    </h1>
    <div style="display:inline-block;color:#1b75d0;background:#eff6ff;border:1px solid #dbeafe;padding:4px 14px;border-radius:9999px;font-size:13px;font-weight:600;margin-bottom:28px;">
      {mlang en}Last updated: 2026{mlang}{mlang ar}آخر تحديث: ٢٠٢٦{mlang}
    </div>
    <div class="nit-legal-body" style="line-height:1.85;color:#334155!important;font-size:16px;">
      {mlang en}{$body_en}{mlang}
      {mlang ar}{$body_ar}{mlang}
    </div>
  </div>
</div>
HTML;

            case 'privacy':
                require_once($CFG->dirroot . '/local/multitopics/legal.php');
                $cfg_privacy_en = get_config('local_multitopics', 'privacy_en');
                $cfg_privacy_ar = get_config('local_multitopics', 'privacy_ar');
                $body_en = ($cfg_privacy_en !== false && trim((string) $cfg_privacy_en) !== '') ? (string) $cfg_privacy_en :
                    local_multitopics_legal_default('privacy', false, 'Bassthalk', $supportemail);
                $body_ar = ($cfg_privacy_ar !== false && trim((string) $cfg_privacy_ar) !== '') ? (string) $cfg_privacy_ar :
                    local_multitopics_legal_default('privacy', true, 'بسطتهالك', $supportemail);

                return <<<HTML
<div dir="auto" class="nit-page-section nit-page-legal" style="background:#f8fafc;color:#0f172a;padding:clamp(56px,7vw,96px) 20px;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="max-width:920px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:clamp(32px,5vw,56px);box-shadow:0 10px 40px -10px rgba(0,0,0,0.05);">
    <h1 style="font-size:clamp(26px,3vw,38px);font-weight:800;color:#0f172a!important;margin:0 0 12px;font-family:'Tajawal',sans-serif;">
      {mlang en}Privacy Policy{mlang}{mlang ar}سياسة الخصوصية{mlang}
    </h1>
    <div style="display:inline-block;color:#1b75d0;background:#eff6ff;border:1px solid #dbeafe;padding:4px 14px;border-radius:9999px;font-size:13px;font-weight:600;margin-bottom:28px;">
      {mlang en}Last updated: 2026{mlang}{mlang ar}آخر تحديث: ٢٠٢٦{mlang}
    </div>
    <div class="nit-legal-body" style="line-height:1.85;color:#334155!important;font-size:16px;">
      {mlang en}{$body_en}{mlang}
      {mlang ar}{$body_ar}{mlang}
    </div>
  </div>
</div>
HTML;

            case 'faq':
                return <<<HTML
<div dir="auto" class="nit-page-section nit-page-faq" style="background:#ffffff;color:#0f172a;padding:clamp(56px,7vw,96px) 20px;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="max-width:920px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:52px;">
      <span style="display:inline-block;background:#eff6ff;color:#1b75d0;border:1px solid #bfdbfe;border-radius:50px;padding:6px 20px;font-size:14px;font-weight:700;margin-bottom:16px;">
        {mlang en}Common Questions{mlang}{mlang ar}الأسئلة الشائعة{mlang}
      </span>
      <h1 style="font-size:clamp(28px,3.5vw,44px);font-weight:800;color:#0f172a!important;margin:0 0 14px;">
        {mlang en}Frequently Asked Questions{mlang}{mlang ar}كل ما تريد معرفته عن المنصة{mlang}
      </h1>
      <p style="font-size:16px;line-height:1.8;color:#475569!important;margin:0;">
        {mlang en}Find answers to the most common questions about courses, payments, and subscriptions.{mlang}{mlang ar}إجابات على أكثر الأسئلة شيوعاً حول الدورات، طرق الدفع، ونظام المنصة.{mlang}
      </p>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
      <details style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 26px;box-shadow:0 4px 12px rgba(0,0,0,0.03);" open>
        <summary style="font-size:17px;font-weight:700;color:#0f172a!important;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;">
          <span>{mlang en}How do I enroll in a course or package?{mlang}{mlang ar}كيف يمكنني الاشتراك في الكورسات أو الباقات؟{mlang}</span>
          <span style="color:#1b75d0;font-size:18px;">▾</span>
        </summary>
        <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#475569!important;border-top:1px solid #f1f5f9;padding-top:14px;">
          {mlang en}You can create a free account, browse the course catalog, and click enroll. You can pay using your wallet, credit card, or supported local payment methods.{mlang}{mlang ar}يمكنك إنشاء حساب مجاني، ثم اختيار الدورة أو الباقة والضغط على زر الشراء أو الاشتراك. يتوفر الدفع عبر البطاقات البنكية، المحافظ الإلكترونية، أو فوري.{mlang}
        </p>
      </details>

      <details style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 26px;box-shadow:0 4px 12px rgba(0,0,0,0.03);">
        <summary style="font-size:17px;font-weight:700;color:#0f172a!important;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;">
          <span>{mlang en}Can I watch videos on multiple devices?{mlang}{mlang ar}هل يمكنني فتح حسابي ومشاهدة الفيديوهات من أكثر من جهاز؟{mlang}</span>
          <span style="color:#1b75d0;font-size:18px;">▾</span>
        </summary>
        <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#475569!important;border-top:1px solid #f1f5f9;padding-top:14px;">
          {mlang en}Yes, you can access your account from your computer or our mobile app. However, sharing accounts is protected by security limits to safeguard your account.{mlang}{mlang ar}يمكنك استخدام الحساب من حاسوبك أو من تطبيق الموبايل، وتطبق المنصة نظام حماية لمنع مشاركة الحساب مع أطراف أخرى للحفاظ على بياناتك.{mlang}
        </p>
      </details>

      <details style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 26px;box-shadow:0 4px 12px rgba(0,0,0,0.03);">
        <summary style="font-size:17px;font-weight:700;color:#0f172a!important;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;">
          <span>{mlang en}How long do I keep access to purchased lessons?{mlang}{mlang ar}ما هي مدة صلاحية الوصول للدروس والكورسات؟{mlang}</span>
          <span style="color:#1b75d0;font-size:18px;">▾</span>
        </summary>
        <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#475569!important;border-top:1px solid #f1f5f9;padding-top:14px;">
          {mlang en}Course purchases remain accessible until the end of the academic year, allowing you to re-watch and revise lessons at your own pace.{mlang}{mlang ar}تظل الكورسات المشتركة متاحة طوال العام الدراسي حتى انتهاء فترة الامتحانات، مما يتيح لك مراجعة الحصص في أي وقت.{mlang}
        </p>
      </details>

      <details style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 26px;box-shadow:0 4px 12px rgba(0,0,0,0.03);">
        <summary style="font-size:17px;font-weight:700;color:#0f172a!important;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;">
          <span>{mlang en}What if I need help or have a question during study?{mlang}{mlang ar}ماذا أفعل إذا واجهت مشكلة أو سؤالاً أثناء المذاكرة؟{mlang}</span>
          <span style="color:#1b75d0;font-size:18px;">▾</span>
        </summary>
        <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#475569!important;border-top:1px solid #f1f5f9;padding-top:14px;">
          {mlang en}Every course includes interactive quizzes, downloadable PDFs, and direct contact with teachers and technical support.{mlang}{mlang ar}يوجد تحت كل درس مساحة لطرح الأسئلة ومتابعة من المدرس والمساعدين، بالإضافة لفريق الدعم الفني المتاح لمساعدتك.{mlang}
        </p>
      </details>
    </div>
  </div>
</div>
HTML;

            case 'articles':
                return <<<HTML
<div dir="auto" class="nit-page-section nit-page-articles-hero" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#0f172a;padding:clamp(48px,6vw,72px) 20px;text-align:center;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="max-width:880px;margin:0 auto;">
    <span style="display:inline-block;background:#eff6ff;color:#1b75d0;border:1px solid #bfdbfe;border-radius:50px;padding:6px 20px;font-size:14px;font-weight:700;margin-bottom:16px;">
      {mlang en}Knowledge Hub{mlang}{mlang ar}مدونة بسطتهالك{mlang}
    </span>
    <h1 style="font-size:clamp(28px,3.5vw,44px);font-weight:800;margin:0 0 16px;line-height:1.35;color:#0f172a!important;">
      {mlang en}Educational Articles & Exam Tips{mlang}{mlang ar}المقالات والنصائح التعليمية{mlang}
    </h1>
    <p style="font-size:17px;line-height:1.8;color:#475569!important;margin:0 auto;max-width:620px;">
      {mlang en}Read tips and advice from expert teachers to help you excel in your studies.{mlang}{mlang ar}اكتشف نصائح وإرشادات نخبة المعلمين لتنظيم وقتك والتفوق في اختباراتك الدراسية.{mlang}
    </p>
  </div>
</div>
HTML;
        }

        return '';
    }

    /**
     * Refresh default starter blocks in the database with the updated Bassthalk light design.
     */
    public static function refresh_default_blocks(): void {
        global $DB;
        $defaults = ['about', 'contact', 'terms', 'privacy', 'faq', 'articles'];
        foreach ($defaults as $key) {
            $page = self::get_page_by_default_key($key);
            if (!$page) {
                continue;
            }
            $blocks = $DB->get_records('block_instances', [
                'pagetypepattern' => 'local-nit_pages-page',
                'subpagepattern'  => (string) $page->id,
                'blockname'       => 'nit_section',
            ]);
            $newhtml = self::generate_starter_html($key);
            foreach ($blocks as $b) {
                $cfg = !empty($b->configdata) ? unserialize(base64_decode($b->configdata)) : new \stdClass();
                $cfg->htmltext = $newhtml;
                $cfg->mode = 'html';
                $cfg->plain = 1;
                $cfg->width = 'full';
                $DB->update_record('block_instances', (object) [
                    'id'         => $b->id,
                    'configdata' => base64_encode(serialize($cfg)),
                    'timemodified' => time(),
                ]);
            }
        }
    }
}

