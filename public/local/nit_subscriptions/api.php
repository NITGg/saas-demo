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
 * JSON API for NIT Subscriptions. Mirrors the reference api.php protocol:
 *   ?function=<name> ... → {"status":"success","data":...} | {"status":"error","error":...}
 *
 * Auth is the logged-in session; state-changing calls require POST + sesskey. The get_available_*
 * reads are public so a home-page marketing block can render them for guests.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', false);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

use local_nit_subscriptions\subscription_manager;
use local_nit_subscriptions\subscription_purchase_manager;
use local_nit_subscriptions\course_purchase_manager;

$function = optional_param('function', '', PARAM_ALPHANUMEXT);

// Public reads a guest can call for a front-page block.
$publicfns = ['get_available_subscriptions'];

if (!in_array($function, $publicfns, true)) {
    require_login(null, false);
}

$context = context_system::instance();
$PAGE->set_context($context);

// Honour an explicit language for multilang name/description resolution.
$alang = optional_param('alang', '', PARAM_LANG);
if ($alang === '') {
    $alang = optional_param('lang', '', PARAM_LANG);
}
if ($alang !== '') {
    force_current_language($alang);
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit a JSON envelope and stop.
 *
 * @param array $payload
 * @return void
 */
function nit_subscriptions_respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

try {
    $ispost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

    // Writes must be POST + sesskey.
    $writes = ['create_subscription', 'update_subscription', 'activate_subscription',
        'deactivate_subscription', 'delete_subscription', 'set_subscription_courses',
        'create_subscription_checkout', 'unsubscribe_user', 'revoke_course_purchase'];
    if (in_array($function, $writes, true)) {
        if (!$ispost) {
            throw new \moodle_exception('err_postrequired', 'local_nit_subscriptions');
        }
        require_sesskey();
    }

    // Admin-only functions.
    $adminfns = ['get_subscriptions', 'create_subscription', 'update_subscription',
        'activate_subscription', 'deactivate_subscription', 'delete_subscription',
        'get_categories_with_courses', 'set_subscription_courses', 'get_all_user_subscriptions',
        'unsubscribe_user', 'get_all_course_purchases', 'revoke_course_purchase'];
    if (in_array($function, $adminfns, true)) {
        require_capability('local/nit_subscriptions:managesubscriptions', $context);
    }

    global $USER, $DB;

    switch ($function) {
        // ── Admin: plan catalog ──
        case 'get_subscriptions':
            nit_subscriptions_respond(['status' => 'success',
                'data' => array_values(subscription_manager::get_subscriptions())]);
            break;

        case 'create_subscription':
            $id = subscription_manager::create_subscription([
                'name'          => required_param('name', PARAM_TEXT),
                'description'   => optional_param('description', '', PARAM_TEXT),
                'price'         => required_param('price', PARAM_FLOAT),
                'duration_days' => required_param('duration_days', PARAM_INT),
                'active'        => optional_param('active', 1, PARAM_INT),
                'b2b_enabled'   => optional_param('b2b_enabled', 0, PARAM_INT),
                'seat_options'  => nit_subscriptions_seat_options(),
            ], $USER->id);
            nit_subscriptions_respond(['status' => 'success', 'data' => ['id' => $id]]);
            break;

        case 'update_subscription':
            subscription_manager::update_subscription(required_param('id', PARAM_INT), [
                'name'          => required_param('name', PARAM_TEXT),
                'description'   => optional_param('description', '', PARAM_TEXT),
                'price'         => required_param('price', PARAM_FLOAT),
                'duration_days' => required_param('duration_days', PARAM_INT),
                'status'        => optional_param('status', 'active', PARAM_ALPHA),
                'b2b_enabled'   => optional_param('b2b_enabled', 0, PARAM_INT),
                'seat_options'  => nit_subscriptions_seat_options(),
            ], $USER->id);
            nit_subscriptions_respond(['status' => 'success', 'data' => []]);
            break;

        case 'activate_subscription':
            subscription_manager::activate_subscription(required_param('id', PARAM_INT), $USER->id);
            nit_subscriptions_respond(['status' => 'success', 'data' => []]);
            break;

        case 'deactivate_subscription':
            subscription_manager::deactivate_subscription(required_param('id', PARAM_INT), $USER->id);
            nit_subscriptions_respond(['status' => 'success', 'data' => []]);
            break;

        case 'delete_subscription':
            subscription_manager::delete_subscription(required_param('id', PARAM_INT));
            nit_subscriptions_respond(['status' => 'success', 'data' => []]);
            break;

        // ── Admin: course access ──
        case 'get_categories_with_courses':
            nit_subscriptions_respond(['status' => 'success',
                'data' => subscription_manager::get_categories_with_courses()]);
            break;

        case 'set_subscription_courses':
            $courseids = json_decode(optional_param('courseids', '[]', PARAM_RAW), true);
            if (!is_array($courseids)) {
                $courseids = [];
            }
            $courses = subscription_manager::set_subscription_courses(
                required_param('subscriptionid', PARAM_INT), $courseids, $USER->id);
            nit_subscriptions_respond(['status' => 'success', 'data' => $courses]);
            break;

        // ── Admin: user subscriptions ──
        case 'get_all_user_subscriptions':
            nit_subscriptions_respond(['status' => 'success',
                'data' => subscription_purchase_manager::get_all_user_subscriptions()]);
            break;

        case 'unsubscribe_user':
            subscription_purchase_manager::unsubscribe(required_param('purchaseid', PARAM_INT));
            nit_subscriptions_respond(['status' => 'success', 'data' => []]);
            break;

        // ── Admin: single-course purchases ("Manage courses") ──
        // List every user's paid single-course purchase (a completed local_payments transaction with
        // item_type=course), so the admin can see who bought what and revoke it.
        case 'get_all_course_purchases':
            nit_subscriptions_respond(['status' => 'success',
                'data' => course_purchase_manager::get_all_course_purchases()]);
            break;

        // "Unbuy" a course: unenrol the buyer and mark the transaction cancelled (or refunded).
        case 'revoke_course_purchase':
            course_purchase_manager::revoke_course_purchase(
                required_param('transactionid', PARAM_INT),
                (bool) optional_param('refund', 0, PARAM_BOOL));
            nit_subscriptions_respond(['status' => 'success', 'data' => []]);
            break;

        // ── Student: my active subscription (for the home-block banner + button state) ──
        case 'get_my_active_subscription':
            $active = subscription_purchase_manager::get_active_subscription($USER->id);
            $data = ['has_active' => false, 'subscriptionid' => 0, 'expires_at' => 0,
                'name' => '', 'days_left' => 0, 'price_paid' => 0];
            if ($active) {
                $name = $DB->get_field('nit_subscription', 'name', ['id' => $active->subscriptionid]);
                $daysleft = ((int) $active->expires_at > 0)
                    ? max(0, (int) ceil(((int) $active->expires_at - time()) / DAYSECS)) : 0;
                $data = [
                    'has_active'     => true,
                    'subscriptionid' => (int) $active->subscriptionid,
                    'expires_at'     => (int) $active->expires_at,
                    'name'           => $name !== false ? format_string(subscription_manager::resolve_mlang($name)) : '',
                    'days_left'      => $daysleft,
                    'price_paid'     => (float) $active->price_paid,
                ];
            }
            nit_subscriptions_respond(['status' => 'success', 'data' => $data]);
            break;

        // ── Student: my subscriptions (active first) for a "My subscriptions" screen ──
        case 'get_my_subscriptions':
            nit_subscriptions_respond(['status' => 'success',
                'data' => subscription_purchase_manager::get_my_subscriptions($USER->id)]);
            break;

        // ── Student: my subscription payments (gateway transactions), newest first ──
        case 'get_subscription_payment_history':
            nit_subscriptions_respond(['status' => 'success',
                'data' => subscription_purchase_manager::get_subscription_payment_history($USER->id)]);
            break;

        // ── Student: start a Kashier checkout for a subscription ──
        case 'create_subscription_checkout':
            require_capability('local/nit_subscriptions:subscribe', $context);
            $mgrfile = $CFG->dirroot . '/local/payments/classes/manager.php';
            if (!file_exists($mgrfile)) {
                throw new \moodle_exception('err_paymentsunavailable', 'local_nit_subscriptions');
            }
            require_once($mgrfile);
            if (!method_exists('\local_payments\manager', 'create_subscription_checkout')) {
                throw new \moodle_exception('err_paymentsunavailable', 'local_nit_subscriptions');
            }
            $checkout = \local_payments\manager::create_subscription_checkout(
                required_param('subscriptionid', PARAM_INT),
                $USER->id,
                null,
                optional_param('alang', current_language(), PARAM_LANG),
                optional_param('type', 'normal', PARAM_ALPHANUM),
                optional_param('seats', 0, PARAM_INT),
                optional_param('coupon_code', '', PARAM_TEXT),
                optional_param('return_url', '', PARAM_RAW_TRIMMED)
            );
            nit_subscriptions_respond(['status' => 'success', 'data' => $checkout]);
            break;

        // ── Public: available plans for the home-page block ──
        case 'get_available_subscriptions':
            nit_subscriptions_respond(['status' => 'success',
                'data' => nit_subscriptions_available()]);
            break;

        default:
            throw new \moodle_exception('err_unknownfunction', 'local_nit_subscriptions');
    }
} catch (\Throwable $e) {
    nit_subscriptions_respond(['status' => 'error', 'error' => $e->getMessage()]);
}

/**
 * Decode the seat_options JSON parameter into an array of ['seats','discount_percent'] rows.
 *
 * @return array
 */
function nit_subscriptions_seat_options(): array {
    $raw = optional_param('seat_options', '[]', PARAM_RAW);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// nit_subscriptions_available() now lives in lib.php (shared with the get_available_subscriptions
// external function), and is loaded via the require_once at the top of this file.
