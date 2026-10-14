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
 * The chat's JSON calls.
 *
 * Mobile app (`function=…`, token-authenticated, the academy's usual app API envelope):
 * get_config, send_message, get_messages, new_conversation — see \local_nit_assistant\chat\app_api
 * and docs/apis/ai-assistant-mobile-api.md.
 *
 * Website widget (`action=…`, sesskey on every call):
 * - action=history&key=&after= : the caller's messages after an id, and the conversation status
 * - action=reset&key=          : close the conversation (POST) so the next message starts a new one
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The app calls with `function` and a token, without cookies; the website widget with `action` and its session.
$isapp = isset($_GET['function']) || isset($_POST['function']); // phpcs:ignore moodle.Files.MoodleInternal
if ($isapp) {
    define('NO_MOODLE_COOKIES', true);
} else {
    define('AJAX_SCRIPT', true);
}
define('NO_DEBUG_DISPLAY', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- visitors chat signed out; access::userid() decides who may.
require(__DIR__ . '/../../config.php');

use local_nit_assistant\chat\access;
use local_nit_assistant\chat\conversation;

if ($isapp) {
    $PAGE->set_context(context_system::instance());
    \local_nit_assistant\chat\app_api::handle();
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_assistant/api.php'));
require_sesskey();

$userid = access::userid();
if ($userid === null) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_nit_assistant'));
}
$action = required_param('action', PARAM_ALPHA);
$key = optional_param('key', '', PARAM_ALPHANUM) ?: null;

\core\session\manager::write_close();
header('Content-Type: application/json; charset=utf-8');

if ($action === 'history') {
    $after = optional_param('after', 0, PARAM_INT);
    $conv = conversation::find($userid, $key);
    echo json_encode([
        'status' => $conv ? $conv->status : null,
        'messages' => $conv ? conversation::messages((int) $conv->id, $after) : [],
    ], JSON_UNESCAPED_UNICODE);
} else if ($action === 'reset') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    echo json_encode(['closed' => conversation::close($userid, $key)]);
} else {
    throw new moodle_exception('invalidparameter', 'debug');
}
