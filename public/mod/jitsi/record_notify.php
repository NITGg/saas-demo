<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later
// version. See <http://www.gnu.org/licenses/>.

/**
 * Server-to-server notification, called by the Jibri finalize script after it
 * uploads a finished recording to VdoCipher. Stores the VdoCipher video id
 * against the Jitsi activity (cmid) so view.php can show the recording.
 *
 * POST /mod/jitsi/record_notify.php
 *   header X-Notify-Key: <local_academysessions/jibri_notify_key>
 *   body   cmid=<int>&vdocipher_videoid=<id>&title=<text>
 *
 * No Moodle session — background call, authenticated by the shared notify key.
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);

// finalize.sh reaches us over the academy's public HTTPS host; make setup.php
// see a valid HTTPS request even if a proxy/user-agent differs.
$_wwwroot = getenv('MOODLE_WWWROOT') ?: '';
if ($_wwwroot !== '' && ($_p = parse_url($_wwwroot)) && !empty($_p['host'])) {
    $_SERVER['HTTP_HOST']   = $_p['host'];
    $_SERVER['HTTPS']       = 'on';
    $_SERVER['SERVER_PORT'] = '443';
}
unset($_wwwroot, $_p);

require(__DIR__ . '/../../config.php');

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
$videoid = required_param('vdocipher_videoid', PARAM_ALPHANUMEXT);
$cmid    = required_param('cmid', PARAM_INT);
$title   = optional_param('title', '', PARAM_TEXT);

// Validate the activity exists.
$cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, instance', IGNORE_MISSING);
if (!$cm) {
    http_response_code(404);
    echo json_encode(['error' => 'unknown cmid']);
    exit;
}
if ($title === '') {
    $title = get_string('recording', 'jitsi') . ' ' . userdate(time(), '%Y-%m-%d %H:%M');
}

// Link to an academy_live_session if this activity has one (optional).
$sessionid = null;
$jitsi = $DB->get_record('jitsi', ['id' => $cm->instance], 'id');
if ($jitsi) {
    $sess = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id], 'id');
    if ($sess) {
        $sessionid = (int) $sess->id;
    }
}

// ── Upsert by VdoCipher video id ────────────────────────────────────────────
$existing = $DB->get_record('academy_session_recordings', ['vdocipher_videoid' => $videoid], 'id');
if ($existing) {
    $DB->update_record('academy_session_recordings', (object) [
        'id' => $existing->id, 'status' => 'ready', 'title' => $title,
        'cmid' => $cmid, 'sessionid' => $sessionid, 'timemodified' => time(),
    ]);
    $recid = $existing->id;
} else {
    $recid = $DB->insert_record('academy_session_recordings', (object) [
        'vdocipher_videoid' => $videoid,
        'cmid'         => $cmid,
        'sessionid'    => $sessionid,
        'title'        => $title,
        'status'       => 'ready',
        'timecreated'  => time(),
        'timemodified' => time(),
    ]);
}

echo json_encode(['success' => true, 'id' => $recid]);
