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
 * Public JSON API for the NIT design system.
 *
 * Mirrors the four tabs of the design-system gallery (theme/nit/gallery.php) as
 * data, so external clients — the Flutter / mobile app in particular — can sync
 * their theme with the web brand. Everything returned is public branding
 * (already visible in the site's CSS / DOM); no login is required, the endpoint
 * is read-only and exposes nothing sensitive. Category listing is
 * visibility-aware, so an anonymous request sees only guest-visible categories.
 *
 * Response shape:
 * {
 *   "generated": 1712345678,
 *   "site": { "name": "NIT Academy", "url": "https://…" },
 *   "brandcolors": {                        // Tab 1 — Brand Colors
 *     "roles": ["primary", "secondary", …], // role keys shared by every group
 *     "groups": [
 *       { "key": "g1", "name": "Group 1", "isdefault": true, "class": "",
 *         "roles": [
 *           { "key": "g1_primary", "role": "primary", "label": "Primary",
 *             "cssvar": "--nit-brand-primary", "value": "#e5322d",
 *             "default": "#e5322d", "iscustom": false,
 *             "usage": ["background main button", …] }, … ] }, … ]
 *   },
 *   "categorystyles": {                     // Tab 2 — Category styles
 *     "groups": [ { "key": "g1", "name": "Group 1" }, … ],
 *     "categories": [
 *       { "id": 3, "name": "Programming", "group": "g2", "groupname": "Group 2",
 *         "class": "nit-brand-2", "isdefault": false }, … ]
 *   },
 *   "fonts": [                              // Tab 3 — Fonts
 *     { "lang": "en", "label": "English font", "family": "NIT Site Font EN",
 *       "rtl": false, "fallback": "…", "hasfont": true, "filename": "font-en.ttf",
 *       "url": "https://…/pluginfile.php/…/font-en.ttf" }, … ]
 *   "components": [                         // Tab 4 — Components
 *     { "name": "Buttons", "variants": [ { "label": "Primary",
 *       "class": "btn btn-primary" }, … ] }, … ]
 * }
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Lightweight, session-less request — this is public, cacheable branding data.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$payload = theme_nit_design_system_export();

// Public branding data: allow cross-origin reads (e.g. the mobile app) and a
// short cache so repeated fetches are cheap.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=300');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    // CORS pre-flight — headers above are enough.
    exit;
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
