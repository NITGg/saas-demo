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
 * The signed-in student's own online payments (local_payments_transactions):
 * what each was for, amount, status in plain words, the invoice number, and for
 * a failed one the page to try again. Never card or provider details.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_payments extends tool {
    /** Payments returned. */
    private const MAX = 10;

    /** Status => plain meaning. */
    private const MEANING = [
        'pending' => 'started but not finished (not charged yet, or waiting for the payment provider)',
        'completed' => 'paid successfully',
        'failed' => 'failed; nothing was charged — it can be tried again',
        'cancelled' => 'cancelled before paying',
        'expired' => 'the payment page expired before paying',
        'timed_out' => 'the payment page expired before paying',
        'refunded' => 'refunded',
        'partially_refunded' => 'partly refunded',
        'voided' => 'voided by the payment provider',
        'chargeback' => 'disputed with the bank',
        'duplicate' => 'a duplicate attempt (ignored)',
    ];

    /** Statuses that can be paid again. */
    private const RETRY = ['failed', 'cancelled', 'expired', 'timed_out'];

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_payments';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s own recent online payments: what each was for (course, plan, package, wallet top-up), '
            . 'amount and currency, date, status with its meaning, invoice number, and a link to try again when a '
            . 'payment failed. Use it for "I paid but…", failed or pending payments, invoices.';
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
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_payments_transactions')) {
            return self::ok(['payments' => []]);
        }
        $rows = $DB->get_records(
            'local_payments_transactions',
            ['userid' => $ctx->userid],
            'timecreated DESC, id DESC',
            'id, courseid, amount, currency, status, metadata, timecreated',
            0,
            self::MAX
        );
        $invoices = [];
        if ($rows && $dbman->table_exists('local_payments_invoices')) {
            [$in, $params] = $DB->get_in_or_equal(array_keys($rows));
            $invoices = $DB->get_records_select_menu(
                'local_payments_invoices',
                "transaction_id {$in}",
                $params,
                '',
                'transaction_id, invoice_number'
            );
        }
        $date = get_string('strftimedatetimeshort', 'langconfig');
        $out = [];
        foreach ($rows as $t) {
            $meta = json_decode((string) $t->metadata, true) ?: [];
            $type = (string) ($meta['item_type'] ?? ((int) $t->courseid > 0 ? 'course' : 'other'));
            $item = ['for' => $type];
            if ((int) $t->courseid > 0) {
                $name = $DB->get_field('course', 'fullname', ['id' => $t->courseid]);
                $item['course'] = $name !== false ? format_string($name) : null;
            }
            $item += [
                'amount' => helper::money($t->amount),
                'currency' => (string) $t->currency,
                'date' => userdate((int) $t->timecreated, $date),
                'status' => (string) $t->status,
                'meaning' => self::MEANING[$t->status] ?? '',
            ];
            if (isset($invoices[$t->id])) {
                $item['invoice'] = (string) $invoices[$t->id];
            }
            if (in_array($t->status, self::RETRY, true) && class_exists('\local_payments\local\return_pages')) {
                $item['try_again_url'] = \local_payments\local\return_pages::retry($t)->out(false);
            }
            $out[] = $item;
        }
        if (file_exists($GLOBALS['CFG']->dirroot . '/local/payments/history.php')) {
            helper::button($ctx, 'action_payments', helper::url('/local/payments/history.php'));
        }
        return self::ok(['payments' => $out]);
    }
}
