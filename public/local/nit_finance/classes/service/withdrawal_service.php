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

namespace local_nit_finance\service;

use local_nit_core\base\service;
use local_nit_finance\entity\withdrawal;
use local_nit_finance\exception\finance_exception;

/**
 * Teacher withdrawal requests and admin processing (US-FN-2-1 / US-FN-2-2).
 *
 * State machine: pending → approved → paid; pending|approved → rejected (releases the hold).
 * A held amount (pending or approved) is subtracted from the teacher's available balance by
 * {@see wallet_service}; a rejected request no longer counts, so its amount returns to balance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class withdrawal_service extends service {
    /**
     * Teacher requests a withdrawal (US-FN-2-1).
     *
     * @param int $teacherid
     * @param int $amountminor requested amount in minor units
     * @param string $method payout method
     * @param string $account payout account details
     * @return array the created request
     */
    public function request(int $teacherid, int $amountminor, string $method, string $account): array {
        if ($amountminor <= 0) {
            throw new finance_exception('err_amountpositive');
        }

        // Serialise concurrent withdrawal requests for the SAME teacher. The
        // balance check and the row insert are two separate steps; without a
        // lock, two simultaneous requests could both read the same balance,
        // both pass the check, and together hold more than is available
        // (a time-of-check/time-of-use race). The lock is keyed on the teacher
        // so different teachers never block each other. A short timeout keeps a
        // stuck request from hanging the caller.
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_nit_finance');
        $lock = $lockfactory->get_lock('withdrawal_request_' . $teacherid, 10);
        if (!$lock) {
            throw new finance_exception('err_busy');
        }

        try {
            $wallet = new wallet_service();
            if ($amountminor > $wallet->available_balance($teacherid)) {
                throw new finance_exception('err_insufficientbalance');
            }
            $wd = new withdrawal(0, (object) [
                'teacherid'    => $teacherid,
                'amount_minor' => $amountminor,
                'method'       => $method !== '' ? $method : 'bank',
                'account'      => $account !== '' ? $account : null,
                'status'       => withdrawal::STATUS_PENDING,
            ]);
            $wd->create();
            return self::format($wd);
        } finally {
            // Always release, even if the balance check throws.
            $lock->release();
        }
    }

    /**
     * Admin processes a withdrawal (US-FN-2-2).
     *
     * @param int $adminid
     * @param int $withdrawalid
     * @param string $action approve | reject | pay
     * @param array $opts reason (reject) | reference (pay)
     * @return array the updated request
     */
    public function process(int $adminid, int $withdrawalid, string $action, array $opts = []): array {
        $wd = withdrawal::get_record(['id' => $withdrawalid]);
        if (!$wd) {
            throw new finance_exception('err_withdrawalnotfound');
        }
        $status = $wd->get('status');

        switch ($action) {
            case 'approve':
                if ($status !== withdrawal::STATUS_PENDING) {
                    throw new finance_exception('err_withdrawalstate');
                }
                $wd->set('status', withdrawal::STATUS_APPROVED);
                break;

            case 'reject':
                if (!in_array($status, [withdrawal::STATUS_PENDING, withdrawal::STATUS_APPROVED], true)) {
                    throw new finance_exception('err_withdrawalstate');
                }
                $reason = trim($opts['reason'] ?? '');
                if ($reason === '') {
                    throw new finance_exception('err_reasonrequired');
                }
                $wd->set('status', withdrawal::STATUS_REJECTED);
                $wd->set('reason', $reason);
                break;

            case 'pay':
                if ($status !== withdrawal::STATUS_APPROVED) {
                    throw new finance_exception('err_withdrawalstate');
                }
                $wd->set('status', withdrawal::STATUS_PAID);
                $wd->set('reference', trim($opts['reference'] ?? ''));
                break;

            default:
                throw new finance_exception('err_badaction');
        }

        $wd->set('processedby', $adminid);
        $wd->set('timeprocessed', time());
        $wd->update();

        return self::format($wd);
    }

    /**
     * A teacher's own withdrawals, most recent first.
     *
     * @param int $teacherid
     * @return array
     */
    public function list_for_teacher(int $teacherid): array {
        $rows = withdrawal::get_records(['teacherid' => $teacherid], 'timecreated', 'DESC');
        return array_map([self::class, 'format'], array_values($rows));
    }

    /**
     * All withdrawals for the admin queue, optionally filtered by status, with teacher names.
     *
     * @param string $status optional status filter
     * @return array
     */
    public function list_all(string $status = ''): array {
        global $DB;
        $params = [];
        $where = '';
        if ($status !== '') {
            $where = 'WHERE w.status = :status';
            $params['status'] = $status;
        }
        $sql = "SELECT w.*, u.firstname, u.lastname, u.email
                  FROM {nit_withdrawal} w
                  JOIN {user} u ON u.id = w.teacherid
                  $where
              ORDER BY w.timecreated DESC, w.id DESC";
        $rows = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'            => (int) $r->id,
                'teacherid'     => (int) $r->teacherid,
                'teacher_name'  => trim($r->firstname . ' ' . $r->lastname),
                'teacher_email' => $r->email,
                'amount_minor'  => (int) $r->amount_minor,
                'method'        => $r->method,
                'account'       => $r->account,
                'reference'     => $r->reference,
                'status'        => $r->status,
                'reason'        => $r->reason,
                'timecreated'   => (int) $r->timecreated,
                'timeprocessed' => (int) $r->timeprocessed,
            ];
        }
        return $out;
    }

    /**
     * Shape a withdrawal entity as a plain array.
     *
     * @param withdrawal $w
     * @return array
     */
    private static function format(withdrawal $w): array {
        return [
            'id'            => (int) $w->get('id'),
            'teacherid'     => (int) $w->get('teacherid'),
            'amount_minor'  => (int) $w->get('amount_minor'),
            'method'        => $w->get('method'),
            'account'       => $w->get('account'),
            'reference'     => $w->get('reference'),
            'status'        => $w->get('status'),
            'reason'        => $w->get('reason'),
            'processedby'   => (int) $w->get('processedby'),
            'timeprocessed' => (int) $w->get('timeprocessed'),
            'timecreated'   => (int) $w->get('timecreated'),
        ];
    }
}
