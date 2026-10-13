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
 * Meta's WhatsApp webhook for this academy's number.
 * GET: Meta's verification handshake (hub.verify_token = the verify token in the settings).
 * POST: incoming messages. Unsigned or wrongly signed calls get 401 and nothing is stored;
 * a message id seen before is ignored; Meta gets 200 at once and the reply follows.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Meta calls this; the request signature is checked instead.
require(__DIR__ . '/../../config.php');

use local_nit_assistant\whatsapp\cloud;
use local_nit_assistant\whatsapp\inbound;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $expected = trim((string) get_config('local_nit_assistant', 'wa_verifytoken'));
    $mode = optional_param('hub_mode', '', PARAM_ALPHA);
    $token = optional_param('hub_verify_token', '', PARAM_RAW);
    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        header('Content-Type: text/plain');
        echo clean_param(optional_param('hub_challenge', '', PARAM_RAW), PARAM_ALPHANUMEXT);
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$raw = (string) file_get_contents('php://input');
if (!cloud::verify_signature($raw, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'Bad JSON';
    exit;
}

$ours = (string) get_config('local_nit_assistant', 'wa_phonenumberid');
$todo = [];
foreach (cloud::parse_webhook($payload) as $m) {
    // Only this academy's number (a WhatsApp account can hold several).
    if ($ours !== '' && $m['phonenumberid'] !== null && $m['phonenumberid'] !== $ours) {
        continue;
    }
    if (inbound::claim($m['id'])) {
        $todo[] = $m;
    }
}

// Answer Meta now (it retries slow webhooks), then write the replies.
ignore_user_abort(true);
core_php_time_limit::raise(300);
http_response_code(200);
header('Content-Type: text/plain');
header('Connection: close');
header('Content-Length: 2');
echo 'OK';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

$handler = new inbound();
foreach ($todo as $m) {
    try {
        $outcome = $handler->process($m);
        debugging('local_nit_assistant: WhatsApp …' . substr($m['from'], -4) . ': ' . $outcome, DEBUG_DEVELOPER);
    } catch (\Throwable $e) {
        debugging('local_nit_assistant: WhatsApp message failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
