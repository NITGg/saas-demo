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

namespace local_nit_finance\api;

use local_nit_finance\service\earnings_service;
use local_nit_finance\service\wallet_service;
use local_nit_finance\service\withdrawal_service;

/**
 * Public facade for the NIT Finance engine. Every amount crossing this boundary is
 * an integer in minor currency units (piastres).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class wallet {
    /**
     * Record the teacher/platform split for a completed lesson (US-FN-1-4). Idempotent.
     *
     * @param int $lessonid
     * @param int $teacherid
     * @param int $studentid
     * @param int $purchaseid
     * @param int $flexvalueminor value of one Flex, minor units
     * @return array earning summary
     */
    public static function distribute(int $lessonid, int $teacherid, int $studentid, int $purchaseid,
            int $flexvalueminor): array {
        return (new earnings_service())->distribute($lessonid, $teacherid, $studentid, $purchaseid, $flexvalueminor);
    }

    /**
     * Reverse the active earning for a lesson (finance side of US-FN-1-5). The caller returns the
     * Flex and updates the lesson.
     *
     * @param int $lessonid
     * @param int $adminid
     * @param string $reason required
     * @return array reversed amounts
     */
    public static function reverse_earning(int $lessonid, int $adminid, string $reason): array {
        return (new earnings_service())->reverse($lessonid, $adminid, $reason);
    }

    /**
     * Teacher wallet summary (minor units).
     *
     * @param int $teacherid
     * @return array
     */
    public static function teacher(int $teacherid): array {
        return (new wallet_service())->teacher_wallet($teacherid);
    }

    /**
     * A teacher's available balance in minor units.
     *
     * @param int $teacherid
     * @return int
     */
    public static function available_balance(int $teacherid): int {
        return (new wallet_service())->available_balance($teacherid);
    }

    /**
     * Platform wallet (minor units). The "money in" and "unconsumed Flex value" figures are supplied
     * by the Flex plugin, which owns those records.
     *
     * @param int $totalpaymentsminor
     * @param int $undistributedminor
     * @return array
     */
    public static function platform(int $totalpaymentsminor, int $undistributedminor): array {
        return (new wallet_service())->platform_wallet($totalpaymentsminor, $undistributedminor);
    }

    /**
     * Teacher requests a withdrawal (US-FN-2-1).
     *
     * @param int $teacherid
     * @param int $amountminor
     * @param string $method
     * @param string $account
     * @return array
     */
    public static function request_withdrawal(int $teacherid, int $amountminor, string $method,
            string $account): array {
        return (new withdrawal_service())->request($teacherid, $amountminor, $method, $account);
    }

    /**
     * Admin processes a withdrawal (US-FN-2-2).
     *
     * @param int $adminid
     * @param int $withdrawalid
     * @param string $action approve | reject | pay
     * @param array $opts reason (reject) | reference (pay)
     * @return array
     */
    public static function process_withdrawal(int $adminid, int $withdrawalid, string $action,
            array $opts = []): array {
        return (new withdrawal_service())->process($adminid, $withdrawalid, $action, $opts);
    }

    /**
     * All withdrawals for the admin queue.
     *
     * @param string $status optional status filter
     * @return array
     */
    public static function list_withdrawals(string $status = ''): array {
        return (new withdrawal_service())->list_all($status);
    }

    /**
     * A teacher's own withdrawals.
     *
     * @param int $teacherid
     * @return array
     */
    public static function teacher_withdrawals(int $teacherid): array {
        return (new withdrawal_service())->list_for_teacher($teacherid);
    }
}
