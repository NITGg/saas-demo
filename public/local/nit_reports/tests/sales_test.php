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
            ['itemtype' => 'course', 'itemid' => (int) $course->id]);
        // Codes: one opens a course (a sale), one only tops up the wallet (not a sale).
        $this->used_code('course', 4000, (int) $student->id);
        $this->used_code('wallet', 2000, (int) $student->id);

        $cards = $this->cards();
        $this->assertSame('1', $cards['card_paidcount']);
        $this->assertSame(format_float(100, 2) . ' EGP', $cards['card_revenue']);
        $this->assertSame(data::minor(3000), $cards['card_walletsales']);
        $this->assertSame(data::minor(4000), $cards['card_codesales']);
        $this->assertStringContainsString(format_float(50, 2), $cards['card_topups']);

        // The grouped "by item" view and the chart hold sales only.
        $_GET = ['view' => 'item'];
        $report = new report\sales(filters::from_request(), scope::for_user());
        $_GET = [];
        $types = array_column($report->rows(0, 0)['rows'], 'type');
        $this->assertNotContains(get_string('item_wallet_topup', 'local_nit_reports'), $types);
        $this->assertSame([100.0], array_values($report->chart()->get_series()[0]->get_values()));
    }
}
