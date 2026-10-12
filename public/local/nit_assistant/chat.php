<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Send a chat message; the reply streams back as server-sent events
 * (event: stored | delta | action | handoff | queued | done | error).
 *
 * POST: sesskey, message, key (the browser's visitor key), page (local path).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_OUTPUT_BUFFERING', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- visitors chat signed out; access::userid() decides who may.
require(__DIR__ . '/../../config.php');

use local_nit_assistant\chat\access;
use local_nit_assistant\chat\conversation;
use local_nit_assistant\chat\service;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_assistant/chat.php'));

$emit = static function (string $event, array $data): void {
    echo 'event: ' . $event . "\n" . 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
};

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !confirm_sesskey()) {
    $emit('error', ['code' => 'session']);
    exit;
}
$userid = access::userid();
if ($userid === null) {
    $emit('error', ['code' => 'denied']);
    exit;
}

$message = required_param('message', PARAM_RAW);
$key = optional_param('key', '', PARAM_ALPHANUM);
$page = optional_param('page', '', PARAM_LOCALURL);
if ($userid === 0 && !conversation::valid_key($key)) {
    $emit('error', ['code' => 'session']);
    exit;
}

$ip = getremoteaddr();
$sender = $userid > 0 ? 'u' . $userid : 'ip' . conversation::hash((string) $ip);
if (!service::allow($sender)) {
    $emit('error', ['code' => 'ratelimited']);
    exit;
}

$conv = conversation::find_or_create($userid, $key ?: null, [
    'channel' => 'web', 'lang' => current_language(), 'mode' => access::mode($userid), 'ip' => $ip, 'page' => $page,
]);

// The reply can take a while: free the session for the user's other tabs, and finish
// (and save) the reply even if the browser goes away.
\core\session\manager::write_close();
ignore_user_abort(true);
core_php_time_limit::raise(120);

(new service())->send($conv, $message, $emit, $page ?: null);
