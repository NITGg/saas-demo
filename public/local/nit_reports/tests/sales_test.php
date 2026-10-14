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

namespace local_nit_reports;

use local_nit_finance\local\wallets;

/**
 * Sales report: a wallet top-up is not a sale, a purchase paid from the wallet
 * is (BUG-064).
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\report\sales
 */
final class sales_test extends \advanced_testcase {

    /**
     * A gateway payment as local_payments writes it.
     *
     * @param int $userid
     * @param int $courseid
     * @param float $amount
     * @param array $meta
     */
    private function payment(int $userid, int $courseid, float $amount, array $meta): void {
        global $DB;
        static $n = 0;
        $n++;
        $DB->insert_record('local_payments_transactions', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'provider_id' => 0, 'order_id' => 'S' . $n,
            'idempotency_key' => 'sk' . $n, 'amount' => $amount, 'currency' => 'EGP', 'status' => 'completed',
            'country' => 'EG', 'metadata' => json_encode($meta), 'expires_at' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * An access code a student used.
     *
     * @param string $itemtype cm | course | wallet
     * @param int $minor
     * @param int $userid
     */
    private function used_code(string $itemtype, int $minor, int $userid): void {
        global $DB;
        $DB->insert_record('nit_access_code', (object) ['code' => 'C' . random_int(10000, 99999) . $itemtype,
            'itemtype' => $itemtype, 'itemid' => 1, 'courseid' => 0, 'amount_minor' => $minor, 'status' => 'used',
            'usedby' => $userid, 'timeused' => time(), 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * The sales report's cards by id.
     *
     * @param string $view
     * @return array<string, string> card id => value
     */
    private function cards(string $view = 'list'): array {
        $_GET = ['view' => $view];
        $report = new report\sales(filters::from_request(), scope::for_user());
        $_GET = [];
        return array_column($report->summary(), 'value', 'id');
    }

    public function test_top_ups_are_not_revenue_and_wallet_purchases_are(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('nit_wallet_txn') || !$DB->get_manager()->table_exists('local_payments_transactions')) {
            $this->markTestSkipped('local_nit_finance / local_payments are not installed.');
        }
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // A course bought by card: 100 EGP.
        $this->payment((int) $student->id, (int) $course->id, 100, ['item_type' => 'course']);
        // A wallet top-up by card: 50 EGP — the student's own balance, not a sale.
        $this->payment((int) $student->id, 0, 50, ['item_type' => 'wallet_topup', 'item_id' => (int) $student->id]);
        wallets::move(wallets::STUDENT, (int) $student->id, 5000, wallets::KIND_TOPUP);
        // 30 EGP of that balance spent on a course — this is the sale.
        wallets::move(wallets::STUDENT, (int) $student->id, -3000, wallets::KIND_PURCHASE,
            ['itemtype' => 'course', 'itemid' => (int) $course->id, 'purchaseid' => 1]);
        // 15 EGP of it spent on a package, then 5 EGP of that refunded back to the wallet.
        wallets::move(wallets::STUDENT, (int) $student->id, -1500, wallets::KIND_PURCHASE,
            ['itemtype' => 'package', 'itemid' => 9, 'purchaseid' => 2, 'note' => 'Package A']);
        wallets::move(wallets::STUDENT, (int) $student->id, 500, wallets::KIND_ADJUSTMENT,
            ['itemtype' => 'package', 'itemid' => 9, 'purchaseid' => 2, 'note' => 'Refund']);
        // An admin's manual adjustment (no purchase) is not a refund of a sale.
        wallets::move(wallets::STUDENT, (int) $student->id, 700, wallets::KIND_ADJUSTMENT, ['note' => 'Gift']);
        // Codes: one opens a course (a sale), one only tops up the wallet (not a sale).
        $this->used_code('course', 4000, (int) $student->id);
        $this->used_code('wallet', 2000, (int) $student->id);

        // Revenue = what was sold: 100 by card + 30 + 15 − 5 from the wallet = 140.
        $cards = $this->cards();
        $this->assertSame('1', $cards['card_paidcount']);
        $this->assertSame(format_float(140, 2) . ' EGP', $cards['card_revenue']);
        $this->assertSame(data::minor(4000), $cards['card_walletsales']);
        $this->assertSame(data::minor(4000), $cards['card_codesales']);
        $this->assertStringContainsString(format_float(50, 2), $cards['card_topups']);
        // Collected online = 100 sold by card + 50 top-up.
        $this->assertSame(format_float(150, 2) . ' EGP', $cards['card_collected']);

        // The chart and the grouped views follow the revenue.
        $report = $this->report('item');
        $this->assertSame([140.0], array_values($report->chart()->get_series()[0]->get_values()));
        $rows = $report->rows(0, 0)['rows'];
        $types = array_column($rows, 'type');
        $this->assertNotContains(get_string('item_wallet_topup', 'local_nit_reports'), $types);
        $package = array_values(array_filter($rows, fn($r) => $r['item'] === 'Package A'))[0];
        $this->assertSame(format_float(10, 2) . ' EGP', $package['revenue']);
        $this->assertSame('1', $package['refunded']);

        $methods = array_column($this->report('provider')->rows(0, 0)['rows'], 'revenue', 'provider');
        $this->assertSame(format_float(40, 2) . ' EGP', $methods[get_string('paymethod_wallet', 'local_nit_reports')]);
        $this->assertSame([format_float(140, 2) . ' EGP'], array_column($this->report('month')->rows(0, 0)['rows'], 'revenue'));
    }

    public function test_refunds_come_off_the_revenue_and_the_fee_stays(): void {
        global $DB;
        $this->resetAfterTest();
        if (!class_exists(\local_nit_finance\local\refunds::class)) {
            $this->markTestSkipped('local_nit_finance refunds are not installed.');
        }
        $this->setAdminUser();
        set_config('refund_days', 3, 'local_nit_finance');
        set_config('refund_fee_percent', 10, 'local_nit_finance');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_and_enrol($course, 'editingteacher');
        $DB->insert_record('local_payments_course_prices', (object) ['courseid' => $course->id, 'country' => '*',
            'currency' => 'EGP', 'price' => 200, 'is_default' => 1, 'is_active' => 1, 'priority' => 0,
            'created_by' => 2, 'timecreated' => time(), 'timemodified' => time()]);
        $refunds = \local_nit_finance\local\refunds::class;

        // Bought from the wallet for 200, refunded: 180 back, the 20 fee is revenue.
        $buyer = (int) $gen->create_user()->id;
        wallets::move(wallets::STUDENT, $buyer, 20000, wallets::KIND_TOPUP);
        \local_nit_finance\local\wallet_checkout::buy_course($buyer, (int) $course->id);
        $refunds::process((int) get_admin()->id,
            (int) $refunds::request($buyer, $refunds::refundable($buyer)[0]['key'])->id, 'approve');

        // Bought online for 150, refunded to the wallet: 135 back, the 15 fee is revenue.
        $payer = (int) $gen->create_user()->id;
        $this->payment($payer, (int) $course->id, 150, ['item_type' => 'course']);
        \local_payments\enrollment_handler::enrol_user($payer, (int) $course->id);
        $refunds::process((int) get_admin()->id,
            (int) $refunds::request($payer, $refunds::refundable($payer)[0]['key'])->id, 'approve');

        $cards = $this->cards();
        $this->assertSame(format_float(35, 2) . ' EGP', $cards['card_revenue']);
        $this->assertSame(data::minor(2000), $cards['card_walletsales']);
        $this->assertSame('2 — ' . format_float(315, 2) . ' EGP', $cards['card_refunded']);
        $this->assertSame([35.0], array_values($this->report('item')->chart()->get_series()[0]->get_values()));
        $this->assertSame([format_float(35, 2) . ' EGP'], array_column($this->report('month')->rows(0, 0)['rows'], 'revenue'));
    }

    /**
     * The sales report in one view.
     *
     * @param string $view
     * @return report\sales
     */
    private function report(string $view): report\sales {
        $_GET = ['view' => $view];
        $report = new report\sales(filters::from_request(), scope::for_user());
        $_GET = [];
        return $report;
    }
}
