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
 * In-academy inline front-page editor — save endpoint (Phase 3.0 framework).
 *
 * Security: POST only, requires login + a valid sesskey + site-admin (see
 * editor::can_edit). Dispatches by `action`; each action returns a small JSON
 * result. Per-region actions (text, points, gallery, contact, footer, palette,
 * logo) are added in Phases 3.1–3.8 on top of this dispatcher.
 *
 * @package   theme_nit
 * @copyright 2026 NIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/blocklib.php');

require_login();
require_sesskey();

use theme_nit\local\editor;

header('Content-Type: application/json; charset=utf-8');

/** Emit a JSON result and stop. */
function nit_edit_respond(bool $ok, array $extra = []): void {
    echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!editor::can_edit()) {
    nit_edit_respond(false, ['error' => 'forbidden']);
}

$action = required_param('action', PARAM_ALPHANUMEXT);

try {
    switch ($action) {

        // Handshake — lets the JS confirm edit rights + learn the size limit.
        case 'ping':
            nit_edit_respond(true, ['maximagebytes' => editor::max_image_bytes()]);
            break;

        // Hero — optional new image + height constraints, saved together (one POST).
        case 'hero':
            $found = editor::find_section('hero');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            [$bi, $cfg] = $found;
            $html = editor::section_html($cfg);
            if (!empty($_FILES['image']['tmp_name'])) {
                $err = null;
                $datauri = editor::uploaded_image_datauri($_FILES['image'], $err);
                if ($datauri === null) {
                    nit_edit_respond(false, ['error' => $err ?? 'image']);
                }
                $h = editor::set_marker_background($html, 'hero', $datauri);
                if ($h !== null) {
                    $html = $h;
                }
            }
            $aspect = optional_param('aspect', '', PARAM_RAW_TRIMMED);
            $minheight = optional_param('minheight', 0, PARAM_INT);
            $props = [];
            if (preg_match('#^\d{1,2}/\d{1,2}$#', $aspect)) {
                $props['aspect-ratio'] = $aspect;
            }
            if ($minheight >= 120 && $minheight <= 1000) {
                $props['min-height'] = $minheight . 'px';
            }
            if ($props) {
                $h = editor::set_marker_style_props($html, 'hero', $props);
                if ($h !== null) {
                    $html = $h;
                }
            }
            editor::save_section_html($bi, $cfg, $html);
            nit_edit_respond(true);
            break;

        // About — optional new photo + bullet points, saved together (one POST).
        // Always drops the placeholder "Get in touch" block from the about column.
        case 'about':
            $found = editor::find_section('about');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            [$bi, $cfg] = $found;
            $html = editor::section_html($cfg);
            if (!empty($_FILES['image']['tmp_name'])) {
                $err = null;
                $datauri = editor::uploaded_image_datauri($_FILES['image'], $err);
                if ($datauri === null) {
                    nit_edit_respond(false, ['error' => $err ?? 'image']);
                }
                $h = editor::set_about_image($html, $datauri);
                if ($h === null) {
                    $h = editor::set_marker_background($html, 'about', $datauri);
                }
                if ($h !== null) {
                    $html = $h;
                }
            }
            $rawb = optional_param('bullets', '', PARAM_RAW);
            if ($rawb !== '') {
                $decoded = json_decode($rawb, true);
                if (is_array($decoded)) {
                    $bullets = [];
                    foreach ($decoded as $b) {
                        $b = trim((string) $b);
                        if ($b !== '') {
                            $bullets[] = \core_text::substr($b, 0, 200);
                        }
                        if (count($bullets) >= 8) {
                            break;
                        }
                    }
                    $h = editor::set_about_points($html, $bullets);
                    if ($h !== null) {
                        $html = $h;
                    }
                }
            }
            $html = editor::remove_about_getintouch($html);
            editor::save_section_html($bi, $cfg, $html);
            nit_edit_respond(true);
            break;

        // Replace a section's background image (hero cover, about photo box, …).
        // Reused by the hero (3.2) and about-image (3.3) editors.
        case 'section_bg_image':
            $marker = required_param('section', PARAM_ALPHANUMEXT);
            $found = editor::find_section($marker);
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            $err = null;
            $datauri = editor::uploaded_image_datauri($_FILES['image'] ?? [], $err);
            if ($datauri === null) {
                nit_edit_respond(false, ['error' => $err ?? 'image']);
            }
            [$bi, $cfg] = $found;
            $newhtml = editor::set_marker_background(editor::section_html($cfg), $marker, $datauri);
            if ($newhtml === null) {
                nit_edit_respond(false, ['error' => 'noimgslot']);
            }
            editor::save_section_html($bi, $cfg, $newhtml);
            nit_edit_respond(true);
            break;

        // Adjust a section's width/height constraints (hero band size, etc.).
        // Whitelisted, validated CSS only — never free-form style from the client.
        case 'section_style':
            $marker = required_param('section', PARAM_ALPHANUMEXT);
            $aspect = optional_param('aspect', '', PARAM_RAW_TRIMMED);   // "16/6"
            $minheight = optional_param('minheight', 0, PARAM_INT);       // px
            $props = [];
            if (preg_match('#^\d{1,2}/\d{1,2}$#', $aspect)) {
                $props['aspect-ratio'] = $aspect;
            }
            if ($minheight >= 120 && $minheight <= 1000) {
                $props['min-height'] = $minheight . 'px';
            }
            if (!$props) {
                nit_edit_respond(false, ['error' => 'noprops']);
            }
            $found = editor::find_section($marker);
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            [$bi, $cfg] = $found;
            $newhtml = editor::set_marker_style_props(editor::section_html($cfg), $marker, $props);
            if ($newhtml === null) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            editor::save_section_html($bi, $cfg, $newhtml);
            nit_edit_respond(true);
            break;

        // About section — replace the photo box image (not the section bg).
        case 'about_image':
            $found = editor::find_section('about');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            $err = null;
            $datauri = editor::uploaded_image_datauri($_FILES['image'] ?? [], $err);
            if ($datauri === null) {
                nit_edit_respond(false, ['error' => $err ?? 'image']);
            }
            [$bi, $cfg] = $found;
            $html = editor::section_html($cfg);
            $newhtml = editor::set_about_image($html, $datauri);
            if ($newhtml === null) {
                // Older block without the photo-box marker → fall back to section bg.
                $newhtml = editor::set_marker_background($html, 'about', $datauri);
            }
            if ($newhtml === null) {
                nit_edit_respond(false, ['error' => 'noimgslot']);
            }
            editor::save_section_html($bi, $cfg, $newhtml);
            nit_edit_respond(true);
            break;

        // About section — replace the bullet points (JSON array of strings).
        case 'about_points':
            $found = editor::find_section('about');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            $decoded = json_decode(required_param('bullets', PARAM_RAW), true);
            if (!is_array($decoded)) {
                nit_edit_respond(false, ['error' => 'badbullets']);
            }
            $bullets = [];
            foreach ($decoded as $b) {
                $b = trim((string) $b);
                if ($b !== '') {
                    $bullets[] = \core_text::substr($b, 0, 200);
                }
                if (count($bullets) >= 8) {
                    break;
                }
            }
            [$bi, $cfg] = $found;
            $newhtml = editor::set_about_points(editor::section_html($cfg), $bullets);
            if ($newhtml === null) {
                nit_edit_respond(false, ['error' => 'nolist']);
            }
            editor::save_section_html($bi, $cfg, $newhtml);
            nit_edit_respond(true);
            break;

        // Gallery — full staged rebuild: reorder + delete + add, in one save.
        // `order` is a JSON list of tokens (e:<i> keep existing i / n:<k> new file
        // image<k>); new files arrive as image0, image1, …
        case 'gallery':
            $found = editor::find_section('gallery');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            $order = json_decode(optional_param('order', '[]', PARAM_RAW), true);
            if (!is_array($order)) {
                nit_edit_respond(false, ['error' => 'badorder']);
            }
            $newuris = [];
            for ($k = 0; $k < 24; $k++) {
                if (!empty($_FILES['image' . $k]['tmp_name'])) {
                    $err = null;
                    $d = editor::uploaded_image_datauri($_FILES['image' . $k], $err);
                    if ($d === null) {
                        nit_edit_respond(false, ['error' => $err ?? 'image']);
                    }
                    $newuris[$k] = $d;
                }
            }
            [$bi, $cfg] = $found;
            $h = editor::gallery_rebuild(editor::section_html($cfg), $order, $newuris);
            if ($h === null) {
                nit_edit_respond(false, ['error' => 'nogrid']);
            }
            editor::save_section_html($bi, $cfg, $h);
            nit_edit_respond(true);
            break;

        // Gallery — append an uploaded image as a new tile.
        case 'gallery_add':
            $found = editor::find_section('gallery');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            $err = null;
            $datauri = editor::uploaded_image_datauri($_FILES['image'] ?? [], $err);
            if ($datauri === null) {
                nit_edit_respond(false, ['error' => $err ?? 'image']);
            }
            [$bi, $cfg] = $found;
            $result = editor::gallery_add(editor::section_html($cfg), $datauri);
            if ($result === 'full') {
                nit_edit_respond(false, ['error' => 'full']);
            }
            if ($result === null) {
                nit_edit_respond(false, ['error' => 'nogrid']);
            }
            editor::save_section_html($bi, $cfg, $result);
            nit_edit_respond(true);
            break;

        // Gallery — remove the tile at the given 0-based index.
        case 'gallery_delete':
            $found = editor::find_section('gallery');
            if (!$found) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            $index = required_param('index', PARAM_INT);
            [$bi, $cfg] = $found;
            $newhtml = editor::gallery_delete(editor::section_html($cfg), $index);
            if ($newhtml === null) {
                nit_edit_respond(false, ['error' => 'badindex']);
            }
            editor::save_section_html($bi, $cfg, $newhtml);
            nit_edit_respond(true);
            break;

        // Contact + social — persist to theme_nit config (app) + rebuild the block.
        case 'contact':
            editor::save_contact([
                'phone'     => optional_param('phone', '', PARAM_RAW_TRIMMED),
                'whatsapp'  => optional_param('whatsapp', '', PARAM_RAW_TRIMMED),
                'facebook'  => optional_param('facebook', '', PARAM_RAW_TRIMMED),
                'instagram' => optional_param('instagram', '', PARAM_RAW_TRIMMED),
                'youtube'   => optional_param('youtube', '', PARAM_RAW_TRIMMED),
                'tiktok'    => optional_param('tiktok', '', PARAM_RAW_TRIMMED),
                'website'   => optional_param('website', '', PARAM_RAW_TRIMMED),
            ]);
            nit_edit_respond(true);
            break;

        // Footer — set academy name + description, optionally show the logo.
        case 'footer':
            $name = optional_param('name', '', PARAM_TEXT);
            $desc = optional_param('desc', '', PARAM_TEXT);
            $showlogo = optional_param('showlogo', 0, PARAM_BOOL);
            if (!editor::save_footer($name, $desc, $showlogo)) {
                nit_edit_respond(false, ['error' => 'notfound']);
            }
            nit_edit_respond(true);
            break;

        // Brand palette — 6 pickers → Group-1 roles + derived roles + recompile.
        // Persist + bump the theme revision synchronously (fast), then kick off
        // the ~7s SCSS recompile in a DETACHED background process and return ok
        // immediately, so the Save button never hangs. The live editor already
        // shows the new palette via CSS custom properties, so the admin sees the
        // result instantly; the background compile just warms the stylesheet for
        // later page loads / non-editing visitors.
        case 'palette':
            $ok = editor::save_palette([
                'primary'    => optional_param('primary', '', PARAM_RAW_TRIMMED),
                'accent'     => optional_param('accent', '', PARAM_RAW_TRIMMED),
                'secondary'  => optional_param('secondary', '', PARAM_RAW_TRIMMED),
                'background' => optional_param('background', '', PARAM_RAW_TRIMMED),
                'surface'    => optional_param('surface', '', PARAM_RAW_TRIMMED),
                'text'       => optional_param('text', '', PARAM_RAW_TRIMMED),
            ], false); // defer the compile — spawned below
            if (!$ok) {
                nit_edit_respond(false, ['error' => 'badcolor']);
            }
            editor::spawn_prewarm();
            nit_edit_respond(true);
            break;

        // Replace the site logo (core_admin site file; old file really deleted).
        // One upload drives BOTH the navbar compact logo and the full logo, the
        // same as provisioning's brand applier.
        case 'logo':
            $err = null;
            $file = $_FILES['image'] ?? [];
            if (!editor::replace_site_file('logo', 'logo', $file, $err)) {
                nit_edit_respond(false, ['error' => $err ?? 'image']);
            }
            // Best-effort: mirror to the compact logo used in the navbar.
            editor::replace_site_file('logocompact', 'logocompact', $file, $err);
            editor::bust_theme_caches();
            nit_edit_respond(true);
            break;

        default:
            nit_edit_respond(false, ['error' => 'unknownaction']);
    }
} catch (\Throwable $e) {
    nit_edit_respond(false, ['error' => 'exception', 'detail' => $e->getMessage()]);
}
