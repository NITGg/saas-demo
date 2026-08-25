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
 * Public mobile-app settings endpoint (white-label bootstrap).
 *
 *   GET /local/multitopics/getsettings.php
 *   → { "data": { … } }
 *
 * Read by the Flutter app BEFORE login, per academy, to learn its tokens, the
 * shared Google client id, and its feature flags. Anonymous and read-only — it
 * must never be sent credentials. The response shape matches what the app already
 * parses (AppSettings.load), so keep every key.
 *
 * WHERE VALUES COME FROM
 * ----------------------
 * Most values are plugin config under the `local_multitopics` component, set per
 * academy by provisioning or the tenant settings page, e.g.:
 *     php admin/cli/cfg.php --component=local_multitopics --name=admin_token --set=…
 * A few are sourced from the plugin that owns them (watermark ← local_vdocipher)
 * or from the licence (social-login flags ← local_license), so they stay
 * consistent with the rest of the system and can't drift.
 *
 * SECURITY
 * --------
 * - `google_client_id` MUST be the same value for every academy — the OAuth client
 *   is bound to the app's bundle id, not the site.
 * - `admin_token` is the shared pre-login (registration) service token; it is
 *   returned anonymously by design. Rotate the EAAC value that lives in git
 *   history once this endpoint is live.
 * - Response is marked no-store: tokens must not be cached by proxies. The app
 *   caches client-side on its own.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

// ── Helpers: read local_multitopics config with a typed default ──────────────
/** @return string */
function ms_str(string $name, string $default = ''): string {
    $v = get_config('local_multitopics', $name);
    return ($v === false || $v === null || $v === '') ? $default : (string) $v;
}
/** @return bool */
function ms_bool(string $name, bool $default = false): bool {
    $v = get_config('local_multitopics', $name);
    return ($v === false || $v === null || $v === '') ? $default : (bool) (int) $v;
}
/** @return int */
function ms_int(string $name, int $default = 0): int {
    $v = get_config('local_multitopics', $name);
    return ($v === false || $v === null || $v === '') ? $default : (int) $v;
}
/** Read another plugin's config, tolerant of it being absent. */
function ms_other(string $component, string $name, $default = null) {
    $v = get_config($component, $name);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}
/**
 * Read a local_multitopics string, returning NULL when unset. The app reads
 * null as "not configured" and falls back to its compiled default; an empty
 * string "" would instead be treated as a real (empty) value, so tokens/URLs
 * must be null-when-unset, never "".
 * @return string|null
 */
function ms_null(string $name): ?string {
    $v = get_config('local_multitopics', $name);
    return ($v === false || $v === null || $v === '') ? null : (string) $v;
}

// ── Watermark ← local_vdocipher (the plugin that owns it) ─────────────────────
$watermarkenabled = (bool) (int) ms_other('local_vdocipher', 'watermarkenabled', ms_bool('watermark', false));
$watermarktext    = (string) ms_other('local_vdocipher', 'watermarktext', ms_str('watermark_text', '{fullname}'));

// Overlay style. The app parses `int.parse('0xff'.$color)` with NO error
// handling, so the colour must be exactly 6 hex digits and MUST NOT carry a
// leading '#' (a '#' throws a FormatException that breaks watermarking for the
// whole session). Strip any '#' and fall back to white on a bad value.
$watermarkcolor = strtolower(ltrim(ms_str('watermark_color', 'ffffff'), '#'));
if (!preg_match('/^[0-9a-f]{6}$/', $watermarkcolor)) {
    $watermarkcolor = 'ffffff';
}
$watermarkspeed    = ms_str('watermark_speed', '0.002');
$watermarkfontsize = ms_str('watermark_fontsize', '14');

// WhatsApp prefilled text is interpolated raw into a wa.me URL by the app, so it
// must be percent-encoded. Encode here unless it already looks encoded (so an
// admin who pasted encoded text isn't double-encoded).
$whatsappmessage = ms_null('whatsapp_message');
if ($whatsappmessage !== null && !preg_match('/%[0-9A-Fa-f]{2}/', $whatsappmessage)) {
    $whatsappmessage = rawurlencode($whatsappmessage);
}

// ── Social-login flags ← local_license (licence output, consistent with features)
// Falls back to local_multitopics config if the licence plugin/method is absent.
$google_login   = ms_bool('google_login', true);
$apple_login    = ms_bool('apple_login', false);
$facebook_login = ms_bool('facebook_login', false);
if (class_exists('\local_license\license') && method_exists('\local_license\license', 'mobile_features')) {
    $f = \local_license\license::mobile_features();
    $google_login   = $f['google_login']   ?? $google_login;
    $apple_login    = $f['apple_login']    ?? $apple_login;
    $facebook_login = $f['facebook_login'] ?? $facebook_login;
}

// ── Video source ← local_license (all|limited|youtube|vimeo|vdocipher) ────────
// The app uses this to restrict which external video hosts it will play — the one
// video rule Moodle can't enforce itself (YouTube/Vimeo share the same module).
$video_source = 'all';
if (class_exists('\local_license\license') && method_exists('\local_license\license', 'video_source')) {
    $video_source = \local_license\license::video_source();
}

// ── The payload the app expects (AppSettings) ────────────────────────────────
// Natural types here; the normaliser below coerces every value to the app's
// contract (all strings, null = "not configured").
$data = [
    // Tokens — per academy. Set by provisioning / tenant settings page.
    'user_token'  => ms_null('user_token'),
    'admin_token' => ms_null('admin_token'),

    // OAuth — MUST be identical for every academy (bound to the app bundle id).
    'google_client_id' => ms_null('google_client_id'),

    // Media / DRM.
    'prevent_screen_recording' => ms_bool('prevent_screen_recording', true),
    'watermark'                => $watermarkenabled,
    'watermark_text'           => $watermarktext,
    'watermark_color'          => $watermarkcolor,     // 6 hex, NO '#'
    'watermark_speed'          => $watermarkspeed,
    'watermark_fontsize'       => $watermarkfontsize,
    'videourl'                 => ms_null('videourl'),
    'video_source'             => $video_source,

    // Contact / WhatsApp FAB.
    'whatsapp_phone'   => ms_null('whatsapp_phone'),
    'whatsapp_message' => $whatsappmessage,

    // Payments.
    'allow_paymob' => ms_bool('allow_paymob', false),

    // Force-update gate.
    'android_version' => ms_null('android_version'),
    'android_url'     => ms_null('android_url'),
    'ios_version'     => ms_null('ios_version'),
    'ios_url'         => ms_null('ios_url'),

    // Social login availability.
    'google_login'   => $google_login,
    'apple_login'    => $apple_login,
    'facebook_login' => $facebook_login,

    // Networking — MINUTES (app default 10 when absent).
    'server_timeout_duration' => ms_int('server_timeout_duration', 30),
];

// ── CONTRACT: every value must be a JSON string (or null) ─────────────────────
// The Flutter client's fields are all `String?` and assigned straight from the
// decoded map; a single JSON boolean or number triggers a TypeError that is
// swallowed and DISCARDS THE ENTIRE PAYLOAD (every setting becomes null). So
// coerce booleans to "1"/"0" and numbers to their decimal string; leave null
// untouched (null legitimately means "not configured — fall back").
foreach ($data as $k => $v) {
    if ($v === null) {
        continue;
    }
    if (is_bool($v)) {
        $data[$k] = $v ? '1' : '0';
    } else if (is_int($v) || is_float($v)) {
        $data[$k] = (string) $v;
    } else if (!is_string($v)) {
        // Never emit an array/object here — drop it rather than break the payload.
        $data[$k] = null;
    }
}

// ── Output — anonymous JSON, no proxy caching (tokens), CORS for the app ──────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit; // CORS pre-flight.
}

echo json_encode(['data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
