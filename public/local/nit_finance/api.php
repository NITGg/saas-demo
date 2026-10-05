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
 * NIT Finance token-authenticated JSON API for the mobile app: student wallet,
 * lesson sales and codes, teacher earnings and withdrawals, admin finance.
 *
 *   GET|POST /local/nit_finance/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * HTTP 401 = missing/dead token, 403 = academy suspended or expired. State-changing
 * calls require POST. Optional `lang=ar|en`. Amounts are sent in pounds ("150.5")
 * and returned as `<name>_minor` (piastres) + `<name>` (pounds) + `currency`.
 * The logic lives in \local_nit_finance\local\mobile_api.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_nit_finance\local\mobile_api as finance;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

api::run(function (string $function) use ($userid) {
    // Admin functions: the finance manager capability (system), like the admin pages.
    $adminfunctions = ['get_finance_summary', 'list_withdrawals', 'process_withdrawal', 'list_wallets',
        'get_wallet_ledger', 'adjust_wallet', 'list_codes', 'generate_codes', 'disable_code'];
    if (in_array($function, $adminfunctions, true)) {
        api::require_capability('local/nit_finance:manage');
    }

    switch ($function) {
        // ── Student wallet & lessons ─────────────────────────────────────────

        // Balance + what online top-up allows.
        case 'get_wallet':
            return finance::get_wallet($userid);

        // Wallet ledger, newest first (page/perpage).
        case 'get_wallet_history':
            [$page, $perpage] = api::paging();
            return finance::get_wallet_history($userid, $page, $perpage);

        // Lessons / courses the student bought or unlocked.
        case 'get_my_purchases':
            return finance::get_my_purchases($userid);

        // Start an online top-up → open checkout_url, then poll get_topup_status.
        case 'create_topup_checkout':
            api::require_post();
            return finance::create_topup_checkout($userid, required_param('amount', PARAM_RAW_TRIMMED),
                optional_param('return_url', '', PARAM_LOCALURL), current_language());

        // Is the top-up paid and credited? (asks the gateway if the webhook has not come yet)
        case 'get_topup_status':
            return finance::get_topup_status($userid, required_param('order_id', PARAM_RAW_TRIMMED));

        // Use a code (lesson, course or wallet credit).
        case 'redeem_code':
            api::require_post();
            return finance::redeem_code($userid, required_param('code', PARAM_RAW_TRIMMED));

        // A course's lessons sold on their own + whether the course itself is sold/owned.
        case 'get_course_lesson_prices':
            return finance::get_course_lesson_prices($userid, required_param('courseid', PARAM_INT));

        // Can I open this lesson / pay for it from the wallet?
        case 'get_lesson_access':
            return finance::get_lesson_access($userid, required_param('cmid', PARAM_INT));

        // Free first minutes of a paid video lesson (preview_seconds > 0 in get_lesson_access).
        case 'get_lesson_preview':
            return finance::get_lesson_preview($userid, required_param('cmid', PARAM_INT));

        // Buy one lesson with the wallet.
        case 'buy_lesson':
            api::require_post();
            return finance::buy_lesson($userid, required_param('cmid', PARAM_INT));

        // ── Teacher ──────────────────────────────────────────────────────────

        case 'get_teacher_wallet':
            return finance::get_teacher_wallet($userid);

        case 'get_teacher_wallet_history':
            [$page, $perpage] = api::paging();
            return finance::get_teacher_wallet_history($userid, $page, $perpage);

        case 'get_my_earnings':
            [$page, $perpage] = api::paging();
            return finance::get_my_earnings($userid, $page, $perpage);

        case 'request_withdrawal':
            api::require_post();
            return finance::request_withdrawal($userid, required_param('amount', PARAM_RAW_TRIMMED),
                optional_param('method', '', PARAM_ALPHA), optional_param('account', '', PARAM_TEXT));

        case 'get_my_withdrawals':
            return finance::get_my_withdrawals($userid);

        // ── Admin (local/nit_finance:manage) ─────────────────────────────────

        case 'get_finance_summary':
            return finance::get_finance_summary();

        case 'list_withdrawals':
            [$page, $perpage] = api::paging(50, 200);
            return finance::list_withdrawals(optional_param('status', '', PARAM_ALPHA), $page, $perpage);

        case 'process_withdrawal':
            api::require_post();
            return finance::process_withdrawal($userid, required_param('id', PARAM_INT),
                required_param('action', PARAM_ALPHA), optional_param('reason', '', PARAM_TEXT),
                optional_param('reference', '', PARAM_TEXT));

        case 'list_wallets':
            [$page, $perpage] = api::paging(50, 200);
            return finance::list_wallets(required_param('type', PARAM_ALPHA), optional_param('q', '', PARAM_RAW_TRIMMED),
                $page, $perpage);

        case 'get_wallet_ledger':
            [$page, $perpage] = api::paging(50, 200);
            return finance::get_wallet_ledger(required_param('type', PARAM_ALPHA), optional_param('userid', 0, PARAM_INT),
                $page, $perpage);

        case 'adjust_wallet':
            api::require_post();
            return finance::adjust_wallet(required_param('userid', PARAM_INT), required_param('amount', PARAM_RAW_TRIMMED),
                optional_param('note', '', PARAM_TEXT));

        case 'list_codes':
            [$page, $perpage] = api::paging(50, 200);
            return finance::list_codes([
                'status' => optional_param('status', '', PARAM_ALPHA),
                'batch' => optional_param('batch', '', PARAM_ALPHANUMEXT),
                'q' => optional_param('q', '', PARAM_RAW_TRIMMED),
            ], $page, $perpage);

        case 'generate_codes':
            api::require_post();
            return finance::generate_codes($userid, required_param('type', PARAM_ALPHA),
                optional_param('itemid', 0, PARAM_INT), required_param('count', PARAM_INT),
                optional_param('amount', '', PARAM_RAW_TRIMMED), optional_param('expires', '', PARAM_RAW_TRIMMED),
                optional_param('note', '', PARAM_TEXT));

        case 'disable_code':
            api::require_post();
            return finance::disable_code(required_param('id', PARAM_INT));

        // Needs local/nit_finance:setprice in the lesson's course (checked inside).
        case 'set_lesson_price':
            api::require_post();
            return finance::set_lesson_price($userid, required_param('cmid', PARAM_INT),
                required_param('price', PARAM_RAW_TRIMMED));
    }
    return api::unknown();
});
