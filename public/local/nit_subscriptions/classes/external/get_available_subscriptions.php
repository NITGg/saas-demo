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
 * Web-service (token) function: list active subscription plans a student can buy.
 *
 * Mobile-facing twin of the ?function=get_available_subscriptions endpoint in api.php.
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

global $CFG;
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

/**
 * Return the active subscription-plan catalogue.
 */
class get_available_subscriptions extends external_api {

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
     * Fetch active plans (shaped by nit_subscriptions_available()).
     *
     * @param string $lang
     * @param string $alang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);
        self::validate_context(\context_system::instance());
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            force_current_language($chosen);
        }
        return nit_subscriptions_available();
    }

    /**
     * Return structure: one row per active plan.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'            => new external_value(PARAM_INT, 'Subscription plan id'),
                'name'          => new external_value(PARAM_TEXT, 'Plan name'),
                'description'   => new external_value(PARAM_RAW, 'Plan description (HTML)'),
                'price'         => new external_value(PARAM_FLOAT, 'Plan price (EGP)'),
                'duration_days' => new external_value(PARAM_INT, 'Access duration in days'),
                'status'        => new external_value(PARAM_TEXT, 'Plan status (active)'),
                'b2b_enabled'   => new external_value(PARAM_INT, '1 if the plan can be bought for a team (B2B)'),
                'courses_count' => new external_value(PARAM_INT, 'Number of courses the plan unlocks'),
                'courses'       => new external_multiple_structure(
                    new external_single_structure([
                        'id'       => new external_value(PARAM_INT, 'Moodle course id'),
                        'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                    ]),
                    'The courses this plan unlocks, as {id, fullname} objects'
                ),
                'seat_options'  => new external_multiple_structure(
                    new external_single_structure([
                        'id'               => new external_value(PARAM_INT, 'Seat-option id'),
                        'seats'            => new external_value(PARAM_INT, 'Seat count for this tier'),
                        'discount_percent' => new external_value(PARAM_FLOAT, 'B2B discount for this seat tier'),
                        'original_price'   => new external_value(PARAM_FLOAT, 'price x seats, before the tier discount'),
                        'discount_amount'  => new external_value(PARAM_FLOAT, 'Amount the tier discount takes off'),
                        'b2b_price'        => new external_value(PARAM_FLOAT, 'Final total for this seat tier'),
                    ]),
                    'B2B seat tiers (empty for a normal plan)'
                ),
                'offer_label'   => new external_value(PARAM_TEXT, 'Best current offer label, e.g. "-10%" (empty if none)'),
                'offer_final'   => new external_value(PARAM_FLOAT, 'Price after the best offer (0 if none)'),
                'offer'         => new external_single_structure([
                    'original' => new external_value(PARAM_FLOAT, 'Price before the offer'),
                    'final'    => new external_value(PARAM_FLOAT, 'Price after the offer'),
                    'label'    => new external_value(PARAM_TEXT, 'Offer badge, e.g. "-10%"'),
                    'name'     => new external_value(PARAM_TEXT, 'Offer name'),
                ], 'Best current offer; omitted when there is no active offer', VALUE_OPTIONAL),
            ])
        );
    }
}
