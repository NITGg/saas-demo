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

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * Automatic offers running now (local_nit_commerce). Coupon codes are never
 * listed: a coupon is only checked when the visitor already has one
 * (preview_price).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_offers extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'list_offers';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Discount offers running now: name, percent or fixed amount off, end date and what they apply to '
            . '(courses, plans, packages). Offers apply automatically at checkout. This never lists coupon codes; '
            . 'if the visitor has a coupon, check it with preview_price. Never promise a discount that is not here.';
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
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        if (!class_exists('\local_nit_commerce\offer_manager') || !helper::commerce('offers')) {
            return self::ok(['offers' => []]);
        }
        $offers = [];
        foreach (\local_nit_commerce\offer_manager::get_available_offers() as $o) {
            $offers[] = [
                'name' => helper::ml($o['name_raw'] ?? $o['name'], $ctx->lang),
                // A fixed amount is in the price's own currency (EGP, or the course's country price).
                $o['discount_type'] === 'percent' ? 'percent_off' : 'amount_off' => helper::money($o['discount_value']),
                'ends' => $o['enddate'] > 0 ? userdate($o['enddate'], get_string('strftimedate', 'langconfig')) : null,
                'applies_to' => array_slice(array_map(static fn($i) => (string) $i['label'], $o['applies_to']), 0, 10),
            ];
        }
        return self::ok(['offers' => $offers]);
    }
}
