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
 * Lesson packages (Flex) token-authenticated JSON API for the mobile app.
 *
 *   GET|POST /local/nit_flex/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * HTTP 401 = missing/dead token, 403 = academy suspended or expired. State-changing calls
 * require POST. Optional `lang=ar|en`. Amounts are sent in pounds and returned as
 * `<name>_minor` + `<name>` + `currency`. The logic lives in \local_nit_flex\local\mobile_api.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_academy\api\endpoint as api;
use local_nit_flex\local\mobile_api as flexapi;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

api::run(function (string $function) use ($userid) {
    if (!local_nit_flex_enabled()) {
        throw new \local_nit_flex\exception\flex_exception('err_featureunavailable');
    }
    if (strpos($function, 'admin_') === 0) {
        api::require_capability('local/nit_flex:managepackages');
    }

    switch ($function) {
        // ── Student ──────────────────────────────────────────────────────────

        // Packages for sale (+ active package, wallet balance, online payment on/off).
        case 'get_packages':
            return flexapi::get_packages($userid);

        // Price with an optional coupon, and whether wallet / online payment is possible.
        case 'get_package_quote':
            return flexapi::get_package_quote($userid, required_param('packageid', PARAM_INT),
                optional_param('coupon_code', '', PARAM_TEXT));

        // Buy with the student wallet.
        case 'buy_package_wallet':
            api::require_post();
            api::require_capability('local/nit_flex:purchase');
            return flexapi::buy_package_wallet($userid, required_param('packageid', PARAM_INT),
                optional_param('coupon_code', '', PARAM_TEXT));

        // Start an online payment → open checkout_url → poll get_package_checkout_status.
        case 'create_package_checkout':
            api::require_post();
            api::require_capability('local/nit_flex:purchase');
            return flexapi::create_package_checkout($userid, required_param('packageid', PARAM_INT),
                optional_param('coupon_code', '', PARAM_TEXT), current_language());

        case 'get_package_checkout_status':
            return flexapi::get_package_checkout_status($userid, required_param('order_id', PARAM_RAW_TRIMMED));

        case 'get_my_flex':
            return flexapi::get_my_flex($userid);

        case 'get_my_packages':
            return flexapi::get_my_packages($userid);

        case 'get_package_payments':
            return flexapi::get_package_payments($userid);

        case 'get_flex_history':
            [$page, $perpage] = api::paging(50, 200);
            return flexapi::get_flex_history($userid, $page, $perpage);

        // ── Admin (local/nit_flex:managepackages) ───────────────────────────

        case 'admin_list_packages':
            return flexapi::admin_list_packages();

        case 'admin_save_package':
            api::require_post();
            return flexapi::admin_save_package(optional_param('id', 0, PARAM_INT), [
                'name_ar' => optional_param('name_ar', '', PARAM_TEXT),
                'name_en' => optional_param('name_en', '', PARAM_TEXT),
                'description_ar' => optional_param('description_ar', '', PARAM_TEXT),
                'description_en' => optional_param('description_en', '', PARAM_TEXT),
                'flex_count' => required_param('flex_count', PARAM_INT),
                'price' => required_param('price', PARAM_RAW_TRIMMED),
                'expiration_days' => optional_param('expiration_days', 0, PARAM_INT),
                'active' => optional_param('active', 1, PARAM_BOOL),
            ]);

        case 'admin_set_package_status':
            api::require_post();
            return flexapi::admin_set_package_status(required_param('id', PARAM_INT),
                (bool) required_param('active', PARAM_BOOL));

        case 'admin_delete_package':
            api::require_post();
            return flexapi::admin_delete_package(required_param('id', PARAM_INT));

        case 'admin_assign_package':
            api::require_post();
            return flexapi::admin_assign_package($userid, required_param('studentid', PARAM_INT),
                required_param('packageid', PARAM_INT), optional_param('amount', '', PARAM_RAW_TRIMMED),
                optional_param('method', 'offline', PARAM_ALPHA), optional_param('reference', '', PARAM_TEXT));

        case 'admin_list_purchases':
            [$page, $perpage] = api::paging(50, 200);
            return flexapi::admin_list_purchases($page, $perpage);

        case 'admin_unassign_package':
            api::require_post();
            return flexapi::admin_unassign_package($userid, required_param('purchaseid', PARAM_INT),
                (bool) optional_param('refund', 0, PARAM_BOOL));
    }
    return api::unknown();
});
