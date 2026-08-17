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
 * Web-service (token) function: the current user's subscription purchases, active first.
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
 * Return the token user's subscriptions for a "My subscriptions" screen.
 */
class get_my_subscriptions extends external_api {

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
     * Fetch the current user's subscription purchases.
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
        return subscription_purchase_manager::get_my_subscriptions($USER->id);
    }

    /**
     * Return structure: one row per purchase, active subscriptions first.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'             => new external_value(PARAM_INT, 'Purchase id'),
                'subscriptionid' => new external_value(PARAM_INT, 'Plan id'),
                'name'           => new external_value(PARAM_TEXT, 'Plan name'),
                'type'           => new external_value(PARAM_ALPHA, 'normal | b2b'),
                'price_paid'     => new external_value(PARAM_FLOAT, 'Amount actually charged'),
                'status'         => new external_value(PARAM_ALPHA, 'active | expired | cancelled (computed live)'),
                'timeactivated'  => new external_value(PARAM_INT, 'Activation unix time'),
                'expires_at'     => new external_value(PARAM_INT, 'Expiry unix time (0 = never)'),
                'remaining_days' => new external_value(PARAM_INT, 'Whole days until expiry (0 when not active)'),
                'duration_days'  => new external_value(PARAM_INT, 'Plan duration in days'),
                'courses'        => new external_multiple_structure(
                    new external_single_structure([
                        'id'       => new external_value(PARAM_INT, 'Moodle course id'),
                        'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                    ]),
                    'The courses this subscription unlocks, as {id, fullname} objects'
                ),
            ])
        );
    }
}
