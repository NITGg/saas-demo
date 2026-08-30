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
 * Web-service (token) function: list active, in-window coupons a student can browse.
 *
 * Mobile-facing twin of the ?function=get_available_coupons endpoint in api.php.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_nit_commerce\coupon_manager;

/**
 * Return the browseable coupon catalogue.
 */
class get_available_coupons extends external_api {

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
     * Fetch active, in-window coupons.
     *
     * @param string $lang
     * @param string $alang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);
        self::validate_context(\context_system::instance());
        // Licence gate: no coupons feature → nothing to offer.
        if (class_exists('\\local_license\\license') && !\local_license\license::has_feature('coupons')) {
            return [];
        }
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            force_current_language($chosen);
        }
        return coupon_manager::get_available_coupons();
    }

    /**
     * Return structure: one row per coupon (see coupon_manager::format()).
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'             => new external_value(PARAM_INT, 'Coupon id'),
                'code'           => new external_value(PARAM_TEXT, 'Coupon code the student enters at checkout'),
                'discount_type'  => new external_value(PARAM_ALPHA, 'percent | fixed'),
                'discount_value' => new external_value(PARAM_FLOAT, 'Percent (0-100) or fixed amount'),
                'max_discount'   => new external_value(PARAM_FLOAT, 'Cap on the applied discount, or null', VALUE_OPTIONAL),
                'usage_type'     => new external_value(PARAM_ALPHA, 'once | multiple'),
                'usage_limit'    => new external_value(PARAM_INT, 'Global redemption cap (0 = unlimited)'),
                'startdate'      => new external_value(PARAM_INT, 'Valid-from unix time (0 = always)'),
                'enddate'        => new external_value(PARAM_INT, 'Valid-until unix time (0 = never expires)'),
                'status'         => new external_value(PARAM_ALPHA, 'active | inactive'),
                'usage_count'    => new external_value(PARAM_INT, 'How many times it has been redeemed'),
                'applies_to'     => new external_multiple_structure(
                    new external_single_structure([
                        'item_type' => new external_value(PARAM_ALPHA, 'course | package | subscription | program'),
                        'item_id'   => new external_value(PARAM_INT, 'Target id (0 = all of that type)'),
                        'label'     => new external_value(PARAM_TEXT, 'Human-readable target label'),
                    ]),
                    'Items this coupon applies to'
                ),
            ])
        );
    }
}
