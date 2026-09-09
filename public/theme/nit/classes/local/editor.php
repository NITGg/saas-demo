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
 * In-academy inline front-page editor — shared backend for edit.php.
 *
 * The front page is built from `nit_section` blocks on the site-index page; each
 * region is found by a `data-nit-section="<marker>"` element in the block HTML,
 * and images are embedded as inline data: URIs (so replacing a region's HTML
 * frees the old image automatically — no orphaned files). Logo / favicon / login
 * background use theme stored files instead, which ARE deleted on replace here.
 *
 * This is Phase 3.0: the framework (permission, size limit, section find/save,
 * image handling, real file deletion). The per-region editors build on it.
 *
 * @package   theme_nit
 * @copyright 2026 NIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor {

    /**
     * Who may inline-edit the front page: a signed-in user who can manage the
     * site-home blocks — site admins AND the academy owner (whose role grants
     * moodle/site:manageblocks, i.e. the same capability that shows "Add a
     * block"). Students/teachers don't have it. Never guests.
     */
    public static function can_edit(): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (is_siteadmin()) {
            return true;
        }
        return has_capability('moodle/site:manageblocks', \context_system::instance());
    }

    /**
     * Max editable-image size in bytes. Driven by theme_nit/maximagemb (pushed
     * from the control plane's max_image_mb platform setting); defaults to 1.5 MB.
     */
    public static function max_image_bytes(): int {
        $mb = (float) get_config('theme_nit', 'maximagemb');
        if (!($mb > 0)) {
            $mb = 1.5;
        }
        return (int) round($mb * 1024 * 1024);
    }

    /** @return string[] image mime types accepted for inline editing. */
    public static function allowed_image_types(): array {
        return ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
    }

    /**
     * Find the front-page nit_section block whose HTML carries
     * data-nit-section="$marker".
     *
     * @param string $marker e.g. hero|about|gallery|contact|footer
     * @return array{0:\stdClass,1:\stdClass}|null [block_instance row, configdata object]
     */
    public static function find_section(string $marker): ?array {
        global $DB;
        $rs = $DB->get_records('block_instances', [
            'blockname' => 'nit_section', 'pagetypepattern' => 'site-index',
        ]);
        foreach ($rs as $bi) {
            $cfg = $bi->configdata ? unserialize(base64_decode($bi->configdata)) : null;
            $html = is_object($cfg) ? ($cfg->htmltext ?? $cfg->visualtext ?? $cfg->text ?? '') : '';
            if (strpos((string) $html, 'data-nit-section="' . $marker . '"') !== false) {
                if (!is_object($cfg)) {
                    $cfg = new \stdClass();
                }
                return [$bi, $cfg];
            }
        }
        return null;
    }

    /** Return the current HTML of a section's config object. */
    public static function section_html(\stdClass $cfg): string {
        return (string) ($cfg->htmltext ?? $cfg->visualtext ?? $cfg->text ?? '');
    }

    /** Persist new HTML into a section block (html mode) + purge caches. */
    public static function save_section_html(\stdClass $bi, \stdClass $cfg, string $html): void {
        global $DB;
        $cfg->mode = 'html';
        $cfg->htmltext = $html;
        $bi->configdata = base64_encode(serialize($cfg));
        $bi->timemodified = time();
        $DB->update_record('block_instances', $bi);
        purge_all_caches();
    }

    /**
     * Validate an uploaded image ($_FILES entry) and return a data: URI, or null
     * with an error code in $error (noimage|toolarge|badtype).
     *
     * @param array $file  a single $_FILES[...] entry
     * @param string|null $error out: error code on failure
     * @return string|null the data: URI, or null on failure
     */
    public static function uploaded_image_datauri(array $file, ?string &$error = null): ?string {
        $error = null;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'noimage';
            return null;
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::max_image_bytes()) {
            $error = 'toolarge';
            return null;
        }
        $raw = @file_get_contents($file['tmp_name']);
        if ($raw === false || $raw === '') {
            $error = 'noimage';
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: '';
        // SVG often sniffs as text/xml or text/plain — accept it when it looks like SVG.
        if (!in_array($mime, self::allowed_image_types(), true)) {
            $head = ltrim(substr($raw, 0, 256));
            if (stripos($head, '<svg') !== false || stripos($head, '<?xml') === 0) {
                $mime = 'image/svg+xml';
            } else {
                $error = 'badtype';
                return null;
            }
        }
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    /**
     * Set the CSS background image of the marker element inside a section's HTML
     * to $datauri, using DOMDocument so it targets exactly that element (not any
     * other background in the block). Returns the new HTML, or null if the marker
     * element isn't found.
     */
    public static function set_marker_background(string $html, string $marker, string $datauri): ?string {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $nodes = $xp->query('//*[@data-nit-section=' . self::xpath_literal($marker) . ']');
        if (!$nodes || $nodes->length === 0) {
            return null;
        }
        /** @var \DOMElement $node */
        $node = $nodes->item(0);
        $style = $node->getAttribute('style');
        $bg = "background:#000 url('" . $datauri . "') center/cover no-repeat";
        if (preg_match('/background\s*:/i', $style)) {
            $style = preg_replace('/background\s*:[^;]*;?/i', $bg . ';', $style, 1);
        } else {
            $style = rtrim($style, '; ') . ';' . $bg . ';';
        }
        $node->setAttribute('style', $style);
        return self::inner_html($dom, $xp);
    }

    /**
     * Merge specific CSS declarations into the marker element's inline style
     * (replacing each property if already present, else appending). Used for the
     * hero's width/height constraints. Returns new HTML, or null if not found.
     *
     * @param string $html
     * @param string $marker
     * @param array<string,string> $props CSS property => value (e.g. ['aspect-ratio'=>'16/6'])
     * @return string|null
     */
    public static function set_marker_style_props(string $html, string $marker, array $props): ?string {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $nodes = $xp->query('//*[@data-nit-section=' . self::xpath_literal($marker) . ']');
        if (!$nodes || $nodes->length === 0) {
            return null;
        }
        /** @var \DOMElement $node */
        $node = $nodes->item(0);
        $style = $node->getAttribute('style');
        foreach ($props as $prop => $value) {
            $q = preg_quote($prop, '/');
            if (preg_match('/' . $q . '\s*:/i', $style)) {
                $style = preg_replace('/' . $q . '\s*:[^;]*;?/i', $prop . ':' . $value . ';', $style, 1);
            } else {
                $style = rtrim($style, '; ') . ';' . $prop . ':' . $value . ';';
            }
        }
        $node->setAttribute('style', $style);
        return self::inner_html($dom, $xp);
    }

    /**
     * Set the background image of the about section's photo box
     * (data-nit-about-image), leaving the section background alone. Returns new
     * HTML, or null if the photo box isn't found.
     */
    public static function set_about_image(string $html, string $datauri): ?string {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $node = $xp->query('//*[@data-nit-about-image]')->item(0);
        if (!$node) {
            return null;
        }
        /** @var \DOMElement $node */
        // Clear any placeholder content inside the box (e.g. a "Instructor photo"
        // caption) so the new image isn't overlaid by the old placeholder.
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        // Show the WHOLE image (no crop). A taller 3/4 box + `contain` fits typical
        // instructor/portrait photos full-height; the surface colour fills any gap.
        $style = $node->getAttribute('style');
        // Replace an old 4/3 aspect with 3/4; add one if none is present.
        if (preg_match('/aspect-ratio\s*:/i', $style)) {
            $style = preg_replace('/aspect-ratio\s*:[^;]*;?/i', 'aspect-ratio:3/4;', $style, 1);
        } else {
            $style = rtrim($style, '; ') . ';aspect-ratio:3/4;';
        }
        $bg = "background:var(--nit-brand-surface,#f2f2f2) url('" . $datauri . "') center/contain no-repeat";
        if (preg_match('/background\s*:/i', $style)) {
            $style = preg_replace('/background\s*:[^;]*;?/i', $bg . ';', $style, 1);
        } else {
            $style = rtrim($style, '; ') . ';' . $bg . ';';
        }
        $node->setAttribute('style', $style);
        return self::inner_html($dom, $xp);
    }

    /**
     * Remove the placeholder "Get in touch" heading + its social-icon row from the
     * about section (contacts live in their own section). Idempotent — returns the
     * HTML unchanged if not present. Matches the h4 by its text (en/ar) and drops
     * it plus the element immediately after it.
     */
    public static function remove_about_getintouch(string $html): string {
        $frag = self::load_fragment($html);
        if (!$frag) {
            return $html;
        }
        [$dom, $xp] = $frag;
        $changed = false;
        foreach (iterator_to_array($xp->query('//h4')) as $h4) {
            $txt = $h4->textContent;
            if (stripos($txt, 'Get in touch') === false && strpos($txt, 'للتواصل') === false) {
                continue;
            }
            // Drop the next element sibling (the social-icon row) first, then the h4.
            $next = $h4->nextSibling;
            while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
                $next = $next->nextSibling;
            }
            if ($next) {
                $next->parentNode->removeChild($next);
            }
            $h4->parentNode->removeChild($h4);
            $changed = true;
        }
        return $changed ? self::inner_html($dom, $xp) : $html;
    }

    /**
     * Replace the bullet points in the about section's list (the first <ul>),
     * rebuilding each <li> with the same styling the brand applier uses. Text is
     * added as DOM text nodes, so it is escaped on serialize (no HTML injection).
     * Returns new HTML, or null if no list is found.
     *
     * @param string $html
     * @param string[] $bullets
     * @return string|null
     */
    public static function set_about_points(string $html, array $bullets): ?string {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $ul = $xp->query('(//ul)[1]')->item(0);
        if (!$ul) {
            return null;
        }
        while ($ul->firstChild) {
            $ul->removeChild($ul->firstChild);
        }
        $listyle = 'display:flex; gap:10px; font-size:15px; color: var(--nit-brand-textsecondary); line-height:1.7;';
        foreach ($bullets as $b) {
            $b = trim((string) $b);
            if ($b === '') {
                continue;
            }
            $li = $dom->createElement('li');
            $li->setAttribute('style', $listyle);
            $span = $dom->createElement('span');
            $span->setAttribute('style', 'color: var(--nit-brand-accent);');
            $span->appendChild($dom->createTextNode('◆'));
            $li->appendChild($span);
            $li->appendChild($dom->createTextNode(' ' . $b));
            $ul->appendChild($li);
        }
        return self::inner_html($dom, $xp);
    }

    /**
     * Build a Moodle multilang2 string from an EN/AR pair — mirrors the build
     * form's makeBullet(): both sides present → {mlang en}…{mlang}{mlang ar}…{mlang};
     * one side only → that side as plain text. Trims each side.
     *
     * @param string $en English text
     * @param string $ar Arabic text
     * @return string
     */
    public static function mlang_build(string $en, string $ar): string {
        $en = trim($en);
        $ar = trim($ar);
        if ($en !== '' && $ar !== '') {
            return '{mlang en}' . $en . '{mlang}{mlang ar}' . $ar . '{mlang}';
        }
        return $en !== '' ? $en : $ar;
    }

    /**
     * Split a (possibly multilang) raw string back into an EN/AR pair for editing.
     * No {mlang} tags → treat as a single value and seed BOTH sides with it (so a
     * bilingual academy can start from the existing text), leaving single-language
     * academies to just use 'en'.
     *
     * @param string $raw
     * @return array{en:string,ar:string}
     */
    public static function mlang_parse(string $raw): array {
        $en = '';
        $ar = '';
        if (preg_match('/\{mlang\s+en\}([\s\S]*?)\{mlang\}/i', $raw, $m)) {
            $en = trim($m[1]);
        }
        if (preg_match('/\{mlang\s+ar\}([\s\S]*?)\{mlang\}/i', $raw, $m)) {
            $ar = trim($m[1]);
        }
        if ($en === '' && $ar === '') {
            $plain = trim($raw);
            return ['en' => $plain, 'ar' => $plain];
        }
        return ['en' => $en, 'ar' => $ar];
    }

    /**
     * Replace the about section's subheader (the <h3> under the "About" heading).
     * Targets [data-nit-about-subheader], else the first <h3>; stamps the marker
     * so later reads/writes are unambiguous. Raw is set as a text node, so any
     * {mlang} tags survive verbatim for the multilang filter while HTML is escaped.
     *
     * @param string $html
     * @param string $raw subheader text (may carry {mlang} tags)
     * @return string|null new HTML, or null when no subheader node is found
     */
    public static function set_about_subheader(string $html, string $raw): ?string {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $node = $xp->query('//*[@data-nit-about-subheader]')->item(0) ?: $xp->query('(//h3)[1]')->item(0);
        if (!$node) {
            return null;
        }
        $node->setAttribute('data-nit-about-subheader', '1');
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($dom->createTextNode(trim($raw)));
        return self::inner_html($dom, $xp);
    }

    /**
     * Read the about section's editable text back out of its RAW html (with
     * {mlang} tags intact), for the inline editor to prefill. Returns the
     * subheader + bullets each already split into an EN/AR pair.
     *
     * @param string $html raw about-section html (block htmltext)
     * @return array{subheader:array{en:string,ar:string},bullets:array<int,array{en:string,ar:string}>}
     */
    public static function about_fields(string $html): array {
        $out = ['subheader' => ['en' => '', 'ar' => ''], 'bullets' => []];
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return $out;
        }
        $xp = new \DOMXPath($dom);
        $sub = $xp->query('//*[@data-nit-about-subheader]')->item(0) ?: $xp->query('(//h3)[1]')->item(0);
        if ($sub) {
            $out['subheader'] = self::mlang_parse(trim($sub->textContent));
        }
        $ul = $xp->query('(//ul)[1]')->item(0);
        if ($ul) {
            foreach ($xp->query('.//li', $ul) as $li) {
                // textContent includes the ◆ bullet glyph prefix — strip it.
                $raw = preg_replace('/^[\s◆]+/u', '', trim($li->textContent));
                if ($raw !== '') {
                    $out['bullets'][] = self::mlang_parse($raw);
                }
            }
        }
        return $out;
    }

    /** SVG glyph bodies (colour sentinel @C@) for the contact icons — mirrors
     *  provisioning/apply_contact.php so inline edits produce identical markup. */
    private static function contact_svg_bodies(): array {
        return [
            'phone'     => "<path fill='@C@' d='M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.4 0 .8-.2 1l-2.2 2.2z'/>",
            'whatsapp'  => "<path fill='@C@' d='M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.3A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8 8 0 1 1 12 20zm4.4-6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1l-.7.8c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.1-.3.2-.5 0-.1 0-.3-.1-.4L9 8.4c-.2-.4-.3-.4-.5-.4h-.4c-.2 0-.4.1-.6.3-.7.7-.9 1.6-.6 2.6.4 1.4 1.3 2.6 2.5 3.5 1.5 1.1 2.8 1.5 4.2 1.3.7-.1 1.4-.6 1.6-1.2.1-.3.1-.6.1-.7l-.4-.5z'/>",
            'facebook'  => "<path fill='@C@' d='M13.5 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.5 1.6-1.5H17V3.9c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1V10H8v3h2.6v8h2.9z'/>",
            'instagram' => "<rect x='3' y='3' width='18' height='18' rx='5' fill='none' stroke='@C@' stroke-width='2'/><circle cx='12' cy='12' r='4' fill='none' stroke='@C@' stroke-width='2'/><circle cx='17.2' cy='6.8' r='1.3' fill='@C@'/>",
            'youtube'   => "<path fill='@C@' d='M23 7.5a3 3 0 0 0-2.1-2.1C19 5 12 5 12 5s-7 0-8.9.4A3 3 0 0 0 1 7.5 31 31 0 0 0 .6 12 31 31 0 0 0 1 16.5a3 3 0 0 0 2.1 2.1C5 19 12 19 12 19s7 0 8.9-.4a3 3 0 0 0 2.1-2.1A31 31 0 0 0 23.4 12 31 31 0 0 0 23 7.5zM9.8 15.3V8.7l5.7 3.3z'/>",
            'tiktok'    => "<path fill='@C@' d='M16.5 3c.3 2.1 1.5 3.4 3.5 3.5v2.4c-1.2.1-2.3-.3-3.5-1v5.9c0 3.6-2.9 6.3-6.4 5.4-3.9-.9-4.9-5.8-1.8-8.2.9-.7 2-1 3.2-.9v2.6c-.5-.1-1-.1-1.5.1-1.3.5-1.6 2.2-.6 3.1 1 .9 2.9.5 3-1.2V3h2.6z'/>",
            'website'   => "<circle cx='12' cy='12' r='9' fill='none' stroke='@C@' stroke-width='2'/><path fill='none' stroke='@C@' stroke-width='2' d='M3 12h18M12 3c2.5 2.5 2.5 15.5 0 18M12 3c-2.5 2.5-2.5 15.5 0 18'/>",
        ];
    }

    /** Build the contact section block HTML from the given fields (mirrors
     *  apply_contact.php: base64 data-URI SVG icons, filter-safe). */
    public static function build_contact_html(string $phone, string $wa, array $social): string {
        $bodies = self::contact_svg_bodies();
        $iconcolor = trim((string) get_config('theme_nit', 'brandcolour_g1_primary'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $iconcolor)) {
            $iconcolor = '#1e7d67';
        }
        $bguri = function (string $body) use ($iconcolor): string {
            $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'>"
                . str_replace('@C@', $iconcolor, $body) . "</svg>";
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        };
        $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $icon = function (string $href, string $body, bool $blank) use ($e, $bguri): string {
            $target = $blank ? ' target="_blank" rel="noopener"' : '';
            $uri = $e($bguri($body));
            $glyph = "width:22px; height:22px; display:block; background: url('$uri') center/contain no-repeat;";
            return '<a href="' . $e($href) . '"' . $target . ' style="width:48px; height:48px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; background: color-mix(in srgb, var(--nit-brand-primary) 12%, transparent); border:1px solid color-mix(in srgb, var(--nit-brand-primary) 30%, transparent); text-decoration:none;">'
                . '<span aria-hidden="true" style="' . $glyph . '"></span></a>';
        };
        $teldigits = preg_replace('/[^0-9+]/', '', $phone);
        $wadigits = preg_replace('/[^0-9]/', '', $wa);
        $buttons = '';
        if ($phone !== '') {
            $buttons .= $icon('tel:' . $teldigits, $bodies['phone'], false);
        }
        if ($wadigits !== '') {
            $buttons .= $icon('https://wa.me/' . $wadigits, $bodies['whatsapp'], true);
        }
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'website'] as $net) {
            $u = trim((string) ($social[$net] ?? ''));
            if ($u !== '') {
                $buttons .= $icon($u, $bodies[$net], true);
            }
        }
        return '<div dir="auto" data-nit-section="contact" style="background: color-mix(in srgb, var(--nit-brand-surface) 70%, var(--nit-brand-background)); color: var(--nit-brand-textprimary); padding: 72px 20px; text-align: center;">'
            . '<div style="max-width: 820px; margin: 0 auto;">'
            . '<h2 style="font-size: clamp(24px,4vw,36px); font-weight: 800; margin: 0 0 12px;">{mlang ar}انضم إلينا اليوم{mlang}{mlang en}Join us today{mlang}</h2>'
            . '<p style="font-size: 15px; color: var(--nit-brand-textsecondary); line-height: 1.8; margin: 0 0 24px;">{mlang ar}تواصل معنا للاستفسار أو التسجيل.{mlang}{mlang en}Contact us to enquire or enrol.{mlang}</p>'
            . '<div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; align-items: center;">' . $buttons . '</div>'
            . '</div></div>';
    }

    /**
     * Persist contact/social fields: to theme_nit config (the app reads these via
     * design_system.php) AND rebuild the front-page contact block. Social values
     * are kept only when they look like an http(s) URL.
     *
     * @param array<string,string> $f phone, whatsapp, facebook, instagram, youtube, tiktok, website
     */
    public static function save_contact(array $f): bool {
        $phone = trim((string) ($f['phone'] ?? ''));
        $wa = trim((string) ($f['whatsapp'] ?? ''));
        if ($wa === '') {
            $wa = $phone;
        }
        $social = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'website'] as $net) {
            $u = trim((string) ($f[$net] ?? ''));
            $social[$net] = (stripos($u, 'http') === 0) ? $u : '';
        }
        set_config('contact_phone', $phone, 'theme_nit');
        set_config('support_phone', $wa !== '' ? $wa : $phone, 'theme_nit');
        foreach ($social as $net => $u) {
            set_config('social_' . $net, $u, 'theme_nit');
        }
        $found = self::find_section('contact');
        if ($found) {
            [$bi, $cfg] = $found;
            self::save_section_html($bi, $cfg, self::build_contact_html($phone, $wa, $social));
        } else {
            purge_all_caches();
        }
        return true;
    }

    /**
     * Build the footer block HTML with the given academy name + description and
     * (optionally) the compact logo. Keeps the standard quick-links (the Log in
     * link is marked data-nit-guest-only + hidden by CSS for logged-in users) and
     * contact/copyright columns. Mirrors provisioning/apply_footer.php.
     */
    public static function build_footer_html(string $name, string $desc, bool $showlogo): string {
        global $OUTPUT;
        $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $namesafe = $e($name);
        $descsafe = $e($desc);
        $logo = '';
        if ($showlogo && isset($OUTPUT)) {
            try {
                $url = $OUTPUT->get_compact_logo_url(300, 80);
                if ($url) {
                    $logo = '<img src="' . $e($url->out(false)) . '" alt="' . $namesafe
                        . '" style="height:44px; width:auto; margin-bottom:10px; display:block;">';
                }
            } catch (\Throwable $ex) {
                $logo = '';
            }
        }
        $year = date('Y');
        return '<div dir="auto" data-nit-section="footer" style="background: var(--nit-brand-secondary); color: var(--nit-brand-textsecondary); border-top:1px solid var(--nit-brand-borderprimary);">'
            . '<div style="max-width:1140px; margin:0 auto; padding:48px 20px 24px; display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:32px;">'
            . '<div>' . $logo
            . '<div style="font-size:18px; font-weight:800; color: var(--nit-brand-textprimary); margin-bottom:10px;">' . $namesafe . '</div>'
            . '<p style="font-size:13px; line-height:1.8; margin:0;">' . $descsafe . '</p>'
            . '</div>'
            . '<div>'
            . '<div style="font-size:14px; font-weight:bold; color: var(--nit-brand-textprimary); margin-bottom:12px;">{mlang ar}روابط سريعة{mlang}{mlang en}Quick links{mlang}</div>'
            . '<div style="display:flex; flex-direction:column; gap:8px; font-size:13px;">'
            . '<a href="/" style="color: var(--nit-brand-textsecondary); text-decoration:none;">{mlang ar}الرئيسية{mlang}{mlang en}Home{mlang}</a>'
            . '<a href="/course/index.php" style="color: var(--nit-brand-textsecondary); text-decoration:none;">{mlang ar}الكورسات{mlang}{mlang en}Courses{mlang}</a>'
            . '<a href="/login/index.php" data-nit-guest-only style="color: var(--nit-brand-textsecondary); text-decoration:none;">{mlang ar}تسجيل الدخول{mlang}{mlang en}Log in{mlang}</a>'
            . '</div>'
            . '</div>'
            . '<div>'
            . '<div style="font-size:14px; font-weight:bold; color: var(--nit-brand-textprimary); margin-bottom:12px;">{mlang ar}تواصل معنا{mlang}{mlang en}Contact{mlang}</div>'
            . '<p style="font-size:13px; line-height:1.8; margin:0;">{mlang ar}للاستفسار والتسجيل تواصل معنا عبر بيانات التواصل بالأعلى.{mlang}{mlang en}Reach us via the contact details above.{mlang}</p>'
            . '</div>'
            . '</div>'
            . '<div style="border-top:1px solid var(--nit-brand-borderprimary); padding:16px 20px; text-align:center; font-size:12px;">'
            . '&copy; <span data-nit-year>' . $year . '</span> &mdash; {mlang ar}جميع الحقوق محفوظة{mlang}{mlang en}All rights reserved{mlang}'
            . '&nbsp;&middot;&nbsp; <a href="https://nitg-eg.com" target="_blank" rel="noopener" style="color: var(--nit-brand-accenttext); text-decoration:none;">N.I.T</a>'
            . '</div></div>';
    }

    /** Rebuild + save the footer block. Returns false if no footer block exists. */
    public static function save_footer(string $name, string $desc, bool $showlogo): bool {
        $found = self::find_section('footer');
        if (!$found) {
            return false;
        }
        [$bi, $cfg] = $found;
        self::footer_to_bottom($bi);
        self::save_section_html($bi, $cfg, self::build_footer_html($name, $desc, $showlogo));
        return true;
    }

    /**
     * Move the footer block into the LAST region (fullwidth-bottom) so it renders
     * below the course list, not above it. Idempotent.
     */
    public static function footer_to_bottom(\stdClass $bi): void {
        global $DB;
        if (($bi->defaultregion ?? '') !== 'fullwidth-bottom') {
            $DB->set_field('block_instances', 'defaultregion', 'fullwidth-bottom', ['id' => $bi->id]);
            $bi->defaultregion = 'fullwidth-bottom';
            // Drop any explicit position override that would pin it to the old region.
            $DB->delete_records('block_positions', ['blockinstanceid' => $bi->id]);
        }
    }

    /**
     * Load a section HTML fragment into a DOMDocument wrapped in data-nitwrap.
     * @return array{0:\DOMDocument,1:\DOMXPath}|null
     */
    private static function load_fragment(string $html): ?array {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-nitwrap="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok ? [$dom, new \DOMXPath($dom)] : null;
    }

    /** Element-node children of a node (skips text/whitespace nodes). */
    private static function element_children(\DOMNode $node): array {
        $els = [];
        foreach ($node->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE) {
                $els[] = $c;
            }
        }
        return $els;
    }

    /** Append a new gallery tile (data-URI background) to the grid. Returns new
     *  HTML, null if no grid, or 'full' when the max tile count is reached. */
    public static function gallery_add(string $html, string $datauri, int $max = 12) {
        $frag = self::load_fragment($html);
        if (!$frag) {
            return null;
        }
        [$dom, $xp] = $frag;
        $grid = $xp->query('//*[@data-nit-gallery-grid]')->item(0);
        if (!$grid) {
            return null;
        }
        if (count(self::element_children($grid)) >= $max) {
            return 'full';
        }
        $tile = $dom->createElement('div');
        $tile->setAttribute('style', "aspect-ratio:4/3; border-radius:12px; background:url('"
            . $datauri . "') center/cover no-repeat; border:1px solid var(--nit-brand-borderprimary);");
        $grid->appendChild($tile);
        return self::inner_html($dom, $xp);
    }

    /**
     * Rebuild the gallery grid from an ordered token list. Each token is
     * "e:<i>" (keep existing tile i, in this new position) or "n:<k>" (a newly
     * uploaded image, $newdatauris[$k]). This does staged add + delete + REORDER
     * in one save. Returns new HTML, or null if no grid.
     *
     * @param string $html
     * @param string[] $order tokens e.g. ['e:2','n:0','e:0']
     * @param array<int,string> $newdatauris new-image data URIs keyed by k
     * @param int $max
     * @return string|null
     */
    public static function gallery_rebuild(string $html, array $order, array $newdatauris, int $max = 12): ?string {
        $frag = self::load_fragment($html);
        if (!$frag) {
            return null;
        }
        [$dom, $xp] = $frag;
        $grid = $xp->query('//*[@data-nit-gallery-grid]')->item(0);
        if (!$grid) {
            return null;
        }
        $existing = self::element_children($grid);
        $newnodes = [];
        foreach ($order as $tok) {
            if (count($newnodes) >= $max) {
                break;
            }
            if (preg_match('/^e:(\d+)$/', (string) $tok, $m)) {
                $i = (int) $m[1];
                if (isset($existing[$i])) {
                    $newnodes[] = $existing[$i];
                }
            } else if (preg_match('/^n:(\d+)$/', (string) $tok, $m)) {
                $k = (int) $m[1];
                if (isset($newdatauris[$k])) {
                    $tile = $dom->createElement('div');
                    $tile->setAttribute('style', "aspect-ratio:4/3; border-radius:12px; background:url('"
                        . $newdatauris[$k] . "') center/cover no-repeat; border:1px solid var(--nit-brand-borderprimary);");
                    $newnodes[] = $tile;
                }
            }
        }
        while ($grid->firstChild) {
            $grid->removeChild($grid->firstChild);
        }
        foreach ($newnodes as $n) {
            $grid->appendChild($n);
        }
        return self::inner_html($dom, $xp);
    }

    /** Remove the gallery tile at $index (0-based). Returns new HTML, or null. */
    public static function gallery_delete(string $html, int $index): ?string {
        $frag = self::load_fragment($html);
        if (!$frag) {
            return null;
        }
        [$dom, $xp] = $frag;
        $grid = $xp->query('//*[@data-nit-gallery-grid]')->item(0);
        if (!$grid) {
            return null;
        }
        $els = self::element_children($grid);
        if ($index < 0 || $index >= count($els)) {
            return null;
        }
        $grid->removeChild($els[$index]);
        return self::inner_html($dom, $xp);
    }

    /** Serialize the children of the data-nitwrap wrapper back to an HTML string. */
    private static function inner_html(\DOMDocument $dom, \DOMXPath $xp): string {
        $wrap = $xp->query("//*[@data-nitwrap='1']")->item(0);
        if (!$wrap) {
            return '';
        }
        $out = '';
        foreach ($wrap->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return $out;
    }

    /** Quote a string as an XPath literal (safe for values containing quotes). */
    private static function xpath_literal(string $value): string {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        return "concat('" . str_replace("'", "',\"'\",'", $value) . "')";
    }

    /**
     * Replace a theme_nit stored-file setting (e.g. logo) with an uploaded file,
     * DELETING the previous file first so we don't leak storage. Also updates the
     * matching theme_nit config value to the new filename.
     *
     * @param string $setting theme_nit setting name / filearea (e.g. 'logo')
     * @param array $file a single $_FILES[...] entry
     * @param string|null $error out: error code on failure
     * @return bool success
     */
    public static function replace_stored_file(string $setting, array $file, ?string &$error = null): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $error = null;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'noimage';
            return false;
        }
        if ((int) ($file['size'] ?? 0) > self::max_image_bytes()) {
            $error = 'toolarge';
            return false;
        }
        $fs = get_file_storage();
        $context = \context_system::instance();
        // Delete every existing file in this theme_nit filearea (real deletion).
        $fs->delete_area_files($context->id, 'theme_nit', $setting, 0);

        $filename = clean_filename($file['name'] ?? ($setting . '.img'));
        $record = [
            'contextid' => $context->id, 'component' => 'theme_nit', 'filearea' => $setting,
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ];
        try {
            $fs->create_file_from_pathname($record, $file['tmp_name']);
        } catch (\Throwable $e) {
            $error = 'savefailed';
            return false;
        }
        set_config($setting, $filename, 'theme_nit');
        purge_all_caches();
        return true;
    }

    /**
     * Rename the academy — sets the site (front-page course) full name. The short
     * name is left alone (it's a stable identifier and must stay unique). Caches
     * are purged so the new name shows everywhere ($SITE, title, footer) at once.
     *
     * @param string $name the new display name
     * @return bool false on an empty / over-long name
     */
    public static function set_site_name(string $name): bool {
        global $DB;
        $name = trim($name);
        if ($name === '' || \core_text::strlen($name) > 254) {
            return false;
        }
        $DB->set_field('course', 'fullname', $name, ['id' => SITEID]);
        rebuild_course_cache(SITEID, true);
        purge_all_caches();
        return true;
    }

    /**
     * Replace a CORE site file (logo / logocompact / favicon — stored under the
     * core_admin component, exactly like theme_nit's brand applier), DELETING the
     * previous file first so storage isn't leaked. Updates the core_admin config
     * to '/<filename>' and bumps the theme revision so the new URL is served.
     *
     * @param string $filearea core_admin filearea (logo|logocompact|favicon)
     * @param string $configname core_admin config name (usually == filearea)
     * @param array $file a single $_FILES[...] entry
     * @param string|null $error out: error code on failure
     * @return bool success
     */
    public static function replace_site_file(string $filearea, string $configname, array $file, ?string &$error = null): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $error = null;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'noimage';
            return false;
        }
        if ((int) ($file['size'] ?? 0) > self::max_image_bytes()) {
            $error = 'toolarge';
            return false;
        }
        $raw = @file_get_contents($file['tmp_name']);
        $mime = $raw ? ((new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: '') : '';
        if (!in_array($mime, self::allowed_image_types(), true)) {
            $head = ltrim(substr((string) $raw, 0, 256));
            if (stripos($head, '<svg') === false && stripos($head, '<?xml') !== 0) {
                $error = 'badtype';
                return false;
            }
        }
        $filename = clean_filename($file['name'] ?? ($filearea . '.img'));
        if ($filename === '') {
            $error = 'badname';
            return false;
        }
        $fs = get_file_storage();
        $ctx = \context_system::instance();
        $fs->delete_area_files($ctx->id, 'core_admin', $filearea, 0);
        try {
            $fs->create_file_from_pathname([
                'contextid' => $ctx->id, 'component' => 'core_admin', 'filearea' => $filearea,
                'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
            ], $file['tmp_name']);
        } catch (\Throwable $e) {
            $error = 'savefailed';
            return false;
        }
        set_config($configname, '/' . $filename, 'core_admin');
        return true;
    }

    /** Blend hex colour $a toward $b by ratio $r (0..1). Mirrors create.sh _mix. */
    private static function mix(string $a, string $b, float $r): string {
        $a = ltrim($a, '#');
        $b = ltrim($b, '#');
        if (strlen($a) !== 6 || strlen($b) !== 6) {
            return '#' . $a;
        }
        $ch = fn($h, $i) => hexdec(substr($h, $i, 2));
        return sprintf('#%02x%02x%02x',
            (int) round($ch($a, 0) * (1 - $r) + $ch($b, 0) * $r),
            (int) round($ch($a, 2) * (1 - $r) + $ch($b, 2) * $r),
            (int) round($ch($a, 4) * (1 - $r) + $ch($b, 4) * $r));
    }

    /**
     * Apply the 6 brand-palette pickers to theme_nit's Group-1 roles and DERIVE
     * the rest (accent-text, secondary text, borders, hover) — the exact mapping
     * create.sh uses — then bump theme caches so the CSS recompiles. Returns false
     * if any of the six isn't a #rrggbb hex.
     *
     * @param array<string,string> $c primary, accent, secondary, background, surface, text
     */
    public static function save_palette(array $c, bool $prewarm = true): bool {
        $hex = function ($v): ?string {
            $v = trim((string) $v);
            return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : null;
        };
        $p = $hex($c['primary'] ?? '');
        $acc = $hex($c['accent'] ?? '');
        $sec = $hex($c['secondary'] ?? '');
        $bg = $hex($c['background'] ?? '');
        $surf = $hex($c['surface'] ?? '');
        $txt = $hex($c['text'] ?? '');
        if (!$p || !$acc || !$sec || !$bg || !$surf || !$txt) {
            return false;
        }
        $set = fn($role, $val) => set_config('brandcolour_g1_' . $role, $val, 'theme_nit');
        $set('primary', $p);
        $set('secondary', $sec);
        $set('background', $bg);
        $set('surface', $surf);
        $set('textprimary', $txt);
        $set('accent', $acc);
        // Button text must CONTRAST with the primary button, not tint toward the
        // accent (a dark-gold accent on a navy primary was unreadable). Pick the
        // AA-safe foreground (white or dark ink) for the primary colour.
        $set('accenttext', \local_nit_core\branding\contrast::safe_foreground($p));
        $set('textsecondary', self::mix($txt, $bg, 0.42));
        $set('borderprimary', self::mix($surf, $txt, 0.12));
        $set('bordersecondary', self::mix($surf, $txt, 0.24));
        $set('hoverbackground', self::mix($surf, $p, 0.14));
        $set('hovertext', $txt);
        self::bust_theme_caches($prewarm);
        return true;
    }

    /** Bump theme caches + revision so a new logo/brand URL is served immediately. */
    public static function bust_theme_caches(bool $prewarm = true): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        if (function_exists('theme_reset_all_caches')) {
            theme_reset_all_caches();
        }
        purge_all_caches();
        if ($prewarm) {
            self::prewarm_theme_css();
        }
    }

    /**
     * Recompile the theme CSS in a detached background process, returning at
     * once. Building both directions takes several seconds; the front-page
     * editor already shows the new palette live via CSS custom properties, so
     * the Save request must not block on the SCSS build. Spawns a short CLI
     * process (theme/nit/cli/prewarm_css.php) that outlives this request. Falls
     * back to a synchronous compile when a child process can't be started.
     */
    public static function spawn_prewarm(): void {
        global $CFG;
        $script = $CFG->dirroot . '/theme/nit/cli/prewarm_css.php';
        $php = PHP_BINDIR . '/php';
        if (function_exists('exec') && is_readable($script) && is_executable($php)) {
            // Detach: redirect all fds and background so exec() returns instantly
            // and the child survives the end of this (mod_php) request.
            @exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');
            return;
        }
        self::prewarm_theme_css();
    }

    /**
     * Compile the theme CSS now (both directions) in the current request — which
     * already holds the freshly-saved config in $CFG. Without it the theme CSS is
     * regenerated lazily by whichever later request wins the race, and that
     * request can pick up a one-revision-stale config snapshot, so core Moodle
     * components (course cards, buttons, the page body — compiled Bootstrap, not
     * live CSS custom properties) keep the *previous* colours until a manual
     * purge / hard refresh. Building both directions keeps RTL (Arabic) correct.
     *
     * Safe to call AFTER the HTTP response has been flushed
     * (fastcgi_finish_request), so the slow SCSS build never blocks the caller.
     */
    public static function prewarm_theme_css(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        if (!function_exists('theme_build_css_for_themes')) {
            return;
        }
        try {
            theme_build_css_for_themes([\theme_config::load('nit')], ['rtl', 'ltr']);
        } catch (\Throwable $e) {
            // Non-fatal: fall back to lazy compile on the next page load.
            debugging('theme_nit palette prewarm failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /** Delete a theme_nit stored-file setting's files + clear its config. */
    public static function delete_stored_file(string $setting): void {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'theme_nit', $setting, 0);
        unset_config($setting, 'theme_nit');
        purge_all_caches();
    }
}
