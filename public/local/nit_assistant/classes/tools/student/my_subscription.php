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

namespace local_nit_assistant\tools\student;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * The signed-in student's own subscriptions (local_nit_subscriptions):
 * the current plan, days left, the courses it opens, and past plans.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_subscription extends tool {
    /** Past plans listed. */
    private const HISTORY = 5;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_subscription';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s own subscription: the active plan, when it started and ends, days left, the courses it '
            . 'opens, and their past plans. Use it for "is my subscription active", "when does it end", "why can\'t I '
            . 'open this course", renewal questions.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['student'];
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
            !helper::lib('nit_subscriptions') || !local_nit_subscriptions_feature()
                || !class_exists('\local_nit_subscriptions\subscription_purchase_manager')
        ) {
            return self::ok(['active' => null, 'note' => 'This academy has no subscriptions.']);
        }
        $rows = \local_nit_subscriptions\subscription_purchase_manager::get_my_subscriptions($ctx->userid);
        $format = get_string('strftimedate', 'langconfig');
        $plan = static function ($s) use ($ctx, $format): array {
            $s = (array) $s;
            return [
                'plan' => helper::ml((string) $s['name'], $ctx->lang),
                'status' => (string) $s['status'],
                'started' => !empty($s['timeactivated']) ? userdate((int) $s['timeactivated'], $format) : null,
                'ends' => !empty($s['expires_at']) ? userdate((int) $s['expires_at'], $format) : 'never',
                'days_left' => isset($s['remaining_days']) ? (int) $s['remaining_days'] : null,
                'courses' => array_slice(array_map(
                    static fn($c) => helper::ml((string) ((array) $c)['fullname'], $ctx->lang),
                    (array) ($s['courses'] ?? [])
                ), 0, 15),
            ];
        };
        $active = null;
        $past = [];
        foreach ($rows as $row) {
            if ($active === null && ((array) $row)['status'] === 'active') {
                $active = $plan($row);
            } else if (count($past) < self::HISTORY) {
                $past[] = $plan($row);
            }
        }
        $tab = $active ? 'mysubs' : 'subavailable';
        helper::button(
            $ctx,
            $active ? 'action_mysubs' : 'action_plans',
            helper::url('/local/nit_lessons/student.php', ['tab' => $tab])
        );
        return self::ok(['active' => $active, 'past' => $past]);
    }
}
