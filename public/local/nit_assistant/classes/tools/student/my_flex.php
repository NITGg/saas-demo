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
 * The signed-in student's own Flex lesson credits (local_nit_flex): balance,
 * credits held for booked lessons, the package's expiry, and recent movements.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_flex extends tool {
    /** Ledger lines returned. */
    private const LEDGER = 10;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_flex';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s own Flex lesson credits: available, held for booked lessons (reserved), the active '
            . 'package and when it expires, and the last credit movements (purchase, reserve, consume, return, '
            . 'expire) with reasons. Use it for "how many lessons do I have left", "where did my credit go".';
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
        if (!class_exists('\local_nit_flex\local\mobile_api') || !helper::lib('nit_flex') || !local_nit_flex_enabled()) {
            return self::ok(['note' => 'This academy has no lesson packages.']);
        }
        $flex = \local_nit_flex\local\mobile_api::get_my_flex($ctx->userid);
        $history = \local_nit_flex\local\mobile_api::get_flex_history($ctx->userid, 0, self::LEDGER);
        $date = get_string('strftimedatetimeshort', 'langconfig');
        $active = $flex['active'] ?? null;
        $out = [
            'available_credits' => (int) $flex['available_flex'],
            'reserved_credits' => (int) $flex['reserved_flex'],
            'has_package' => (bool) $flex['has_package'],
            'package' => $active ? [
                'name' => helper::ml((string) $active['name'], $ctx->lang),
                'total' => (int) $active['total_flex'],
                'remaining' => (int) $active['remaining_flex'],
                'used' => (int) $active['consumed_flex'],
                'status' => (string) $active['status_label'],
                'expires' => !empty($active['expires_at']) ? userdate((int) $active['expires_at'], $date) : 'never',
            ] : null,
            'recent' => array_map(static fn($h): array => [
                'what' => (string) $h['type_label'],
                'credits' => (int) $h['amount'],
                'balance_after' => (int) $h['balance_after'],
                'reason' => helper::plain((string) $h['reason'], 120),
                'when' => userdate((int) $h['timecreated'], $date),
            ], $history['history']),
        ];
        helper::button(
            $ctx,
            $active ? 'action_myflex' : 'action_packages',
            helper::url('/local/nit_lessons/student.php', ['tab' => $active ? 'packages' : 'flexavailable'])
        );
        return self::ok($out);
    }
}
