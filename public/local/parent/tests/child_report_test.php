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

namespace local_parent;

/**
 * {@see dashboard::child_report()} — the mobile API's parent lookup: same gate
 * order and throttle as the web page.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_parent\dashboard::child_report
 */
final class child_report_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/profile/lib.php');
        set_config('countrycode', '20', 'local_parent');
        if (!$DB->record_exists('user_info_field', ['shortname' => 'parentphone'])) {
            $this->getDataGenerator()->create_custom_profile_field(
                ['shortname' => 'parentphone', 'name' => 'parentphone', 'datatype' => 'text']);
        }
    }

    /**
     * Expect a moodle_exception with the given errorcode.
     *
     * @param string $code
     * @param callable $fn
     */
    private function assert_error(string $code, callable $fn): void {
        try {
            $fn();
            $this->fail("expected $code");
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    public function test_matching_pair_returns_student_and_courses(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Physics']);
        $student = $gen->create_user(['phone1' => '01000000099', 'firstname' => 'Omar', 'lastname' => 'Ali']);
        profile_save_custom_fields($student->id, ['parentphone' => '01100000088']);
        $gen->enrol_user($student->id, $course->id, 'student');

        $out = dashboard::child_report(' 01000000099 ', '+201100000088', '10.0.0.1');
        $this->assertSame(['id' => (int) $student->id, 'fullname' => 'Omar Ali'], $out['student']);
        $this->assertCount(1, $out['courses']);
        $this->assertSame('Physics', $out['courses'][0]['name']);
    }

    public function test_invalid_phone_is_not_counted_and_wrong_pair_is(): void {
        $ip = '10.0.0.2';
        for ($i = 0; $i < dashboard::MAX_FAILURES; $i++) {
            $this->assert_error('err_invalidphone', fn() => dashboard::child_report('abc', '01100000088', $ip));
        }
        $this->assertFalse(dashboard::is_blocked($ip), 'format errors are not throttled');

        for ($i = 0; $i < dashboard::MAX_FAILURES; $i++) {
            $this->assert_error('err_studentnotfound',
                fn() => dashboard::child_report('01000000099', '01155555555', $ip));
        }
        $this->assertTrue(dashboard::is_blocked($ip));
        // Once blocked even a valid pair is refused.
        $this->assert_error('err_toomanyattempts', fn() => dashboard::child_report('01000000099', '01100000088', $ip));
        // Another client is unaffected.
        $this->assert_error('err_studentnotfound',
            fn() => dashboard::child_report('01000000099', '01155555555', '10.0.0.3'));
    }
}
