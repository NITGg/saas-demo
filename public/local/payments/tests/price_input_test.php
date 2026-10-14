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

namespace local_payments;

use local_payments\local\hooks\course_form;
use local_payments\local\price_input;

/**
 * A typed course price is refused when malformed, never silently changed (BUG-035).
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_payments\local\price_input
 * @covers     \local_payments\local\hooks\course_form
 * @covers     \local_nit_core\helper\price
 */
final class price_input_test extends \advanced_testcase {

    public function test_typed_prices(): void {
        foreach (['150' => 150.0, '149.5' => 149.5, '149.50' => 149.5, ' 99 ' => 99.0, '١٥٠' => 150.0,
                '49,50' => 49.5, '١٤٩٫٥' => 149.5, '99999999.99' => 99999999.99] as $typed => $value) {
            $this->assertSame('', price_input::problem($typed), (string) $typed);
            $this->assertSame($value, price_input::value($typed), (string) $typed);
        }
        foreach (['10.999' => 'format', '12abc' => 'format', '1,500' => 'format', '1e6' => 'format',
                '-5' => 'format', 'abc' => 'format', '' => 'format', '10.5.3' => 'format', '0x10' => 'format',
                '0' => 'positive', '0.00' => 'positive', '100000000' => 'max'] as $typed => $problem) {
            $this->assertSame($problem, price_input::problem($typed), "'$typed'");
        }
    }

    public function test_course_settings_form_refuses_malformed_prices(): void {
        $errors = course_form::price_errors(['lp_paid' => 1, 'lp_price_home' => '10.999', 'lp_price_other' => '12abc']);
        $this->assertSame(['lp_price_home', 'lp_price_other'], array_keys($errors));
        $this->assertSame(get_string('error_price_format', 'local_payments'), $errors['lp_price_home']);

        $this->assertSame([], course_form::price_errors(['lp_paid' => 1, 'lp_price_home' => '150',
            'lp_price_other' => '9.99']));
        $this->assertSame(['lp_price_home'], array_keys(course_form::price_errors(['lp_paid' => 1,
            'lp_price_home' => '0', 'lp_price_other' => '10'])));
        // A free course has no prices to check.
        $this->assertSame([], course_form::price_errors(['lp_paid' => 0, 'lp_price_home' => 'abc']));
    }
}
