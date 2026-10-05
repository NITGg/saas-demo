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

namespace local_nit_finance\local;

use local_nit_finance\exception\finance_exception;

/**
 * The three wallets: the platform's (one), each teacher's and each student's.
 *
 * A balance only changes through {@see self::move()}, which also writes the
 * ledger line, so every balance can be explained line by line.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wallets {

    /** The platform's wallet (userid 0). */
    public const PLATFORM = 'platform';
    /** A teacher's earnings wallet. */
    public const TEACHER = 'teacher';
    /** A student's spending wallet. */
    public const STUDENT = 'student';

    /** Money added to a student wallet (admin, code). */
    public const KIND_TOPUP = 'topup';
    /** Money spent by a student. */
    public const KIND_PURCHASE = 'purchase';
    /** A share of a sale (teacher, platform). */
    public const KIND_EARNING = 'earning';
    /** Money paid out to a teacher. */
    public const KIND_WITHDRAWAL = 'withdrawal';
    /** Manual correction by an admin. */
    public const KIND_ADJUSTMENT = 'adjustment';

    /**
     * A wallet, created empty on first use.
     *
     * @param string $type platform | teacher | student
     * @param int $userid 0 for the platform
     * @return \stdClass
     */
    public static function get(string $type, int $userid = 0): \stdClass {
        global $DB;
        if (!in_array($type, [self::PLATFORM, self::TEACHER, self::STUDENT], true)) {
            throw new \coding_exception('Unknown wallet type ' . $type);
        }
        $userid = $type === self::PLATFORM ? 0 : $userid;
        $wallet = $DB->get_record('nit_wallet', ['ownertype' => $type, 'userid' => $userid]);
        if ($wallet) {
            return $wallet;
        }
        $now = time();
        $wallet = (object) ['ownertype' => $type, 'userid' => $userid, 'balance_minor' => 0,
            'timecreated' => $now, 'timemodified' => $now];
        try {
            $wallet->id = $DB->insert_record('nit_wallet', $wallet);
        } catch (\dml_write_exception $e) {
            // Created at the same moment by another request.
            $wallet = $DB->get_record('nit_wallet', ['ownertype' => $type, 'userid' => $userid], '*', MUST_EXIST);
        }
        return $wallet;
    }

    /**
     * Current balance in minor units (0 for a wallet never used).
     *
     * @param string $type
     * @param int $userid
     * @return int
     */
    public static function balance(string $type, int $userid = 0): int {
        global $DB;
        $userid = $type === self::PLATFORM ? 0 : $userid;
        return (int) $DB->get_field('nit_wallet', 'balance_minor', ['ownertype' => $type, 'userid' => $userid]);
    }

    /**
     * Add (positive) or take (negative) money and write the ledger line.
     *
     * The balance is changed with one UPDATE (the database serialises
     * concurrent moves on the same wallet), then read back inside the same
     * transaction: if it went below zero the whole transaction is rolled back.
     *
     * @param string $type
     * @param int $userid
     * @param int $amountminor signed
     * @param string $kind one of the KIND_ constants
     * @param array $ref optional itemtype, itemid, purchaseid, note
     * @param bool $allownegative let the balance go below zero (pay-outs of old earnings)
     * @return \stdClass the ledger line
     */
    public static function move(string $type, int $userid, int $amountminor, string $kind, array $ref = [],
            bool $allownegative = false): \stdClass {
        global $DB, $USER;
        $wallet = self::get($type, $userid);
        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            $DB->execute('UPDATE {nit_wallet} SET balance_minor = balance_minor + :amount, timemodified = :now WHERE id = :id',
                ['amount' => $amountminor, 'now' => $now, 'id' => $wallet->id]);
            $after = (int) $DB->get_field('nit_wallet', 'balance_minor', ['id' => $wallet->id]);
            if ($after < 0 && $amountminor < 0 && !$allownegative) {
                throw new finance_exception('err_insufficientwallet');
            }
            $line = (object) [
                'walletid' => $wallet->id,
                'amount_minor' => $amountminor,
                'balance_after_minor' => $after,
                'kind' => $kind,
                'itemtype' => (string) ($ref['itemtype'] ?? ''),
                'itemid' => (int) ($ref['itemid'] ?? 0),
                'purchaseid' => (int) ($ref['purchaseid'] ?? 0),
                'note' => \core_text::substr((string) ($ref['note'] ?? ''), 0, 255),
                'usermodified' => isset($USER->id) ? (int) $USER->id : 0,
                'timecreated' => $now,
            ];
            $line->id = $DB->insert_record('nit_wallet_txn', $line);
            $transaction->allow_commit();
            return $line;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Credit a student wallet with an online payment (local_payments, e.g.
     * Kashier). The gateway's webhook and the student's redirect may both
     * report the same payment: it is credited once only.
     *
     * @param int $userid
     * @param int $amountminor
     * @param int $transactionid local_payments_transactions id
     * @param string $orderid shown in the wallet history
     * @return bool true when credited now, false when it already was
     */
    public static function topup_from_payment(int $userid, int $amountminor, int $transactionid, string $orderid): bool {
        return self::locked('payment_' . $transactionid, function () use ($userid, $amountminor, $transactionid, $orderid) {
            global $DB;
            $done = $DB->record_exists_sql(
                "SELECT 1
                   FROM {nit_wallet_txn} t
                   JOIN {nit_wallet} w ON w.id = t.walletid
                  WHERE w.ownertype = :type AND w.userid = :userid
                    AND t.kind = :kind AND t.itemtype = :itemtype AND t.itemid = :itemid",
                ['type' => self::STUDENT, 'userid' => $userid, 'kind' => self::KIND_TOPUP,
                 'itemtype' => 'payment', 'itemid' => $transactionid]);
            if ($done || $amountminor <= 0) {
                return false;
            }
            self::move(self::STUDENT, $userid, $amountminor, self::KIND_TOPUP,
                ['itemtype' => 'payment', 'itemid' => $transactionid, 'note' => $orderid]);
            return true;
        });
    }

    /**
     * Latest ledger lines of a wallet, newest first.
     *
     * @param string $type
     * @param int $userid
     * @param int $limit
     * @param int $offset lines to skip (paging)
     * @return \stdClass[]
     */
    public static function history(string $type, int $userid = 0, int $limit = 50, int $offset = 0): array {
        global $DB;
        $userid = $type === self::PLATFORM ? 0 : $userid;
        return array_values($DB->get_records_sql(
            "SELECT t.*
               FROM {nit_wallet_txn} t
               JOIN {nit_wallet} w ON w.id = t.walletid
              WHERE w.ownertype = :type AND w.userid = :userid
           ORDER BY t.timecreated DESC, t.id DESC",
            ['type' => $type, 'userid' => $userid], max(0, $offset), $limit));
    }

    /**
     * How many ledger lines a wallet has.
     *
     * @param string $type
     * @param int $userid
     * @return int
     */
    public static function history_count(string $type, int $userid = 0): int {
        global $DB;
        $userid = $type === self::PLATFORM ? 0 : $userid;
        return $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {nit_wallet_txn} t
               JOIN {nit_wallet} w ON w.id = t.walletid
              WHERE w.ownertype = :type AND w.userid = :userid",
            ['type' => $type, 'userid' => $userid]);
    }

    /**
     * Run $fn while holding the student's wallet lock, so two purchases or
     * redemptions by the same student never interleave.
     *
     * @param string $key lock key
     * @param callable $fn
     * @return mixed what $fn returns
     */
    public static function locked(string $key, callable $fn) {
        $factory = \core\lock\lock_config::get_lock_factory('local_nit_finance');
        $lock = $factory->get_lock($key, 10);
        if (!$lock) {
            throw new finance_exception('err_walletbusy');
        }
        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }
}
