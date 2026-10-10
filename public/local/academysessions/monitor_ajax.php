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
 * The live monitoring wall's refresh (amd/src/monitor.js). Session + sesskey.
 *
 * GET action=wall + the page's filters → {status, wall} (the template context of
 *     local_academysessions/monitor_cards)
 * GET action=detail&sessionid=N → {status, detail} (local_academysessions/monitor_detail)
 *
 * The same scope as the page: an admin sees every lesson, a category / course
 * manager their courses only.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

use local_academysessions\monitor;

require_login(null, false);
require_sesskey();
header('Content-Type: application/json; charset=utf-8');

$scope = monitor::course_scope((int) $USER->id);
if ($scope === []) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => get_string('nopermissions', 'error', get_string('monitor', 'local_academysessions'))]);
    exit;
}

$action = required_param('action', PARAM_ALPHA);
if ($action === 'wall') {
    $filters = monitor::filters_from_request();
    if ($scope !== null && $filters->courseid && !in_array($filters->courseid, $scope, true)) {
        $filters->courseid = 0;
    }
    $wall = monitor::wall($filters, $scope);
    $wall['empty'] = !$wall['cards'];
    $wall['updated'] = userdate(time(), get_string('monitor_strftimeseconds', 'local_academysessions'));
    echo json_encode(['status' => 'ok', 'wall' => $wall]);
    exit;
}

if ($action === 'detail') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $detail = monitor::detail($sessionid, $scope);
    if (!$detail) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'error' => get_string('err_sessionnotfound', 'local_academysessions')]);
        exit;
    }
    // Opening one lesson is logged once per open (the panel's refreshes pass logged=1).
    if (!optional_param('logged', 0, PARAM_BOOL)) {
        \local_academysessions\event\monitor_viewed::for_session($sessionid)->trigger();
    }
    $detail['updated'] = userdate(time(), get_string('monitor_strftimeseconds', 'local_academysessions'));
    echo json_encode(['status' => 'ok', 'detail' => $detail]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'error' => 'Unknown action']);
