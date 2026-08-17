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
use local_nit_finance\entity\earning;
use local_nit_finance\entity\withdrawal;

/**
 * Derives teacher and platform wallet balances by aggregation over the immutable
 * earning and withdrawal records — no stored balance is ever mutated directly.
 *
 * All amounts are integer minor units. Wallet-model reference:
 *   teacher available = Σ active teacher earnings − Σ (pending|approved|paid) withdrawals
 *   platform current  = total payments − total paid out
 *   invariant: current = undistributed + teachers_money + platform_earnings
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wallet_service extends service {
    /**
     * Sum a money column from a table for a where clause.
     *
     * @param string $table
     * @param string $column
     * @param string $where
     * @param array $params
     * @return int minor units
     */
    private function sum(string $table, string $column, string $where, array $params): int {
        global $DB;
        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM($column),0) FROM {" . $table . "} WHERE $where", $params);
    }

    /**
     * A teacher's available balance (minor units).
     *
     * @param int $teacherid
     * @return int
     */
    public function available_balance(int $teacherid): int {
        $earned = $this->sum('nit_earning', 'teacher_amount_minor',
            'teacherid = :tid AND status = :st', ['tid' => $teacherid, 'st' => earning::STATUS_ACTIVE]);
        $held = $this->sum('nit_withdrawal', 'amount_minor',
            'teacherid = :tid AND status IN (:s1, :s2, :s3)',
            ['tid' => $teacherid, 's1' => withdrawal::STATUS_PENDING,
             's2' => withdrawal::STATUS_APPROVED, 's3' => withdrawal::STATUS_PAID]);
        return $earned - $held;
    }

    /**
     * Full teacher wallet summary.
     *
     * @param int $teacherid
     * @return array
     */
    public function teacher_wallet(int $teacherid): array {
        $totalearned = $this->sum('nit_earning', 'teacher_amount_minor',
            'teacherid = :tid AND status = :st', ['tid' => $teacherid, 'st' => earning::STATUS_ACTIVE]);
        $reserved = $this->sum('nit_withdrawal', 'amount_minor',
            'teacherid = :tid AND status IN (:s1, :s2)',
            ['tid' => $teacherid, 's1' => withdrawal::STATUS_PENDING, 's2' => withdrawal::STATUS_APPROVED]);
        $paid = $this->sum('nit_withdrawal', 'amount_minor',
            'teacherid = :tid AND status = :st', ['tid' => $teacherid, 'st' => withdrawal::STATUS_PAID]);

        return [
            'teacherid'                 => $teacherid,
            'total_earned_minor'        => $totalearned,
            'available_balance_minor'   => $totalearned - $reserved - $paid,
            'pending_withdrawals_minor' => $reserved,
            'total_withdrawn_minor'     => $paid,
        ];
    }

    /**
     * Platform wallet. The two "money in" / "unconsumed" figures come from the Flex plugin
     * (which owns payments and purchases), keeping this service free of any Flex-table knowledge.
     *
     * @param int $totalpaymentsminor total successful payments taken (from nit_flex)
     * @param int $undistributedminor value of unconsumed Flex (from nit_flex)
     * @return array
     */
    public function platform_wallet(int $totalpaymentsminor, int $undistributedminor): array {
        $platformearnings = $this->sum('nit_earning', 'platform_amount_minor',
            'status = :st', ['st' => earning::STATUS_ACTIVE]);
        $teacherearnings = $this->sum('nit_earning', 'teacher_amount_minor',
            'status = :st', ['st' => earning::STATUS_ACTIVE]);
        $paid = $this->sum('nit_withdrawal', 'amount_minor',
            'status = :st', ['st' => withdrawal::STATUS_PAID]);

        return [
            'current_money_minor'       => $totalpaymentsminor - $paid,
            'undistributed_money_minor' => $undistributedminor,
            'teachers_money_minor'      => $teacherearnings - $paid,
            'platform_earnings_minor'   => $platformearnings,
            'total_payments_minor'      => $totalpaymentsminor,
            'total_paid_out_minor'      => $paid,
        ];
    }
}
