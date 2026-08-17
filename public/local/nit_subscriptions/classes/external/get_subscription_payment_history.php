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
 * Web-service (token) function: the current user's subscription payment history.
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
use core_external\external_multiple_structure;
use local_nit_subscriptions\subscription_purchase_manager;

/**
 * Return the token user's subscription payments (from the payment gateway records).
 */
class get_subscription_payment_history extends external_api {

    /**
     * Parameters: optional display language (mobile apps send it on every call).
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'lang'  => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Fetch the current user's subscription payments, newest first.
     *
     * @param string $lang
     * @param string $alang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);
        self::validate_context(\context_system::instance());
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            force_current_language($chosen);
        }
        return subscription_purchase_manager::get_subscription_payment_history($USER->id);
    }

    /**
     * Return structure: one row per subscription payment.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'             => new external_value(PARAM_INT, 'Transaction id'),
                'subscriptionid' => new external_value(PARAM_INT, 'Plan id the payment was for'),
                'name'           => new external_value(PARAM_TEXT, 'Plan name'),
                'order_id'       => new external_value(PARAM_TEXT, 'Gateway order id (payment reference)'),
                'amount'         => new external_value(PARAM_FLOAT, 'Amount charged'),
                'currency'       => new external_value(PARAM_TEXT, 'Currency (e.g. EGP)'),
                'status'         => new external_value(PARAM_TEXT, 'pending | completed | failed | refunded | ...'),
                'payment_method' => new external_value(PARAM_TEXT, 'Payment method used (empty if unknown)'),
                'coupon_code'    => new external_value(PARAM_TEXT, 'Coupon applied at checkout (empty if none)'),
                'timecreated'    => new external_value(PARAM_INT, 'Payment created unix time'),
            ])
        );
    }
}
