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
 * Web-service (token) function: start a payment-gateway checkout for a subscription plan.
 *
 * This replaces the old "assume-paid" purchase_subscription: the new platform charges through a real
 * gateway (Kashier). The call returns a `checkout_url` the app opens; the subscription is granted
 * server-side only after the gateway confirms payment (webhook / verify_payment). Poll
 * get_my_subscriptions afterwards to detect activation.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * Create a subscription checkout session and return the gateway URL.
 */
class create_subscription_checkout extends external_api {

    /**
     * Parameters: subscriptionid + optional B2B/discount/display options.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'subscriptionid' => new external_value(PARAM_INT, 'Plan id to buy'),
            'type'           => new external_value(PARAM_ALPHANUM, 'normal | b2b', VALUE_DEFAULT, 'normal'),
            'seats'          => new external_value(PARAM_INT, 'B2B seat count (required when type=b2b)', VALUE_DEFAULT, 0),
            'coupon_code'    => new external_value(PARAM_TEXT, 'Coupon code to apply (normal purchase only)', VALUE_DEFAULT, ''),
            'country'        => new external_value(PARAM_ALPHA, 'ISO country from the app (optional)', VALUE_DEFAULT, ''),
            'lang'           => new external_value(PARAM_ALPHA, 'Display language for the gateway (en/ar)', VALUE_DEFAULT, 'en'),
            'alang'          => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
            'return_url'     => new external_value(PARAM_URL, 'URL to send the user back to after payment (optional)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create the checkout for the token's user.
     *
     * @param int $subscriptionid
     * @param string $type
     * @param int $seats
     * @param string $couponcode
     * @param string $country
     * @param string $lang
     * @param string $returnurl
     * @return array
     */
    public static function execute(int $subscriptionid, string $type = 'normal', int $seats = 0,
            string $couponcode = '', string $country = '', string $lang = 'en', string $returnurl = ''): array {
        global $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'subscriptionid' => $subscriptionid,
            'type'           => $type,
            'seats'          => $seats,
            'coupon_code'    => $couponcode,
            'country'        => $country,
            'lang'           => $lang,
            'return_url'     => $returnurl,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/nit_subscriptions:subscribe', $context);

        $mgrfile = $CFG->dirroot . '/local/payments/classes/manager.php';
        if (!file_exists($mgrfile) || !class_exists('\local_payments\manager')
                || !method_exists('\local_payments\manager', 'create_subscription_checkout')) {
            throw new \moodle_exception('err_paymentsunavailable', 'local_nit_subscriptions');
        }

        try {
            $checkout = \local_payments\manager::create_subscription_checkout(
                $params['subscriptionid'],
                $USER->id,
                !empty($params['country']) ? $params['country'] : null,
                $params['lang'],
                $params['type'],
                $params['seats'],
                $params['coupon_code'],
                $params['return_url']
            );
        } catch (\dml_missing_record_exception $e) {
            // The plan id does not exist — the manager does a MUST_EXIST lookup that surfaces as a raw
            // "Can't find data record in database". Give the app a clean, specific message instead.
            throw new \moodle_exception('err_subnotfound', 'local_nit_subscriptions');
        } catch (\moodle_exception $e) {
            // Business rules (already-subscribed, plan inactive, bad B2B seats, …) are thrown by the
            // payments manager as a generic errorcode 'error' whose human-readable reason lives in
            // debuginfo — which the web-service layer hides, leaving only "Error occurred". Re-surface
            // the real reason so the app can show it; pass any other exception through unchanged.
            if ($e->errorcode === 'error' && !empty($e->debuginfo)) {
                throw new \moodle_exception('err_checkoutfailed', 'local_nit_subscriptions', '', $e->debuginfo);
            }
            throw $e;
        }

        return [
            'order_id'       => $checkout->order_id,
            'checkout_url'   => $checkout->checkout_url,
            'expires_at'     => (int) $checkout->expires_at,
            'provider'       => $checkout->provider,
            'transaction_id' => (int) $checkout->transaction_id,
            'amount'         => (float) ($checkout->amount ?? 0),
            'original_amount' => (float) ($checkout->original_amount ?? 0),
            'currency'       => $checkout->currency ?? 'EGP',
        ];
    }

    /**
     * Return structure: the gateway checkout session.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'order_id'       => new external_value(PARAM_TEXT, 'Internal order id'),
            'checkout_url'   => new external_value(PARAM_URL, 'Gateway URL the app opens to collect payment'),
            'expires_at'     => new external_value(PARAM_INT, 'Checkout expiry unix time'),
            'provider'       => new external_value(PARAM_TEXT, 'Gateway name (e.g. kashier)'),
            'transaction_id' => new external_value(PARAM_INT, 'Transaction record id'),
            'amount'         => new external_value(PARAM_FLOAT, 'Charged amount after coupon/offer — show THIS price'),
            'original_amount' => new external_value(PARAM_FLOAT, 'Plan price before discount'),
            'currency'       => new external_value(PARAM_TEXT, 'Currency (e.g. EGP)'),
        ]);
    }
}
