<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\tools\sales;

use local_nit_assistant\runtime\rate_limit;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * The price the checkout would charge for one item, with the automatic offer
 * and, optionally, a coupon the visitor typed — the same calculation as the
 * checkout (local_nit_commerce\discount_manager::resolve). Read-only: nothing
 * is reserved. Coupon checks are capped per conversation so codes can't be guessed.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preview_price extends tool {
    /** Coupon checks allowed per conversation per day. */
    public const COUPON_CHECKS = 5;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'preview_price';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The final price of one course, subscription plan or lesson package after the automatic offer and, '
            . 'if the visitor gives one, their coupon code. Says whether the coupon is valid. Use the id from '
            . 'search_courses, list_subscriptions or list_packages. Only pass a coupon the visitor typed.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['sales', 'student'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'item_type' => ['type' => 'string', 'enum' => ['course', 'subscription', 'package']],
                'item_id' => ['type' => 'integer', 'minimum' => 1],
                'coupon' => ['type' => 'string', 'maxLength' => 50],
            ],
            'required' => ['item_type', 'item_id'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $DB;
        if (!class_exists('\local_nit_commerce\discount_manager')) {
            return self::fail('service_unavailable', 'Prices cannot be checked right now.');
        }
        $type = $input['item_type'];
        $id = (int) $input['item_id'];
        $coupon = trim($input['coupon'] ?? '');
        if ($coupon !== '' && !rate_limit::hit("coupon:{$ctx->convid}", self::COUPON_CHECKS, DAYSECS, $ctx->now)['allowed']) {
            return self::fail('rate_limited', 'Too many coupon checks in this chat. The coupon can be entered at checkout.');
        }

        $currency = helper::EGP;
        $base = null;
        if ($type === 'course') {
            $course = $DB->get_record('course', ['id' => $id], 'id, visible');
            if (!$course || $id === (int) SITEID || !$course->visible) {
                return self::fail('not_found', 'No such course.');
            }
            if (!\local_payments\price_resolver::has_pricing($id)) {
                return self::ok(['free' => true]);
            }
            $pricing = \local_payments\price_resolver::resolve($id, $ctx->userid);
            $base = (float) $pricing->price;
            $currency = (string) $pricing->currency;
        } else if (!self::on_sale($type, $id)) {
            return self::fail('not_found', "No such {$type} on sale.");
        }

        $couponerror = null;
        try {
            $r = \local_nit_commerce\discount_manager::resolve($type, $id, $ctx->userid, $coupon, $base);
        } catch (\moodle_exception $e) {
            $couponerror = helper::plain($e->getMessage(), 200);
            $r = \local_nit_commerce\discount_manager::resolve($type, $id, $ctx->userid, '', $base);
        }
        $out = [
            'item_type' => $type,
            'item_id' => $id,
            'price' => helper::money($r['original']),
            'offer' => $r['offer_discount'] > 0
                ? ['name' => (string) $r['offer_name'], 'discount' => helper::money($r['offer_discount'])] : null,
            'final_price' => helper::money($r['final']),
            'currency' => $currency,
        ];
        if ($coupon !== '') {
            $out['coupon_valid'] = $couponerror === null && $r['coupon_discount'] > 0;
            $out['coupon_discount'] = helper::money($r['coupon_discount']);
            if ($couponerror !== null) {
                $out['coupon_problem'] = $couponerror;
            }
        }
        return self::ok($out);
    }

    /**
     * Is this plan or package on sale?
     *
     * @param string $type subscription | package
     * @param int $id
     * @return bool
     */
    private static function on_sale(string $type, int $id): bool {
        global $DB;
        [$table, $flag] = $type === 'subscription' ? ['nit_subscription', 'subscriptions'] : ['nit_package', 'packages'];
        if (!$DB->get_manager()->table_exists($table)) {
            return false;
        }
        if (class_exists('\local_license\license') && !\local_license\license::has_feature($flag)) {
            return false;
        }
        return $DB->record_exists($table, ['id' => $id, 'status' => 'active']);
    }
}
