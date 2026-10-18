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

namespace local_nit_analytics;

/**
 * The acquisition funnel report: landings → sign-ups → buyers → purchases / revenue per source.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_analytics\funnel
 */
final class funnel_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A user who signed up from $utm.
     *
     * @param array $utm
     * @return int user id
     */
    private function signup(array $utm): int {
        $uid = (int) $this->getDataGenerator()->create_user()->id;
        $session = new \stdClass();
        $session->local_nit_analytics_utm = $utm;
        tracker::record_signup($uid, $session);
        return $uid;
    }

    /**
     * Facebook: 4 visits, 2 sign-ups, 1 buyer with 2 purchases in two currencies. Old data and
     * unattributed users stay out of the Facebook row.
     *
     * @return void
     */
    public function test_rows_follow_the_funnel(): void {
        global $DB;
        $fb = ['utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => 'oct'];
        foreach ([1, 2, 3, 4] as $unused) {
            funnel::log_landing($fb, time());
        }
        funnel::log_landing($fb, time() - 100 * DAYSECS); // Outside the period.
        $buyer = $this->signup($fb);
        $this->signup($fb);
        conversions::record_purchase($buyer, 'flex', 100000, 'EGP', 'a', false);
        conversions::record_purchase($buyer, 'course', 35000, 'SAR', 'b', false);

        $direct = (int) $this->getDataGenerator()->create_user()->id;
        conversions::record_purchase($direct, 'flex', 5000, 'EGP', 'c', false);

        $old = $this->signup(['utm_source' => 'google']);
        $DB->set_field('user', 'timecreated', time() - 100 * DAYSECS, ['id' => $old]);

        $rows = funnel::rows(time() - 30 * DAYSECS);
        $bykey = [];
        foreach ($rows as $r) {
            $bykey[$r['source']] = $r;
        }

        $f = $bykey['facebook'];
        $this->assertSame(['cpc', 'oct'], [$f['medium'], $f['campaign']]);
        $this->assertSame(4, $f['landings'], 'visits outside the period are left out');
        $this->assertSame(2, $f['signups']);
        $this->assertSame(1, $f['buyers'], 'one buyer, even paying in two currencies');
        $this->assertSame(2, $f['purchases']);
        $this->assertSame(['EGP' => 100000, 'SAR' => 35000], $f['revenue']);

        $this->assertArrayNotHasKey('google', $bykey, 'sign-ups older than the period are left out');
        $this->assertSame(1, $bykey['']['buyers'], 'unattributed buyers are grouped as direct');
        $this->assertSame('facebook', $rows[0]['source'], 'most sign-ups first');
    }

    /**
     * Export rows carry rates, formatted revenue and the "direct" label — what the CSV contains.
     *
     * @return void
     */
    public function test_export_rows_and_rates(): void {
        $rows = funnel::export_rows([[
            'source' => '', 'medium' => '', 'campaign' => '', 'landings' => 8, 'signups' => 2, 'buyers' => 1,
            'purchases' => 1, 'revenue' => ['SAR' => 35000, 'EGP' => 125000],
        ]], '(direct)');
        $this->assertSame('(direct)', $rows[0]['source']);
        $this->assertSame('25%', $rows[0]['signuprate']);
        $this->assertSame('50%', $rows[0]['buyerrate']);
        $this->assertSame('1,250.00 EGP; 350.00 SAR', $rows[0]['revenue']);
        $this->assertSame('', funnel::rate(3, 0), 'no rate without a base');
    }

    /**
     * The CSV export writes a header and one line per row.
     *
     * @return void
     */
    public function test_csv_export(): void {
        $this->signup(['utm_source' => 'facebook']);
        $rows = funnel::export_rows(funnel::rows(time() - DAYSECS), '(direct)');
        $columns = array_combine(array_keys($rows[0]), array_keys($rows[0]));
        $file = \core\dataformat::write_data('funnel', 'csv', $columns, $rows);
        $csv = file_get_contents($file);
        $this->assertStringContainsString('source,medium,campaign,landings,signups', $csv);
        $this->assertStringContainsString('facebook', $csv);
    }
}
