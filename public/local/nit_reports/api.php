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
 * Teacher reports for the mobile app (token API).
 *
 *   GET /local/nit_reports/api.php?function=<name>&token=<wstoken>[&lang=ar|en]&...
 *   → {"status":"success","data":...} | {"status":"fail","error":"...","errorcode":"..."}
 *
 * The same reports, scope and filters as /local/nit_reports/index.php — only the
 * "teaching" ones (see \local_nit_reports\mobile_api). See docs/MOBILE_API_V5.md.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_reports\filters;
use local_nit_reports\mobile_api;

api::boot();
$USER = api::authenticate();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}
$PAGE->set_context(context_system::instance());

api::run(function (string $function) use ($USER) {
    switch ($function) {
        // The reports this teacher may open.
        case 'get_reports':
            return mobile_api::list((int) $USER->id);

        // One report: filters + options, number cards, columns and a page of rows.
        case 'get_report':
            $key = required_param('report', PARAM_ALPHANUMEXT);
            [$page, $perpage] = api::paging(mobile_api::PERPAGE, mobile_api::MAXPERPAGE);
            return mobile_api::get((int) $USER->id, $key, filters::from_request(), $page, $perpage);
    }
    return api::unknown();
});
