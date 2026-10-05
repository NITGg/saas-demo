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
 * JSON API for NIT Subscriptions. Two auth modes share one dispatcher:
 *
 * - Session mode (the admin web UIs + checkout modal): no token. Logged-in session; state-changing
 *   calls require POST + sesskey. get_available_subscriptions is public so a home-page marketing
 *   block can render it for guests.
 *     ?function=<name> ... → {"status":"success","data":...} | {"status":"error","error":...}
 *
 * - Token mode (the mobile app): send `token` (or `wstoken`) = a web-service token. No cookies,
 *   no sesskey; writes still require POST. Shared mobile envelope (local_academy\api\endpoint):
 *     → {"status":"success","data":...} | {"status":"fail","error":...,"errorcode":...}
 *
 * Capability checks and the licence gate apply in both modes.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// A token in the request switches to the cookie-less mobile mode (decided before config.php).
$nittokenmode = (($_GET['token'] ?? '') !== '') || (($_POST['token'] ?? '') !== '')
    || (($_GET['wstoken'] ?? '') !== '') || (($_POST['wstoken'] ?? '') !== '');
if ($nittokenmode) {
    define('NO_MOODLE_COOKIES', true);
} else {
    define('AJAX_SCRIPT', true);
    define('NO_MOODLE_COOKIES', false);
}
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

use local_nit_subscriptions\subscription_manager;
use local_nit_subscriptions\subscription_purchase_manager;
use local_nit_subscriptions\course_purchase_manager;
use local_academy\api\endpoint as api;

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$context = context_system::instance();

// ── Token mode (mobile app) ──
if ($nittokenmode) {
    api::boot();
    api::authenticate();
    $PAGE->set_context($context);
    nit_subscriptions_force_lang();
    if (!local_nit_subscriptions_feature()) {
        api::fail('featureunavailable', get_string('feature_unavailable_desc', 'local_nit_subscriptions'));
    }
    api::run(function (string $function) use ($context) {
        return nit_subscriptions_dispatch($function, $context, true);
    });
    exit;
}

// ── Session mode (web UIs) ──
// Public reads a guest can call for a front-page block.
$publicfns = ['get_available_subscriptions'];

if (!in_array($function, $publicfns, true)) {
    require_login(null, false);
}

$PAGE->set_context($context);

// Licence gate: when the tier doesn't include subscriptions, refuse every call
// (management errors; the public listing returns an empty set). Nothing runs.
if (!local_nit_subscriptions_feature()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'feature_unavailable', 'subscriptions' => []]);
    exit;
}

// Honour an explicit language for multilang name/description resolution.
nit_subscriptions_force_lang();

header('Content-Type: application/json; charset=utf-8');

try {
    nit_subscriptions_respond(['status' => 'success',
        'data' => nit_subscriptions_dispatch($function, $context, false)]);
} catch (\Throwable $e) {
    nit_subscriptions_respond(['status' => 'error', 'error' => $e->getMessage()]);
}

/**
 * Emit a JSON envelope and stop (session mode).
 *
 * @param array $payload
 * @return void
 */
function nit_subscriptions_respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

/**
 * Honour an explicit language (alang, else lang) for multilang names and messages.
 *
 * @return void
 */
function nit_subscriptions_force_lang(): void {
    $alang = optional_param('alang', '', PARAM_LANG);
    if ($alang === '') {
        $alang = optional_param('lang', '', PARAM_LANG);
    }
    if ($alang !== '') {
        force_current_language($alang);
    }
}

/**
 * Run one API function as the current user and return its data. Errors are thrown; each mode
 * renders them in its own envelope.
 *
 * @param string $function
 * @param context $context system context
 * @param bool $tokenmode true = mobile token request (no sesskey; shared envelope)
 * @return mixed
 */
function nit_subscriptions_dispatch(string $function, context $context, bool $tokenmode) {
    global $USER, $DB, $CFG;

    // Writes must be POST (+ sesskey in session mode).
    $writes = ['create_subscription', 'update_subscription', 'activate_subscription',
        'deactivate_subscription', 'delete_subscription', 'set_subscription_courses',
        'create_subscription_checkout', 'unsubscribe_user', 'revoke_course_purchase',
        'save_reminder_settings', 'enrol_course'];
    if (in_array($function, $writes, true)) {
        if ($tokenmode) {
            api::require_post();
        } else {
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                throw new \moodle_exception('err_postrequired', 'local_nit_subscriptions');
            }
            require_sesskey();
        }
    }

    // Admin-only functions.
    $adminfns = ['get_subscriptions', 'create_subscription', 'update_subscription',
        'activate_subscription', 'deactivate_subscription', 'delete_subscription',
        'get_categories_with_courses', 'set_subscription_courses', 'get_all_user_subscriptions',
        'unsubscribe_user', 'get_all_course_purchases', 'revoke_course_purchase',
        'get_reminder_settings', 'preview_reminder_settings', 'save_reminder_settings'];
    if (in_array($function, $adminfns, true)) {
        require_capability('local/nit_subscriptions:managesubscriptions', $context);
    }

    switch ($function) {
        // ── Admin: plan catalog ──
        case 'get_subscriptions':
            return array_values(subscription_manager::get_subscriptions());

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
            return ['id' => $id];

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
            return [];

        case 'activate_subscription':
            subscription_manager::activate_subscription(required_param('id', PARAM_INT), $USER->id);
            return [];

        case 'deactivate_subscription':
            subscription_manager::deactivate_subscription(required_param('id', PARAM_INT), $USER->id);
            return [];

        case 'delete_subscription':
            subscription_manager::delete_subscription(required_param('id', PARAM_INT));
            return [];

        // ── Admin: course access ──
        case 'get_categories_with_courses':
            return subscription_manager::get_categories_with_courses();

        case 'set_subscription_courses':
            $courseids = json_decode(optional_param('courseids', '[]', PARAM_RAW), true);
            if (!is_array($courseids)) {
                $courseids = [];
            }
            return subscription_manager::set_subscription_courses(
                required_param('subscriptionid', PARAM_INT), $courseids, $USER->id);

        // ── Admin: user subscriptions ──
        case 'get_all_user_subscriptions':
            return subscription_purchase_manager::get_all_user_subscriptions();

        case 'unsubscribe_user':
            subscription_purchase_manager::unsubscribe(required_param('purchaseid', PARAM_INT));
            return [];

        // ── Admin: single-course purchases ("Manage courses") ──
        // List every user's paid single-course purchase (a completed local_payments transaction with
        // item_type=course), so the admin can see who bought what and revoke it.
        case 'get_all_course_purchases':
            return course_purchase_manager::get_all_course_purchases();

        // "Unbuy" a course: unenrol the buyer and mark the transaction cancelled (or refunded).
        case 'revoke_course_purchase':
            course_purchase_manager::revoke_course_purchase(
                required_param('transactionid', PARAM_INT),
                (bool) optional_param('refund', 0, PARAM_BOOL));
            return [];

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
            return $data;

        // ── Student: enrol into a FREE course or one covered by the active subscription ──
        // (the logic of enrol.php; a paid, uncovered course fails with err_paymentrequired).
        case 'enrol_course':
            return subscription_purchase_manager::enrol_free_or_covered(
                required_param('courseid', PARAM_INT), (int) $USER->id);

        // ── Admin: expiry-reminder settings (the "Renewal reminders" tab) ──
        case 'get_reminder_settings':
            $settings = \local_nit_subscriptions\reminder_manager::get_settings();
            $settings['preview'] = \local_nit_subscriptions\reminder_manager::preview($settings['days']);
            $settings['max_days'] = \local_nit_subscriptions\reminder_manager::MAX_DAYS;
            $settings['max_entries'] = \local_nit_subscriptions\reminder_manager::MAX_ENTRIES;
            return $settings;

        // How many people the days currently typed into the form would reach, without saving.
        case 'preview_reminder_settings':
            $days = array_filter(explode(',', optional_param('days', '', PARAM_SEQUENCE)), 'strlen');
            return \local_nit_subscriptions\reminder_manager::preview($days);

        // Saving does not just store the numbers: it re-runs the whole calculation, so anyone
        // the new window now covers is notified immediately rather than at the next cron.
        case 'save_reminder_settings':
            $days = array_filter(explode(',', optional_param('days', '', PARAM_SEQUENCE)), 'strlen');
            $result = \local_nit_subscriptions\reminder_manager::save_settings(
                (bool) optional_param('enabled', 0, PARAM_BOOL), $days);
            $result['preview'] = \local_nit_subscriptions\reminder_manager::preview($result['days']);
            return $result;

        // ── Student: my subscriptions (active first) for a "My subscriptions" screen ──
        case 'get_my_subscriptions':
            return subscription_purchase_manager::get_my_subscriptions($USER->id);

        // ── Student: my subscription payments (gateway transactions), newest first ──
        case 'get_subscription_payment_history':
            return subscription_purchase_manager::get_subscription_payment_history($USER->id);

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
            $subscriptionid = required_param('subscriptionid', PARAM_INT);
            if (!$DB->record_exists('nit_subscription', ['id' => $subscriptionid])) {
                throw new \moodle_exception('err_subnotfound', 'local_nit_subscriptions');
            }
            return \local_payments\manager::create_subscription_checkout(
                $subscriptionid,
                $USER->id,
                null,
                optional_param('alang', current_language(), PARAM_LANG),
                optional_param('type', 'normal', PARAM_ALPHANUM),
                optional_param('seats', 0, PARAM_INT),
                optional_param('coupon_code', '', PARAM_TEXT),
                optional_param('return_url', '', PARAM_RAW_TRIMMED)
            );

        // ── Public: available plans for the home-page block ──
        case 'get_available_subscriptions':
            return nit_subscriptions_available();
    }

    if ($tokenmode) {
        api::unknown();
    }
    throw new \moodle_exception('err_unknownfunction', 'local_nit_subscriptions');
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
