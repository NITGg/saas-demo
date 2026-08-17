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
 * Public JSON API for the NIT colour palette.
 *
 * Returns the site's live colour palette so external clients — the Flutter /
 * mobile app in particular — can sync their theme with the web brand. The
 * colours are public branding (already visible in the site's CSS), so no login
 * is required; the endpoint is read-only and exposes nothing sensitive.
 *
 * Response shape:
 * {
 *   "generated": 1712345678,
 *   "colours": { "primary": "#2a50c8", "accentgold": "#e8b84b", ... },
 *   "tokens": [ { "key": "primary", "group": "Brand", "label": "Primary",
 *                 "value": "#2a50c8", "default": "#2a50c8", "iscustom": false }, ... ]
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

$tokens = theme_nit_colours_all();

// Flat key => value map (the easy path for a client applying the palette).
$flat = [];
foreach ($tokens as $token) {
    $flat[$token['key']] = $token['value'];
}

$payload = [
    'generated' => time(),
    'colours' => $flat,
    'tokens' => $tokens,
];

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
