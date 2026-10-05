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

use local_nit_finance\exception\finance_exception;
use local_nit_finance\local\catalog;
use local_nit_finance\local\codes;
use local_nit_finance\local\mobile_api;
use local_nit_finance\local\wallets;

/**
 * The mobile app finance service (api.php logic).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_finance\local\mobile_api
 * @covers     \local_nit_finance\local\codes::search
 * @covers     \local_nit_finance\local\wallets::history
 * @covers     \local_nit_finance\local\wallets::history_count
 */
final class mobile_api_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass lesson sold at 50 EGP */
    private $sold;
    /** @var \stdClass lesson without a price */
    private $free;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->sold = $gen->create_module('page', ['course' => $this->course->id]);
        $this->free = $gen->create_module('page', ['course' => $this->course->id]);
        catalog::set_price((int) $this->sold->cmid, (int) $this->course->id, 5000);
    }

    /**
     * Give the student some wallet credit.
     *
     * @param int $minor
     */
    private function credit(int $minor): void {
        wallets::move(wallets::STUDENT, (int) $this->student->id, $minor, wallets::KIND_TOPUP, ['note' => 'test']);
    }

    /**
     * Assert a call fails with a finance error code.
     *
     * @param string $code err_ string id
     * @param callable $fn
     */
    private function assert_fails(string $code, callable $fn): void {
        try {
            $fn();
            $this->fail('Expected ' . $code);
        } catch (finance_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    public function test_wallet_and_paged_history(): void {
        $userid = (int) $this->student->id;
        $this->credit(10000);
        $this->credit(2550);
        $this->credit(100);

        $wallet = mobile_api::get_wallet($userid);
        $this->assertSame(12650, $wallet['balance_minor']);
        $this->assertSame(126.5, $wallet['balance']);
        $this->assertSame('EGP', $wallet['currency']);
        $this->assertFalse($wallet['is_teacher']);

        $page0 = mobile_api::get_wallet_history($userid, 0, 2);
        $page1 = mobile_api::get_wallet_history($userid, 1, 2);
        $this->assertSame(3, $page0['total']);
        $this->assertCount(2, $page0['lines']);
        $this->assertCount(1, $page1['lines']);
        $this->assertSame(10000, $page1['lines'][0]['amount_minor']);
        // Default history() behaviour is unchanged (no offset).
        $this->assertCount(3, wallets::history(wallets::STUDENT, $userid));
        $this->assertSame(3, wallets::history_count(wallets::STUDENT, $userid));
    }

    public function test_buy_lesson_and_access(): void {
        $userid = (int) $this->student->id;
        $cmid = (int) $this->sold->cmid;

        $before = mobile_api::get_lesson_access($userid, $cmid);
        $this->assertFalse($before['open']);
        $this->assertFalse($before['can_afford']);
        $this->assertSame(5000, $before['shortfall_minor']);
        $this->assert_fails('err_insufficientwallet', fn() => mobile_api::buy_lesson($userid, $cmid));

        $this->credit(6000);
        $this->assertTrue(mobile_api::get_lesson_access($userid, $cmid)['can_afford']);
        $bought = mobile_api::buy_lesson($userid, $cmid);
        $this->assertSame(5000, $bought['amount_minor']);
        $this->assertSame(1000, $bought['balance_minor']);
        $this->assertTrue(mobile_api::get_lesson_access($userid, $cmid)['open']);
        $this->assert_fails('err_alreadyowned', fn() => mobile_api::buy_lesson($userid, $cmid));
        $this->assert_fails('err_notforsale', fn() => mobile_api::buy_lesson($userid, (int) $this->free->cmid));
        $this->assert_fails('err_itemnotfound', fn() => mobile_api::buy_lesson($userid, 999999));

        $prices = mobile_api::get_course_lesson_prices($userid, (int) $this->course->id);
        $this->assertCount(1, $prices['lessons']);
        $this->assertTrue($prices['lessons'][0]['owned']);
        $this->assertSame(1, count(mobile_api::get_my_purchases($userid)['purchases']));
        $this->assert_fails('err_coursenotfound', fn() => mobile_api::get_course_lesson_prices($userid, SITEID));
    }

    public function test_codes_generate_list_redeem_disable(): void {
        $admin = get_admin();
        $userid = (int) $this->student->id;

        $made = mobile_api::generate_codes((int) $admin->id, catalog::WALLET, 0, 2, '20.25', '', 'note');
        $this->assertSame(2, $made['count']);
        $this->assertSame(2025, $made['amount_minor']);
        // A lesson code without an amount costs the lesson's price.
        $lesson = mobile_api::generate_codes((int) $admin->id, catalog::CM, (int) $this->sold->cmid, 1, '', '', '');
        $this->assertSame(5000, $lesson['amount_minor']);

        $this->assert_fails('err_chooseitem', fn() => mobile_api::generate_codes((int) $admin->id, 'cm', 0, 1, '', '', ''));
        $this->assert_fails('err_badexpiry', fn() => mobile_api::generate_codes((int) $admin->id, 'wallet', 0, 1, '5',
            '2001-01-01', ''));

        $listed = mobile_api::list_codes(['batch' => $made['batch']], 0, 50);
        $this->assertSame(2, $listed['total']);
        $this->assertSame(1, codes::search(['q' => strtolower(substr($lesson['codes'][0]['code'], 0, 4))])['total']);

        $redeemed = mobile_api::redeem_code($userid, $made['codes'][0]['code']);
        $this->assertSame('wallet', $redeemed['type']);
        $this->assertSame(2025, $redeemed['balance_minor']);
        $this->assert_fails('err_codeused', fn() => mobile_api::redeem_code($userid, $made['codes'][0]['code']));
        $this->assert_fails('err_codeinvalid', fn() => mobile_api::redeem_code($userid, 'nope'));

        $id = $made['codes'][1]['id'];
        $this->assertSame('disabled', mobile_api::disable_code($id)['code']['status']);
        $this->assert_fails('err_codenotactive', fn() => mobile_api::disable_code($id));
        $this->assert_fails('err_codenotfound', fn() => mobile_api::disable_code(999999));

        $item = mobile_api::redeem_code($userid, $lesson['codes'][0]['code']);
        $this->assertSame((int) $this->sold->cmid, $item['item']['cmid']);
        $this->assertSame(1, mobile_api::list_codes(['status' => 'used', 'q' => $lesson['codes'][0]['code']], 0, 10)['total']);
    }

    public function test_adjust_wallet_signed(): void {
        $userid = (int) $this->student->id;
        $this->setAdminUser();
        $this->assertSame(5000, mobile_api::adjust_wallet($userid, '50', 'gift')['balance_minor']);
        $taken = mobile_api::adjust_wallet($userid, '-20.5', 'fix');
        $this->assertSame(2950, $taken['balance_minor']);
        $this->assertSame(wallets::KIND_ADJUSTMENT, $taken['line']['kind']);
        $this->assert_fails('err_insufficientwallet', fn() => mobile_api::adjust_wallet($userid, '-100', ''));
        $this->assert_fails('err_amountnonzero', fn() => mobile_api::adjust_wallet($userid, '0', ''));
        $this->assert_fails('err_usernotfound', fn() => mobile_api::adjust_wallet(999999, '5', ''));
    }

    public function test_teacher_wallet_and_withdrawals(): void {
        $teacherid = (int) $this->teacher->id;
        $this->assert_fails('err_notateacher', fn() => mobile_api::get_teacher_wallet((int) $this->student->id));

        // A sale earns the teacher 40% of 50 EGP.
        $this->credit(5000);
        mobile_api::buy_lesson((int) $this->student->id, (int) $this->sold->cmid);
        $wallet = mobile_api::get_teacher_wallet($teacherid);
        $this->assertSame(2000, $wallet['available_balance_minor']);
        $this->assertSame(2000, $wallet['wallet_balance_minor']);
        $earnings = mobile_api::get_my_earnings($teacherid, 0, 10);
        $this->assertSame(1, $earnings['total']);
        $this->assertSame(fullname($this->student), $earnings['earnings'][0]['student_name']);

        $this->assert_fails('err_badmethod', fn() => mobile_api::request_withdrawal($teacherid, '5', 'crypto', ''));
        $this->assert_fails('err_insufficientbalance', fn() => mobile_api::request_withdrawal($teacherid, '30', 'bank', ''));
        $req = mobile_api::request_withdrawal($teacherid, '15', 'wallet', '0100');
        $this->assertSame(500, $req['available_balance_minor']);
        $this->assertCount(1, mobile_api::get_my_withdrawals($teacherid)['withdrawals']);

        $admin = get_admin();
        $this->assertSame(1, mobile_api::list_withdrawals('pending', 0, 10)['total']);
        $this->assert_fails('err_badstatus', fn() => mobile_api::list_withdrawals('nope', 0, 10));
        $this->assert_fails('err_reasonrequired',
            fn() => mobile_api::process_withdrawal((int) $admin->id, $req['withdrawal']['id'], 'reject'));
        $done = mobile_api::process_withdrawal((int) $admin->id, $req['withdrawal']['id'], 'reject', 'test');
        $this->assertSame('rejected', $done['withdrawal']['status']);
        $this->assertSame(2000, mobile_api::get_teacher_wallet($teacherid)['available_balance_minor']);
    }

    public function test_admin_lists_and_summary(): void {
        $this->credit(1234);
        $summary = mobile_api::get_finance_summary();
        $this->assertSame(1234, $summary['wallets']['student']['total_minor']);
        $list = mobile_api::list_wallets(wallets::STUDENT, '', 0, 10);
        $this->assertSame(1, $list['total']);
        $this->assertSame(0, mobile_api::list_wallets(wallets::STUDENT, 'no-such-person', 0, 10)['total']);
        $this->assert_fails('err_badwallettype', fn() => mobile_api::list_wallets('platform', '', 0, 10));
        $ledger = mobile_api::get_wallet_ledger(wallets::STUDENT, (int) $this->student->id, 0, 10);
        $this->assertSame(1, $ledger['total']);
        $this->assert_fails('err_usernotfound', fn() => mobile_api::get_wallet_ledger(wallets::STUDENT, 999999, 0, 10));
    }

    public function test_set_lesson_price_needs_capability(): void {
        $cmid = (int) $this->free->cmid;
        $this->setUser($this->teacher);
        $this->expectException(\required_capability_exception::class);
        try {
            mobile_api::set_lesson_price((int) $this->teacher->id, $cmid, '10');
        } finally {
            $this->setAdminUser();
            $admin = (int) get_admin()->id;
            $this->assertSame(1050, mobile_api::set_lesson_price($admin, $cmid, '10.5')['price_minor']);
            $this->assertSame(0, mobile_api::set_lesson_price($admin, $cmid, '0')['price_minor']);
            $this->assertSame(0, catalog::price($cmid));
        }
    }

    public function test_parse_expiry(): void {
        $this->assertSame(0, mobile_api::parse_expiry(''));
        $this->assertSame(0, mobile_api::parse_expiry('0'));
        $future = time() + DAYSECS;
        $this->assertSame($future, mobile_api::parse_expiry((string) $future));
        $this->assertGreaterThan(time(), mobile_api::parse_expiry(date('Y-m-d', time() + 3 * DAYSECS)));
        $this->assert_fails('err_badexpiry', fn() => mobile_api::parse_expiry('tomorrow'));
    }
}
