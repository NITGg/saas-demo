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
 * Instant lessons for the web pages (js/instant.js): JSON in, JSON out, with the browser session
 * and sesskey. The mobile app uses the same actions through api.php (function=instant_<action>).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_nit_lessons\api\instant;

require_login(null, false);
require_sesskey();
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHA);
try {
    if (isguestuser() || !local_nit_flex_enabled() || !in_array($action, instant::ACTIONS, true)) {
        throw new \local_nit_lessons\exception\lesson_exception('err_forbidden');
    }
    if ($action === 'request') {
        require_capability('local/nit_lessons:request', context_system::instance());
    }
    $data = instant::act((int) $USER->id, $action, [
        'subject' => optional_param('subject', '', PARAM_TEXT),
        'note' => optional_param('note', '', PARAM_TEXT),
        'requestid' => optional_param('requestid', 0, PARAM_INT),
    'photo' => !empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])
        ? $_FILES['photo'] : null, // Optional photo of the problem (request).
    ]);
    echo json_encode(['ok' => true, 'data' => $data]);
} catch (\moodle_exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->errorcode, 'message' => $e->getMessage()]);
}
