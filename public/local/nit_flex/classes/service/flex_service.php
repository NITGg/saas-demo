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

namespace local_nit_flex\service;

use local_nit_core\base\service;
use local_nit_flex\entity\flex_tx;
use local_nit_flex\entity\package_purchase;
use local_nit_flex\exception\flex_exception;

/**
 * The Flex engine: reserve / consume / return / reverse a student's lesson credits,
 * with a full ledger. Ported from the reference flex_manager.
 *
 * Balance on {nit_package_purchase}:
 *   remaining_flex = available to reserve
 *   reserved_flex  = held for confirmed lessons
 *   consumed_flex  = permanently spent
 *
 *   reserve : remaining -n, reserved +n   (ledger -n on remaining)
 *   consume : reserved  -n, consumed +n   (ledger  0 — remaining lowered at reserve)
 *   return  : reserved  -n, remaining +n  (ledger +n)
 *   reverse : consumed  -n, remaining +n  (ledger +n)
 *
 * n is the lesson's Flex count — the teacher's price per lesson, 1 by default.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flex_service extends service {
    /**
     * Reserve a lesson's Flex from the student's active package when it is confirmed (US-FN-1-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @param int $count how many Flex the lesson costs
     * @return int the purchase id the Flex was reserved from
     */
    public function reserve(int $studentid, int $lessonid, int $performedby, string $reason = '', int $count = 1): int {
        $count = max(1, $count);
        $purchase = (new purchase_service())->active_entity($studentid);
        if (!$purchase || (int) $purchase->get('remaining_flex') < $count) {
            throw new flex_exception('err_noflex');
        }
        $before = (int) $purchase->get('remaining_flex');
        $after  = $before - $count;
        $purchase->set('remaining_flex', $after);
        $purchase->set('reserved_flex', (int) $purchase->get('reserved_flex') + $count);
        $purchase->update();

        $this->log($studentid, (int) $purchase->get('id'), $lessonid, flex_tx::TYPE_RESERVE, -$count,
            $before, $after, $performedby, $reason);
        return (int) $purchase->get('id');
    }

    /**
     * Permanently consume a lesson's reserved Flex (completed / late student cancel / student absent).
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @param int $count how many Flex the lesson reserved
     * @return void
     */
    public function consume(int $studentid, int $purchaseid, int $lessonid, int $performedby,
            string $reason = '', int $count = 1): void {
        $count = max(1, $count);
        $purchase = $this->purchase_or_fail($purchaseid);
        $remaining = (int) $purchase->get('remaining_flex');
        $purchase->set('reserved_flex', max(0, (int) $purchase->get('reserved_flex') - $count));
        $purchase->set('consumed_flex', (int) $purchase->get('consumed_flex') + $count);
        $purchase->update();

        $this->log($studentid, $purchaseid, $lessonid, flex_tx::TYPE_CONSUME, 0,
            $remaining, $remaining, $performedby, $reason);
    }

    /**
     * Return a lesson's reserved Flex to the student's balance (US-FN-1-3).
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @param int $count how many Flex the lesson reserved
     * @return void
     */
    public function return_flex(int $studentid, int $purchaseid, int $lessonid, int $performedby,
            string $reason = '', int $count = 1): void {
        $count = max(1, $count);
        $purchase = $this->purchase_or_fail($purchaseid);
        $before = (int) $purchase->get('remaining_flex');
        $purchase->set('remaining_flex', $before + $count);
        $purchase->set('reserved_flex', max(0, (int) $purchase->get('reserved_flex') - $count));
        $this->revive_if_used($purchase);
        $purchase->update();

        $this->log($studentid, $purchaseid, $lessonid, flex_tx::TYPE_RETURN, $count,
            $before, $before + $count, $performedby, $reason);
        $this->close_if_ended($purchase);
    }

    /**
     * Reverse a lesson's consumed Flex back to the student's balance (admin reversal, US-FN-1-5).
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @param int $count how many Flex the lesson consumed
     * @return void
     */
    public function reverse_consumed(int $studentid, int $purchaseid, int $lessonid, int $performedby,
            string $reason = '', int $count = 1): void {
        $count = max(1, $count);
        $purchase = $this->purchase_or_fail($purchaseid);
        $before = (int) $purchase->get('remaining_flex');
        $purchase->set('remaining_flex', $before + $count);
        $purchase->set('consumed_flex', max(0, (int) $purchase->get('consumed_flex') - $count));
        $this->revive_if_used($purchase);
        $purchase->update();

        $this->log($studentid, $purchaseid, $lessonid, flex_tx::TYPE_RETURN, $count,
            $before, $before + $count, $performedby, $reason);
        $this->close_if_ended($purchase);
    }

    /**
     * Record Flexes granted to a student's balance at purchase/assignment time.
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $amount opening balance
     * @param int $performedby
     * @param string $type flex_tx::TYPE_PURCHASE | TYPE_ASSIGN
     * @param string $reason
     * @return void
     */
    public function log_grant(int $studentid, int $purchaseid, int $amount, int $performedby,
            string $type, string $reason = ''): void {
        $this->log($studentid, $purchaseid, 0, $type, $amount, 0, $amount, $performedby, $reason);
    }

    /**
     * Zero a student's remaining balance when a package is unassigned. No-op if nothing remains.
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $performedby
     * @param string $reason
     * @return void
     */
    public function log_revoke(int $studentid, int $purchaseid, int $performedby, string $reason = ''): void {
        $purchase = package_purchase::get_record(['id' => $purchaseid]);
        if (!$purchase || (int) $purchase->get('remaining_flex') <= 0) {
            return;
        }
        $before = (int) $purchase->get('remaining_flex');
        $purchase->set('remaining_flex', 0);
        $purchase->update();
        $this->log($studentid, $purchaseid, 0, flex_tx::TYPE_ADJUST, -$before, $before, 0, $performedby, $reason);
    }

    /**
     * The value of one Flex from a purchase, in minor units (price_paid / flex_count, rounded).
     *
     * @param int $purchaseid
     * @return int
     */
    public function value_for_purchase(int $purchaseid): int {
        $purchase = package_purchase::get_record(['id' => $purchaseid]);
        if (!$purchase || (int) $purchase->get('flex_count') <= 0) {
            return 0;
        }
        return (int) round((int) $purchase->get('price_paid_minor') / (int) $purchase->get('flex_count'));
    }

    /**
     * A student's full Flex ledger, most recent first.
     *
     * @param int $userid
     * @return array
     */
    public function history(int $userid): array {
        $rows = flex_tx::get_records(['userid' => $userid], 'timecreated', 'DESC');
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int) $r->get('id'),
                'purchaseid'     => (int) $r->get('purchaseid'),
                'lessonid'       => (int) $r->get('lessonid'),
                'type'           => $r->get('type'),
                'amount'         => (int) $r->get('amount'),
                'balance_before' => (int) $r->get('balance_before'),
                'balance_after'  => (int) $r->get('balance_after'),
                'reason'         => $r->get('reason'),
                'timecreated'    => (int) $r->get('timecreated'),
            ];
        }
        return $out;
    }

    /**
     * Load a purchase or throw.
     *
     * @param int $purchaseid
     * @return package_purchase
     */
    private function purchase_or_fail(int $purchaseid): package_purchase {
        $purchase = package_purchase::get_record(['id' => $purchaseid]);
        if (!$purchase) {
            throw new flex_exception('err_notfound');
        }
        return $purchase;
    }

    /**
     * Returning a Flex revives a purchase that had been fully used.
     *
     * @param package_purchase $purchase
     * @return void
     */
    private function revive_if_used(package_purchase $purchase): void {
        if ($purchase->get('status') === package_purchase::STATUS_FULLY_USED) {
            $purchase->set('status', package_purchase::STATUS_ACTIVE);
        }
    }

    /**
     * A Flex that comes back to a purchase that already ended (expired or cancelled) cannot be
     * used again: close it straight away so its value goes to the platform.
     *
     * @param package_purchase $purchase
     * @return void
     */
    private function close_if_ended(package_purchase $purchase): void {
        if (in_array($purchase->get('status'), [package_purchase::STATUS_EXPIRED, package_purchase::STATUS_CANCELLED], true)) {
            (new purchase_service())->expire_one((int) $purchase->get('id'));
        }
    }

    /**
     * Write one ledger row.
     *
     * @param int $userid
     * @param int $purchaseid
     * @param int $lessonid
     * @param string $type
     * @param int $amount
     * @param int $before
     * @param int $after
     * @param int $performedby
     * @param string $reason
     * @return void
     */
    private function log(int $userid, int $purchaseid, int $lessonid, string $type, int $amount,
            int $before, int $after, int $performedby, string $reason): void {
        (new flex_tx(0, (object) [
            'userid'         => $userid,
            'purchaseid'     => $purchaseid,
            'lessonid'       => $lessonid,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $before,
            'balance_after'  => $after,
            'performedby'    => $performedby,
            'reason'         => $reason !== '' ? $reason : null,
        ]))->create();
    }
}
