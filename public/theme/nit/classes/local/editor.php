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
     * Who may inline-edit the front page: a signed-in site admin of THIS academy
     * (the owner account is a site admin). Never guests.
     */
    public static function can_edit(): bool {
        return isloggedin() && !isguestuser() && is_siteadmin();
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

    /** Bump theme caches + revision so a new logo/brand URL is served immediately. */
    public static function bust_theme_caches(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        if (function_exists('theme_reset_all_caches')) {
            theme_reset_all_caches();
        }
        purge_all_caches();
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
