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

/**
 * Report filters read from the request.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\filters
 */
final class filters_test extends \advanced_testcase {

    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    public function test_to_date_includes_the_whole_day(): void {
        $this->resetAfterTest();
        $this->setTimezone('Africa/Cairo');
        $_GET = ['from' => '2026-10-01', 'to' => '2026-10-31'];
        $f = filters::from_request();

        $this->assertSame(make_timestamp(2026, 10, 1), $f->from);
        $this->assertSame(make_timestamp(2026, 11, 1) - 1, $f->to);

        [$sql, $params] = $f->period_sql('t.timecreated', 'x');
        $this->assertSame('t.timecreated >= :xfrom AND t.timecreated <= :xto', $sql);
        $this->assertSame(['xfrom' => $f->from, 'xto' => $f->to], $params);
    }

    public function test_bad_dates_are_ignored(): void {
        $_GET = ['from' => '2026-02-30', 'to' => 'yesterday'];
        $f = filters::from_request();

        $this->assertSame(0, $f->from);
        $this->assertSame(0, $f->to);
        $this->assertSame(['1 = 1', []], $f->period_sql('t.timecreated'));
    }

    public function test_params_keep_only_set_filters(): void {
        $_GET = ['courseid' => '5', 'q' => '  ali ', 'status' => ''];
        $f = filters::from_request();

        $this->assertSame(['courseid' => 5, 'q' => 'ali'], $f->params());
    }
}
