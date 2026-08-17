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

namespace local_nit_finance;

use local_nit_finance\api\wallet;

/**
 * Tests for the finance engine: distribution rounding, wallet aggregation, withdrawal state machine.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_finance\service\earnings_service
 * @covers     \local_nit_finance\service\wallet_service
 * @covers     \local_nit_finance\service\withdrawal_service
 */
final class wallet_test extends \advanced_testcase {
    /**
     * The 40/60 split rounds the teacher share and gives the remainder to the platform, so the two
     * always sum to the exact Flex value.
     *
     * @return void
     */
    public function test_distribution_rounds_to_exact_flex_value(): void {
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        set_config('platform_percent', 60, 'local_nit_finance');

        // 101 minor at 40% → teacher 40 (round(40.4)), platform 61; sum == 101.
        $e = wallet::distribute(10, 55, 66, 7, 101);
        $this->assertSame(40, $e['teacher_amount_minor']);
        $this->assertSame(61, $e['platform_amount_minor']);
        $this->assertSame(101, $e['teacher_amount_minor'] + $e['platform_amount_minor']);
    }

    /**
     * Distribution is idempotent per lesson.
     *
     * @return void
     */
    public function test_distribute_is_idempotent(): void {
        $this->resetAfterTest();
        $first = wallet::distribute(11, 55, 66, 7, 10000);
        $second = wallet::distribute(11, 55, 66, 7, 10000);
        $this->assertSame($first['id'], $second['id']);
    }

    /**
     * A held (pending/approved) withdrawal reduces available balance; a rejected one releases it.
     *
     * @return void
     */
    public function test_withdrawal_holds_and_releases_balance(): void {
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        set_config('platform_percent', 60, 'local_nit_finance');
        $teacherid = 55;

        wallet::distribute(20, $teacherid, 66, 7, 10000); // teacher earns 4000.
        $this->assertSame(4000, wallet::available_balance($teacherid));

        $wd = wallet::request_withdrawal($teacherid, 4000, 'bank', 'IBAN');
        $this->assertSame(0, wallet::available_balance($teacherid)); // held.

        wallet::process_withdrawal(2, $wd['id'], 'reject', ['reason' => 'bad details']);
        $this->assertSame(4000, wallet::available_balance($teacherid)); // released.
    }

    /**
     * A teacher cannot withdraw more than the available balance.
     *
     * @return void
     */
    public function test_withdrawal_cannot_exceed_balance(): void {
        $this->resetAfterTest();
        $this->expectException(\local_nit_finance\exception\finance_exception::class);
        wallet::request_withdrawal(55, 5000, 'bank', 'IBAN');
    }

    /**
     * Only an approved withdrawal can be paid; the paid amount leaves the platform.
     *
     * @return void
     */
    public function test_withdrawal_pay_requires_approval(): void {
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        set_config('platform_percent', 60, 'local_nit_finance');
        $teacherid = 55;
        wallet::distribute(30, $teacherid, 66, 7, 10000);
        $wd = wallet::request_withdrawal($teacherid, 4000, 'bank', 'IBAN');

        try {
            wallet::process_withdrawal(2, $wd['id'], 'pay', ['reference' => 'x']);
            $this->fail('paying a pending withdrawal should fail');
        } catch (\local_nit_finance\exception\finance_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        wallet::process_withdrawal(2, $wd['id'], 'approve');
        $paid = wallet::process_withdrawal(2, $wd['id'], 'pay', ['reference' => 'PAYOUT']);
        $this->assertSame('paid', $paid['status']);

        // Platform current money = payments(0 here) − paid(4000) = -4000 in isolation; check paid-out figure.
        $pw = wallet::platform(0, 0);
        $this->assertSame(4000, $pw['total_paid_out_minor']);
    }
}
