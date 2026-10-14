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

namespace local_nit_assistant\tools\teacher;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * The signed-in teacher's own money (local_nit_finance): what they can withdraw,
 * total earned, recent earnings and their withdrawal requests. Payout account
 * details are masked; the assistant never requests a withdrawal.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_earnings extends tool {
    /** Earnings and withdrawals listed. */
    private const MAX = 10;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_earnings';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The teacher\'s own earnings: balance they can withdraw, total earned, withdrawals pending and paid, '
            . 'their share percent, the latest earnings (what, amount, status) and their withdrawal requests with '
            . 'status and reason. Use it for "how much did I earn", "when is my payout", "why was my withdrawal '
            . 'rejected". Withdrawals are requested on the earnings page, not in the chat.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['teacher'];
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
            return self::ok(['note' => 'This academy has no teacher earnings.']);
        }
        $api = '\local_nit_finance\local\mobile_api';
        try {
            $wallet = $api::get_teacher_wallet($ctx->userid);
        } catch (\moodle_exception $e) {
            return self::ok(['note' => 'No earnings yet.']);
        }
        $earnings = $api::get_my_earnings($ctx->userid, 0, self::MAX)['earnings'];
        $withdrawals = array_slice($api::get_my_withdrawals($ctx->userid)['withdrawals'], 0, self::MAX);
        $date = get_string('strftimedatetimeshort', 'langconfig');
        helper::button($ctx, 'action_earnings', helper::url('/local/nit_lessons/student.php', ['tab' => 'earnings']));
        return self::ok([
            'currency' => (string) $wallet['currency'],
            'available_to_withdraw' => helper::money($wallet['available_balance']),
            'total_earned' => helper::money($wallet['total_earned']),
            'pending_withdrawals' => helper::money($wallet['pending_withdrawals']),
            'total_withdrawn' => helper::money($wallet['total_withdrawn']),
            'your_share_percent' => (int) $wallet['teacher_percent'],
            'recent_earnings' => array_map(static fn($e): array => [
                'for' => helper::plain((string) $e['item_name'], 120),
                'course' => (string) $e['coursename'],
                'amount' => helper::money($e['teacher_amount']),
                'status' => (string) $e['status_label'],
                'when' => userdate((int) $e['timecreated'], $date),
            ], $earnings),
            'withdrawals' => array_map(static fn($w): array => [
                'amount' => helper::money($w['amount']),
                'method' => (string) $w['method_label'],
                'account' => self::mask((string) $w['account']),
                'status' => (string) $w['status_label'],
                'reason' => helper::plain((string) $w['reason'], 200),
                'requested' => userdate((int) $w['timecreated'], $date),
                'processed' => $w['timeprocessed'] ? userdate((int) $w['timeprocessed'], $date) : null,
            ], $withdrawals),
        ]);
    }

    /**
     * Only the last 4 characters of a payout account.
     *
     * @param string $account
     * @return string
     */
    private static function mask(string $account): string {
        $account = trim($account);
        return $account === '' ? '' : '…' . \core_text::substr($account, -4);
    }
}
