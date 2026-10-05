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
 * Course catalogue JSON API for the mobile app (the web page is index.php).
 *
 *   GET /local/nit_category/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * Any valid token works — including the shared pre-login token (getsettings.php
 * → admin_token / user_token), which the app uses for anonymous browsing. With a
 * shared token the caller is treated as a guest: hidden courses/categories are
 * never listed and enrolment / subscription state is always false.
 *
 * Functions: get_categories, get_courses. Optional `lang=ar|en`.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_category\catalogue;

api::boot();
$USER = api::authenticate();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

// The shared pre-login tokens browse as a guest (no personal state, public courses only).
$token = api::token();
$shared = array_filter([
    (string) get_config('local_multitopics', 'admin_token'),
    (string) get_config('local_multitopics', 'user_token'),
]);
$anonymous = in_array($token, $shared, true);
$userid = $anonymous ? 0 : (int) $USER->id;

api::run(function (string $function) use ($userid, $anonymous) {
    switch ($function) {
        // The category tree with recursive course counts.
        case 'get_categories':
            return [
                'anonymous'  => $anonymous,
                'categories' => catalogue::category_tree($anonymous),
            ];

        // The catalogue: same filters as the web page + search + paging.
        case 'get_courses':
            [$page, $perpage] = api::paging(20, 50);
            $params = [
                'categoryid' => optional_param('categoryid', optional_param('id', 0, PARAM_INT), PARAM_INT),
                'recursive'  => optional_param('recursive', 1, PARAM_BOOL),
                'q'          => optional_param('q', '', PARAM_TEXT),
                'sort'       => optional_param('sort', 'recommended', PARAM_ALPHA),
                'price'      => optional_param('price', 'all', PARAM_ALPHA),
                'level'      => optional_param('level', '', PARAM_TEXT),
                'rating'     => optional_param('rating', 0, PARAM_INT),
            ];
            $data = catalogue::search($params, $page, $perpage, $userid, $anonymous,
                static fn(string $url): string => api::file_url($url));
            return ['anonymous' => $anonymous] + $data;
    }
    return api::unknown();
});
