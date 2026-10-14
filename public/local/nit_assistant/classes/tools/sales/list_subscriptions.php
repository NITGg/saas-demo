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
 * The subscription plans on sale, as the site's own plan list shows them
 * (local_nit_subscriptions: price, duration, courses it opens, current offer).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_subscriptions extends tool {
    /** Course names listed per plan (the count is always given). */
    private const MAXCOURSES = 15;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'list_subscriptions';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Subscription plans on sale now: name, price in EGP, duration in days, how many and which courses '
            . 'the plan opens, the current automatic offer (final price) and group seat prices for schools/companies. '
            . 'Use it for any question about plans, subscriptions, "how much is it", or which plan opens a course.';
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
        if (
            !helper::lib('nit_subscriptions') || !function_exists('nit_subscriptions_available')
                || !local_nit_subscriptions_feature()
        ) {
            return self::ok(['plans' => [], 'note' => 'This academy does not sell subscription plans.']);
        }
        $plans = [];
        foreach (nit_subscriptions_available() as $p) {
            $courses = array_map(static fn($c): string => helper::ml(
                is_array($c) ? ($c['fullname'] ?? '') : ($c->fullname ?? ''),
                $ctx->lang
            ), $p['courses']);
            $plan = [
                'id' => (int) $p['id'],
                'name' => helper::ml($p['name'], $ctx->lang),
                'description' => helper::plain($p['description'], 300),
                'price' => helper::money($p['price']),
                'currency' => helper::EGP,
                'duration_days' => (int) $p['duration_days'],
                'courses_count' => (int) $p['courses_count'],
                'courses' => array_slice($courses, 0, self::MAXCOURSES),
            ];
            if (!empty($p['offer_final']) && (float) $p['offer_final'] < (float) $p['price']) {
                $plan['offer'] = ['label' => (string) $p['offer_label'], 'final_price' => helper::money($p['offer_final'])];
            }
            if (!empty($p['b2b_enabled']) && !empty($p['seat_options'])) {
                $plan['group_seats'] = array_map(static fn($s): array => [
                    'seats' => (int) ((array) $s)['seats'],
                    'price' => helper::money(((array) $s)['b2b_price'] ?? 0),
                ], array_values($p['seat_options']));
            }
            $plans[] = $plan;
        }
        if ($plans) {
            helper::button($ctx, 'action_plans', helper::url('/local/nit_lessons/student.php', ['tab' => 'subavailable']));
        }
        return self::ok(['plans' => $plans]);
    }
}
