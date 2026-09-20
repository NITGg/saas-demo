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

namespace theme_nit\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Fills the editable content hooks in the homepage template blocks.
 *
 * Templates mark editable spots with:
 *   data-nit-edit="<key>"        — text (replaced with a {mlang} string)
 *   data-nit-edit-img="<key>"    — image (background-image set from a data URI)
 *   data-nit-edit-href="<key>"   — link target (href)
 *   data-nit-logo                — the nav/footer logo mark (filled from the logo image)
 *
 * Moodle-dynamic content (courses/categories/subscriptions/coupons, all the other
 * data-nit-* hooks) is NOT touched — it hydrates at runtime.
 *
 * Only keys PRESENT in the manifest are filled; absent keys keep the template's
 * own (already-designed, bilingual) defaults. So a partial manifest is fine.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class homepage_content {

    /** Text keys that also drive a link (key => href scheme). */
    const LINK_TEXT = ['contact_email' => 'mailto:', 'contact_phone' => 'tel:'];

    /**
     * The editable-content schema shared by the homepage templates: which fields
     * exist, their type and where they belong. Drives the Moodle content editor
     * and mirrors the nit2 build-form fields. Absent-in-a-template keys are simply
     * ignored when filling (only present hooks are touched).
     *
     * @return array<int, array{key:string,type:string,group:string,label:string,multiline?:bool}>
     */
    public static function fields(): array {
        $t = function (string $key, string $group, string $label, bool $ml = false): array {
            return ['key' => $key, 'type' => 'text', 'group' => $group, 'label' => $label, 'multiline' => $ml];
        };
        return [
            // Brand / nav / footer identity.
            $t('brand_name', 'brand', 'Academy name'),
            ['key' => 'logo', 'type' => 'image', 'group' => 'brand', 'label' => 'Logo mark'],
            $t('footer_tagline', 'brand', 'Footer tagline', true),
            // Hero.
            $t('hero_badge', 'hero', 'Hero badge'),
            $t('hero_title', 'hero', 'Hero title', true),
            $t('hero_subtitle', 'hero', 'Hero subtitle', true),
            ['key' => 'hero', 'type' => 'image', 'group' => 'hero', 'label' => 'Hero image'],
            // Section headings.
            $t('categories_eyebrow', 'headings', 'Categories eyebrow'),
            $t('categories_heading', 'headings', 'Categories heading'),
            $t('courses_eyebrow', 'headings', 'Courses eyebrow'),
            $t('courses_heading', 'headings', 'Courses heading'),
            $t('plans_eyebrow', 'headings', 'Plans eyebrow'),
            $t('plans_heading', 'headings', 'Plans heading'),
            $t('coupons_eyebrow', 'headings', 'Coupons eyebrow'),
            $t('coupons_heading', 'headings', 'Coupons heading'),
            // About.
            $t('about_heading', 'about', 'About heading', true),
            $t('about_text', 'about', 'About text', true),
            ['key' => 'about', 'type' => 'image', 'group' => 'about', 'label' => 'About image'],
            // Gallery.
            $t('gallery_eyebrow', 'gallery', 'Gallery eyebrow'),
            $t('gallery_heading', 'gallery', 'Gallery heading'),
            ['key' => 'gallery_1', 'type' => 'image', 'group' => 'gallery', 'label' => 'Gallery image 1'],
            ['key' => 'gallery_2', 'type' => 'image', 'group' => 'gallery', 'label' => 'Gallery image 2'],
            ['key' => 'gallery_3', 'type' => 'image', 'group' => 'gallery', 'label' => 'Gallery image 3'],
            ['key' => 'gallery_4', 'type' => 'image', 'group' => 'gallery', 'label' => 'Gallery image 4'],
            ['key' => 'gallery_5', 'type' => 'image', 'group' => 'gallery', 'label' => 'Gallery image 5'],
            // Testimonials.
            $t('testimonials_heading', 'testimonials', 'Testimonials heading'),
            $t('t_text_1', 'testimonials', 'Testimonial 1 quote', true),
            $t('t_name_1', 'testimonials', 'Testimonial 1 name'),
            $t('t_role_1', 'testimonials', 'Testimonial 1 role'),
            ['key' => 't_avatar_1', 'type' => 'image', 'group' => 'testimonials', 'label' => 'Testimonial 1 photo'],
            $t('t_text_2', 'testimonials', 'Testimonial 2 quote', true),
            $t('t_name_2', 'testimonials', 'Testimonial 2 name'),
            $t('t_role_2', 'testimonials', 'Testimonial 2 role'),
            ['key' => 't_avatar_2', 'type' => 'image', 'group' => 'testimonials', 'label' => 'Testimonial 2 photo'],
            $t('t_text_3', 'testimonials', 'Testimonial 3 quote', true),
            $t('t_name_3', 'testimonials', 'Testimonial 3 name'),
            $t('t_role_3', 'testimonials', 'Testimonial 3 role'),
            ['key' => 't_avatar_3', 'type' => 'image', 'group' => 'testimonials', 'label' => 'Testimonial 3 photo'],
            // FAQ.
            $t('faq_heading', 'faq', 'FAQ heading'),
            $t('faq_q_1', 'faq', 'FAQ 1 question'), $t('faq_a_1', 'faq', 'FAQ 1 answer', true),
            $t('faq_q_2', 'faq', 'FAQ 2 question'), $t('faq_a_2', 'faq', 'FAQ 2 answer', true),
            $t('faq_q_3', 'faq', 'FAQ 3 question'), $t('faq_a_3', 'faq', 'FAQ 3 answer', true),
            $t('faq_q_4', 'faq', 'FAQ 4 question'), $t('faq_a_4', 'faq', 'FAQ 4 answer', true),
            // App band.
            $t('app_heading', 'app', 'App band heading'),
            $t('app_text', 'app', 'App band text', true),
            ['key' => 'app_ios', 'type' => 'link', 'group' => 'app', 'label' => 'App Store URL'],
            ['key' => 'app_android', 'type' => 'link', 'group' => 'app', 'label' => 'Google Play URL'],
            // Contact.
            $t('contact_email', 'contact', 'Contact email'),
            $t('contact_phone', 'contact', 'Contact phone'),
            $t('contact_address', 'contact', 'Contact address', true),
        ];
    }

    /**
     * Read the CURRENT value of each text/link field from the homepage blocks,
     * so an editor can pre-fill. Text values are split back into {en,ar}. Images
     * are not read back (they are data URIs / stored files).
     *
     * @return array<string, mixed> key => {en,ar} for text, or url string for link
     */
    public static function read(): array {
        global $DB;
        $out = [];
        $blocks = $DB->get_records('block_instances', [
            'blockname' => 'nit_section', 'pagetypepattern' => 'site-index',
        ]);
        foreach ($blocks as $bi) {
            $cfg = $bi->configdata ? @unserialize(base64_decode($bi->configdata)) : null;
            if (!is_object($cfg)) {
                continue;
            }
            $html = (string) ($cfg->htmltext ?? $cfg->visualtext ?? $cfg->text ?? '');
            if ($html === '') {
                continue;
            }
            $dom = new \DOMDocument();
            $prev = libxml_use_internal_errors(true);
            $ok = $dom->loadHTML('<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if (!$ok) {
                continue;
            }
            $xp = new \DOMXPath($dom);
            foreach (iterator_to_array($xp->query('//*[@data-nit-edit]')) as $node) {
                $key = $node->getAttribute('data-nit-edit');
                if (isset($out[$key])) {
                    continue;
                }
                $inner = '';
                foreach ($node->childNodes as $c) {
                    $inner .= $dom->saveHTML($c);
                }
                $out[$key] = self::split_mlang(trim($inner));
            }
            foreach (iterator_to_array($xp->query('//*[@data-nit-edit-href]')) as $node) {
                $key = $node->getAttribute('data-nit-edit-href');
                if (!isset($out[$key . '__href'])) {
                    $out[$key . '__href'] = $node->getAttribute('href');
                }
            }
        }
        return $out;
    }

    /** Parse "{mlang en}X{mlang}{mlang ar}Y{mlang}" back into {en,ar} (plain -> en). */
    private static function split_mlang(string $raw): array {
        if (preg_match_all('/\{mlang\s+(\w+)\}([\s\S]*?)\{mlang\}/', $raw, $m, PREG_SET_ORDER)) {
            $r = ['en' => '', 'ar' => ''];
            foreach ($m as $one) {
                $r[$one[1]] = trim($one[2]);
            }
            return $r;
        }
        return ['en' => $raw, 'ar' => ''];
    }

    /**
     * Apply a content manifest to every homepage nit_section block.
     *
     * Manifest shape (every field optional):
     *   text: { key: string | {en,ar} }         — hero_title, hero_subtitle, about_heading,
     *                                               about_text, brand_name, footer_tagline,
     *                                               app_heading, app_text, contact_email,
     *                                               contact_phone, contact_address
     *   href: { key: url }                       — app_ios, app_android
     *   images: { key: dataUri }                 — hero, about, logo
     *
     * @param array $manifest
     * @param array|null $log
     * @return bool
     */
    public static function apply(array $manifest, ?array &$log = null): bool {
        global $DB;
        $log = [];
        $content = self::normalise($manifest);
        if (!$content['text'] && !$content['href'] && !$content['img']) {
            $log[] = 'no content fields to apply';
            return true;
        }
        $blocks = $DB->get_records('block_instances', [
            'blockname' => 'nit_section', 'pagetypepattern' => 'site-index',
        ]);
        $n = 0;
        foreach ($blocks as $bi) {
            $cfg = $bi->configdata ? @unserialize(base64_decode($bi->configdata)) : null;
            if (!is_object($cfg)) {
                continue;
            }
            $html = (string) ($cfg->htmltext ?? $cfg->visualtext ?? $cfg->text ?? '');
            if ($html === '') {
                continue;
            }
            $filled = self::fill_html($html, $content);
            if ($filled !== $html) {
                $cfg->mode = 'html';
                $cfg->htmltext = $filled;
                $bi->configdata = base64_encode(serialize($cfg));
                $bi->timemodified = time();
                $DB->update_record('block_instances', $bi);
                $n++;
            }
        }
        purge_all_caches();
        $log[] = "content applied to {$n} block(s)";
        return true;
    }

    /**
     * Normalise a raw manifest into {text:{key=>mlangstr}, href:{key=>url}, img:{key=>datauri}}.
     * Bilingual values ({en,ar}) become {mlang} strings; contact_email/phone also
     * generate a mailto:/tel: href.
     */
    private static function normalise(array $m): array {
        $out = ['text' => [], 'href' => [], 'img' => []];

        $textkeys = ['brand_name', 'hero_title', 'hero_subtitle', 'about_heading', 'about_text',
            'footer_tagline', 'app_heading', 'app_text', 'contact_email', 'contact_phone', 'contact_address'];
        $src = isset($m['text']) && is_array($m['text']) ? $m['text'] : $m;
        foreach ($textkeys as $k) {
            if (!array_key_exists($k, $src)) {
                continue;
            }
            $val = self::mlang($src[$k]);
            if ($val === '') {
                continue;
            }
            $out['text'][$k] = $val;
            if (isset(self::LINK_TEXT[$k])) {
                // A link value uses the raw (single-language) string, not the mlang wrapper.
                $raw = self::plain($src[$k]);
                if ($raw !== '') {
                    $out['href'][$k] = self::LINK_TEXT[$k] . $raw;
                }
            }
        }

        $hrefsrc = isset($m['href']) && is_array($m['href']) ? $m['href'] : $m;
        foreach (['app_ios', 'app_android'] as $k) {
            if (!empty($hrefsrc[$k]) && is_string($hrefsrc[$k])) {
                $out['href'][$k] = clean_param(trim($hrefsrc[$k]), PARAM_URL);
            }
        }

        $imgsrc = isset($m['images']) && is_array($m['images']) ? $m['images'] : (isset($m['img']) && is_array($m['img']) ? $m['img'] : []);
        foreach (['hero', 'about', 'logo'] as $k) {
            if (!empty($imgsrc[$k]) && is_string($imgsrc[$k]) && strpos($imgsrc[$k], 'data:') === 0) {
                $out['img'][$k] = $imgsrc[$k];
            }
        }
        return $out;
    }

    /** Build a {mlang} string from a value that is a string or {en,ar}. */
    private static function mlang($v): string {
        if (is_array($v)) {
            $en = trim((string) ($v['en'] ?? ''));
            $ar = trim((string) ($v['ar'] ?? ''));
            if ($en !== '' && $ar !== '') {
                return '{mlang en}' . $en . '{mlang}{mlang ar}' . $ar . '{mlang}';
            }
            return $en !== '' ? $en : $ar;
        }
        return trim((string) $v);
    }

    /** The single-language plain form of a value (prefers en) — for hrefs. */
    private static function plain($v): string {
        if (is_array($v)) {
            return trim((string) ($v['en'] ?? $v['ar'] ?? ''));
        }
        return trim((string) $v);
    }

    /**
     * Replace the content hooks in one block's HTML. Pure function.
     */
    public static function fill_html(string $html, array $content): string {
        if (trim($html) === '') {
            return $html;
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return $html;
        }
        $xp = new \DOMXPath($dom);
        $changed = false;

        foreach ($content['text'] as $key => $val) {
            foreach (iterator_to_array($xp->query('//*[@data-nit-edit=' . self::xpath_lit($key) . ']')) as $node) {
                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                // The value is a {mlang} / plain string; store as a text node so the
                // multilang filter processes it at render time.
                $node->appendChild($dom->createTextNode($val));
                $changed = true;
            }
        }

        foreach ($content['href'] as $key => $val) {
            foreach (iterator_to_array($xp->query('//*[@data-nit-edit-href=' . self::xpath_lit($key) . ']')) as $node) {
                $node->setAttribute('href', $val);
                $changed = true;
            }
        }

        foreach ($content['img'] as $key => $datauri) {
            foreach (iterator_to_array($xp->query('//*[@data-nit-edit-img=' . self::xpath_lit($key) . ']')) as $node) {
                self::set_bg($node, $datauri);
                $changed = true;
            }
            if ($key === 'logo') {
                foreach (iterator_to_array($xp->query('//*[@data-nit-logo]')) as $node) {
                    self::set_bg($node, $datauri);
                    $changed = true;
                }
            }
        }

        if (!$changed) {
            return $html;
        }
        $wrap = $xp->query('//div[@data-nitwrap="1"]')->item(0);
        $out = '';
        foreach ($wrap->childNodes as $c) {
            $out .= $dom->saveHTML($c);
        }
        return $out;
    }

    /** Set/replace the background-image on an element's inline style. */
    private static function set_bg(\DOMElement $node, string $datauri): void {
        $style = $node->getAttribute('style');
        // Drop any existing background-image, keep the rest (position/size/repeat).
        $style = preg_replace('/background-image\s*:[^;]*;?/i', '', $style);
        $style = rtrim(trim($style), ';');
        $style .= ($style ? ';' : '') . 'background-image:url(' . str_replace(['"', '\\', ' '], '', $datauri) . ');';
        $node->setAttribute('style', $style);
    }

    /** XPath string literal that survives quotes. */
    private static function xpath_lit(string $value): string {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        return "concat('" . str_replace("'", "',\"'\",'", $value) . "')";
    }
}
