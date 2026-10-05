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
 * Parent dashboard — JSON API for the mobile app.
 *
 *   POST /local/parent/api.php?function=get_child_report&token=<wstoken>
 *        studentphone=…&parentphone=…
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * PRE-LOGIN: parents have no account. The app sends the shared registration
 * token (getsettings.php → admin_token); any valid token is accepted, but the
 * token's user is NOT the parent and grants nothing — access comes only from
 * the phone pair, checked exactly like the web page (index.php), with the same
 * per-IP throttle.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_parent\dashboard;

api::boot();
$USER = api::authenticate();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

api::run(function (string $function) {
    switch ($function) {
        // The child's courses → lectures → videos / homework / exams.
        case 'get_child_report':
            api::require_post();
            return dashboard::child_report(
                optional_param('studentphone', '', PARAM_TEXT),
                optional_param('parentphone', '', PARAM_TEXT),
                getremoteaddr());
    }
    return api::unknown();
});
