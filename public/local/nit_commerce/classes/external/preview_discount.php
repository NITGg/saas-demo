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
 * Web-service (token) function: preview the discounted price of a sellable item.
 *
 * Applies the best automatic offer, then an optional coupon code on top, and returns the price
 * breakdown WITHOUT charging anything. Mobile-facing twin of the ?function=preview_discount endpoint.
 * An invalid coupon does not fail the call: the offer-only price is returned with a `coupon_error`.
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
use local_nit_commerce\discount_manager;

/**
 * Preview a coupon/offer discount for an item.
 */
class preview_discount extends external_api {

    /**
     * Parameters: item_type, item_id, optional coupon_code.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'item_type'   => new external_value(PARAM_ALPHA, 'course | package | subscription | program'),
            'item_id'     => new external_value(PARAM_INT, 'Target item id'),
            'coupon_code' => new external_value(PARAM_TEXT, 'Coupon code to try (optional)', VALUE_DEFAULT, ''),
            'country'     => new external_value(PARAM_ALPHA, 'ISO country from the app, for course pricing (optional)', VALUE_DEFAULT, ''),
            'lang'        => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang'       => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Resolve the discounted price for the token's user.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param string $couponcode
     * @param string $country
     * @param string $lang
     * @param string $alang
     * @return array
     */
    public static function execute(string $itemtype, int $itemid, string $couponcode = '',
            string $country = '', string $lang = '', string $alang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'item_type'   => $itemtype,
            'item_id'     => $itemid,
            'coupon_code' => $couponcode,
            'country'     => $country,
            'lang'        => $lang,
            'alang'       => $alang,
        ]);

        self::validate_context(\context_system::instance());
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            force_current_language($chosen);
        }

        // Verify the item exists first, so a bad id returns a clear "not found" instead of a
        // misleading final price of 0 (the discount engine treats an unknown item as base 0).
        self::require_item_exists($params['item_type'], $params['item_id']);

        // Course prices live in local_payments (per-course rules); other item types
        // resolve their own base inside discount_manager.
        $base = self::base_price($params['item_type'], $params['item_id'], $USER->id, $params['country']);

        try {
            $resolved = discount_manager::resolve(
                $params['item_type'], $params['item_id'], $USER->id, $params['coupon_code'], $base);
            $resolved['coupon_error'] = '';
        } catch (\moodle_exception $e) {
            // Invalid coupon — recompute without it so the offer-only price still shows.
            $resolved = discount_manager::resolve(
                $params['item_type'], $params['item_id'], $USER->id, '', $base);
            $resolved['coupon_error'] = $e->getMessage();
        }

        return self::shape($resolved);
    }

    /**
     * Ensure the target item actually exists, or throw a clean "not found". Guards against a bad id
     * silently previewing as free. Programs have no table yet, so they are rejected as not found.
     *
     * @param string $itemtype course | package | subscription | program
     * @param int $itemid
     * @return void
     */
    private static function require_item_exists(string $itemtype, int $itemid): void {
        global $DB;
        $exists = false;
        if ($itemtype === 'course') {
            $exists = $itemid > 1 && $DB->record_exists('course', ['id' => $itemid]); // 1 = site course.
        } else if ($itemtype === 'subscription') {
            $exists = $DB->get_manager()->table_exists('nit_subscription')
                && $DB->record_exists('nit_subscription', ['id' => $itemid]);
        } else if ($itemtype === 'package') {
            $exists = $DB->get_manager()->table_exists('nit_package')
                && $DB->record_exists('nit_package', ['id' => $itemid]);
        }
        if (!$exists) {
            throw new \moodle_exception('err_itemnotfound', 'local_nit_commerce');
        }
    }

    /**
     * The base (pre-discount) price of an item. Courses resolve via local_payments; other types
     * return null so discount_manager resolves them from their own tables.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param int $userid
     * @param string $country ISO country for course pricing ('' = auto-detect)
     * @return float|null
     */
    private static function base_price(string $itemtype, int $itemid, int $userid, string $country = ''): ?float {
        global $CFG;
        if ($itemtype !== 'course') {
            return null;
        }
        $file = $CFG->dirroot . '/local/payments/classes/price_resolver.php';
        if (!file_exists($file) || !class_exists('\local_payments\price_resolver')) {
            return null;
        }
        try {
            $pricing = \local_payments\price_resolver::resolve($itemid, $userid, $country !== '' ? $country : null);
            return (float) ($pricing->price ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalise the resolve() output for the web-service return structure.
     *
     * @param array $r
     * @return array
     */
    private static function shape(array $r): array {
        $offers = [];
        foreach (($r['offers'] ?? []) as $o) {
            $o = (array) $o;
            $offers[] = [
                'id'       => (int) ($o['id'] ?? 0),
                'name'     => (string) ($o['name'] ?? ''),
                'discount' => (float) ($o['discount'] ?? 0),
            ];
        }
        return [
            'original'        => (float) ($r['original'] ?? 0),
            'offers'          => $offers,
            'offer_id'        => (int) ($r['offer_id'] ?? 0),
            'offer_name'      => (string) ($r['offer_name'] ?? ''),
            'offer_discount'  => (float) ($r['offer_discount'] ?? 0),
            'coupon_id'       => (int) ($r['coupon_id'] ?? 0),
            'coupon_code'     => (string) ($r['coupon_code'] ?? ''),
            'coupon_discount' => (float) ($r['coupon_discount'] ?? 0),
            'discount'        => (float) ($r['discount'] ?? 0),
            'final'           => (float) ($r['final'] ?? 0),
            'coupon_error'    => (string) ($r['coupon_error'] ?? ''),
        ];
    }

    /**
     * Return structure: the price breakdown.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'original'        => new external_value(PARAM_FLOAT, 'Base price before any discount'),
            'offers'          => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Offer id'),
                    'name'     => new external_value(PARAM_TEXT, 'Offer name'),
                    'discount' => new external_value(PARAM_FLOAT, 'Amount this offer takes off'),
                ]),
                'Automatic offers applied (best one; offers do not stack)'
            ),
            'offer_id'        => new external_value(PARAM_INT, 'Applied offer id (0 = none)'),
            'offer_name'      => new external_value(PARAM_TEXT, 'Applied offer name'),
            'offer_discount'  => new external_value(PARAM_FLOAT, 'Total offer discount'),
            'coupon_id'       => new external_value(PARAM_INT, 'Applied coupon id (0 = none)'),
            'coupon_code'     => new external_value(PARAM_TEXT, 'Applied coupon code'),
            'coupon_discount' => new external_value(PARAM_FLOAT, 'Coupon discount (on top of offer)'),
            'discount'        => new external_value(PARAM_FLOAT, 'Total discount (offer + coupon)'),
            'final'           => new external_value(PARAM_FLOAT, 'Final price to charge'),
            'coupon_error'    => new external_value(PARAM_TEXT, 'Why the coupon was rejected (empty if none/valid)'),
        ]);
    }
}
