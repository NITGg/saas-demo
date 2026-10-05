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
 * Payments token-authenticated JSON API for the mobile app — the admin/teacher tools that only
 * exist as web pages (report.php, course_pricing.php, admin/providers.php, all transactions).
 * The student purchase flow is the local_payments_* web services (db/services.php).
 *
 *   GET|POST /local/payments/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * HTTP 401 = missing/dead token, 403 = academy suspended or expired. Writes require POST.
 * Optional `lang=ar|en`.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;
use local_payments\course_price_manager;
use local_payments\provider_manager;
use local_payments\report_manager;

api::boot();
$USER = api::authenticate();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}
$PAGE->set_context(context_system::instance());

api::run(function (string $function) use ($USER) {
    $syscontext = context_system::instance();

    switch ($function) {
        // ── Admin: revenue report (report.php) ──
        case 'get_revenue_report':
            api::require_capability('local/payments:viewreports', $syscontext);
            return report_manager::get_revenue_report();

        // ── Admin: every transaction, filtered + paged ──
        case 'get_transactions':
            api::require_capability('local/payments:viewalltransactions', $syscontext);
            [$page, $perpage] = api::paging(20, 100);
            return report_manager::get_transactions([
                'status'   => optional_param('status', '', PARAM_ALPHAEXT),
                'userid'   => optional_param('userid', 0, PARAM_INT),
                'courseid' => optional_param('courseid', 0, PARAM_INT),
                'datefrom' => optional_param('datefrom', 0, PARAM_INT),
                'dateto'   => optional_param('dateto', 0, PARAM_INT),
            ], $page, $perpage);

        // ── Teacher/manager: course pricing rules (course_pricing.php) ──
        case 'get_course_prices':
            $courseid = required_param('courseid', PARAM_INT);
            course_price_manager::require_course($courseid);
            api::require_capability('local/payments:managecoursepricing', context_course::instance($courseid));
            return course_price_manager::get_course_prices($courseid);

        case 'save_course_price':
            api::require_post();
            $courseid = required_param('courseid', PARAM_INT);
            course_price_manager::require_course($courseid);
            api::require_capability('local/payments:managecoursepricing', context_course::instance($courseid));
            return course_price_manager::save_course_price($courseid, optional_param('priceid', 0, PARAM_INT), [
                'country'    => optional_param('country', '*', PARAM_RAW_TRIMMED),
                'currency'   => required_param('currency', PARAM_ALPHA),
                'price'      => required_param('price', PARAM_FLOAT),
                'is_default' => optional_param('is_default', 0, PARAM_BOOL),
                'is_active'  => optional_param('is_active', 1, PARAM_BOOL),
            ], (int) $USER->id);

        case 'delete_course_price':
            api::require_post();
            $courseid = required_param('courseid', PARAM_INT);
            course_price_manager::require_course($courseid);
            api::require_capability('local/payments:managecoursepricing', context_course::instance($courseid));
            course_price_manager::delete_course_price($courseid, required_param('priceid', PARAM_INT));
            return ['deleted' => true];

        // ── Admin: payment providers (admin/providers.php) ──
        case 'get_providers':
            api::require_capability('local/payments:manageproviders', $syscontext);
            return provider_manager::get_providers();

        case 'set_provider':
            api::require_post();
            api::require_capability('local/payments:manageproviders', $syscontext);
            $enabled = optional_param('enabled', null, PARAM_BOOL);
            $priority = optional_param('priority', null, PARAM_INT);
            return provider_manager::set_provider(required_param('id', PARAM_INT),
                $enabled === null ? null : (bool) $enabled,
                $priority === null ? null : (int) $priority);
    }
    return api::unknown();
});
