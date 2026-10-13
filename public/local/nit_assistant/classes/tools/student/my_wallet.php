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
 * The signed-in student's own wallet (local_nit_finance): balance and the last
 * movements (top-ups, purchases, adjustments).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_wallet extends tool {
    /** Lines returned. */
    private const LINES = 10;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_wallet';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s own wallet: balance in EGP and the last movements (top-up, purchase, adjustment) with '
            . 'amounts and notes, plus whether online top-up is available. Use it for balance and "where did my money go".';
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
        if (!class_exists('\local_nit_finance\local\mobile_api')) {
            return self::ok(['note' => 'This academy has no wallet.']);
        }
        $wallet = \local_nit_finance\local\mobile_api::get_wallet($ctx->userid);
        $lines = \local_nit_finance\local\mobile_api::get_wallet_history($ctx->userid, 0, self::LINES)['lines'];
        $date = get_string('strftimedatetimeshort', 'langconfig');
        helper::button($ctx, 'action_wallet', helper::url('/local/nit_lessons/student.php', ['tab' => 'wallet']));
        return self::ok([
            'balance' => helper::money($wallet['balance']),
            'currency' => (string) $wallet['currency'],
            'online_topup' => (bool) ($wallet['topup']['online_payment_available'] ?? false),
            'recent' => array_map(static fn($l): array => [
                'what' => (string) $l['kind_label'],
                'amount' => helper::money($l['amount']),
                'balance_after' => helper::money($l['balance_after']),
                'note' => helper::plain((string) ($l['note'] ?? ''), 120),
                'when' => userdate((int) $l['timecreated'], $date),
            ], $lines),
        ]);
    }
}
