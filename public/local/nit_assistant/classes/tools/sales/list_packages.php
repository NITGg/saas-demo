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
 * Flex lesson packages on sale (local_nit_flex), each priced by the same quote
 * the checkout uses, so the automatic offer is included.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_packages extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'list_packages';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Flex packages for live one-to-one lessons: name, number of lesson credits, price in EGP, price after '
            . 'the current offer, price per credit and how many days the credits stay valid. A teacher\'s lesson costs '
            . 'a number of credits (see find_teachers). Use it for questions about private/live lessons and packages.';
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
        if (!class_exists('\local_nit_flex\api\packages') || !helper::lib('nit_flex') || !local_nit_flex_enabled()) {
            return self::ok(['packages' => [], 'note' => 'This academy does not sell lesson packages.']);
        }
        $out = [];
        foreach (\local_nit_flex\api\packages::available() as $p) {
            try {
                $quote = \local_nit_flex\api\purchase::quote($ctx->userid, (int) $p['id']);
            } catch (\moodle_exception $e) {
                continue; // Not on sale any more.
            }
            $price = $quote['original_minor'] / 100;
            $final = $quote['final_minor'] / 100;
            $credits = max(1, (int) $p['flex_count']);
            $item = [
                'id' => (int) $p['id'],
                'name' => helper::ml($p['name'], $ctx->lang),
                'description' => helper::plain(helper::ml($p['description'], $ctx->lang), 250),
                'lesson_credits' => $credits,
                'price' => helper::money($price),
                'currency' => helper::EGP,
                'price_per_credit' => helper::money($final / $credits),
                'valid_days' => (int) $p['expiration_days'] ?: null,
            ];
            if ($quote['final_minor'] < $quote['original_minor']) {
                $item['offer_price'] = helper::money($final);
            }
            $out[] = $item;
        }
        if ($out) {
            helper::button($ctx, 'action_packages', helper::url('/local/nit_lessons/student.php', ['tab' => 'flexavailable']));
        }
        return self::ok(['packages' => $out]);
    }
}
