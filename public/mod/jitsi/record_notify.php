<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later
// version. See <http://www.gnu.org/licenses/>.

/**
 * Server-to-server endpoint, called by the Jibri finalize script after a session
 * recording finishes. Receives the finished .mp4, uploads it to Vimeo (via
 * local_vimeo — reusing its tus upload + domain whitelist), and stores the Vimeo
 * video id against the Jitsi activity so view.php can play it.
 *
 * POST /mod/jitsi/record_notify.php   (multipart/form-data)
 *   header X-Notify-Key: <local_academysessions/jibri_notify_key>
 *   fields cmid=<int>  title=<text>  file=@recording.mp4
 *
 * No Moodle session — background call, authenticated by the shared notify key.
 * NOTE: for large recordings the academy's PHP upload_max_filesize / post_max_size
 * must be large enough (Jibri POSTs the file server-to-server on the same host).
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);

$_wwwroot = getenv('MOODLE_WWWROOT') ?: '';
if ($_wwwroot !== '' && ($_p = parse_url($_wwwroot)) && !empty($_p['host'])) {
    $_SERVER['HTTP_HOST']   = $_p['host'];
    $_SERVER['HTTPS']       = 'on';
    $_SERVER['SERVER_PORT'] = '443';
}
unset($_wwwroot, $_p);

require(__DIR__ . '/../../config.php');
global $CFG, $DB;

header('Content-Type: application/json');

// ── Auth: shared notify key ─────────────────────────────────────────────────
$notify_key   = get_config('local_academysessions', 'jibri_notify_key') ?: 'academy-cron-2024';
$provided_key = $_SERVER['HTTP_X_NOTIFY_KEY'] ?? optional_param('key', '', PARAM_RAW_TRIMMED);
if (!is_string($provided_key) || !hash_equals($notify_key, $provided_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Params ──────────────────────────────────────────────────────────────────
$cmid  = required_param('cmid', PARAM_INT);
$title = optional_param('title', '', PARAM_TEXT);

$cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, instance', IGNORE_MISSING);
if (!$cm) {
    http_response_code(404);
    echo json_encode(['error' => 'unknown cmid']);
    exit;
}
if ($title === '') {
    $title = get_string('recording', 'jitsi') . ' ' . userdate(time(), '%Y-%m-%d %H:%M');
}

// ── The uploaded recording file ─────────────────────────────────────────────
if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'no file']);
    exit;
}
$tmpfile = $_FILES['file']['tmp_name'];

// ── Upload to Vimeo (reuses local_vimeo's tus upload + domain whitelist) ─────
if (!class_exists('\local_vimeo\api_client') || !\local_vimeo\api_client::is_configured()) {
    http_response_code(503);
    echo json_encode(['error' => 'vimeo not configured']);
    exit;
}
try {
    $client  = new \local_vimeo\api_client();
    $videoid = $client->upload($tmpfile, $title);   // server-side tus, returns the Vimeo id
    // Whitelist THIS academy's domain so the private embed plays here.
    $domain = (string) parse_url($CFG->wwwroot, PHP_URL_HOST);
    if ($domain !== '') {
        try {
            $client->whitelist_domain($videoid, $domain);
        } catch (\Throwable $e) {
            // Non-fatal: autowhitelist may already cover it, or admin sets it later.
        }
    }
} catch (\Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'vimeo upload failed', 'detail' => $e->getMessage()]);
    exit;
}

// ── Link to a live session if this activity has one (optional) ───────────────
$sessionid = null;
$jitsi = $DB->get_record('jitsi', ['id' => $cm->instance], 'id');
if ($jitsi) {
    $sess = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id], 'id');
    if ($sess) {
        $sessionid = (int) $sess->id;
    }
}

// ── Store the recording row ─────────────────────────────────────────────────
$existing = $DB->get_record('academy_session_recordings', ['vimeo_videoid' => $videoid], 'id');
if ($existing) {
    $DB->update_record('academy_session_recordings', (object) [
        'id' => $existing->id, 'status' => 'ready', 'title' => $title,
        'cmid' => $cmid, 'sessionid' => $sessionid, 'timemodified' => time(),
    ]);
    $recid = $existing->id;
} else {
    $recid = $DB->insert_record('academy_session_recordings', (object) [
        'vimeo_videoid' => $videoid,
        'cmid'          => $cmid,
        'sessionid'     => $sessionid,
        'title'         => $title,
        'status'        => 'ready',
        'timecreated'   => time(),
        'timemodified'  => time(),
    ]);
}

echo json_encode(['success' => true, 'id' => $recid, 'vimeo_videoid' => $videoid]);
