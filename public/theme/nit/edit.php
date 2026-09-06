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
