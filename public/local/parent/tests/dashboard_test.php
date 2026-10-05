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
 * Unit tests for {@see \local_parent\dashboard} — the phone gate and the report
 * behind the public parent dashboard (local/parent/index.php).
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_parent\dashboard
 */
final class dashboard_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/profile/lib.php');
        set_config('countrycode', '20', 'local_parent');
        foreach (['parentphone', 'fatherphone', 'motherphone'] as $shortname) {
            if (!$DB->record_exists('user_info_field', ['shortname' => $shortname])) {
                $this->getDataGenerator()->create_custom_profile_field(
                    ['shortname' => $shortname, 'name' => $shortname, 'datatype' => 'text']);
            }
        }
    }

    /**
     * A student with their own phone and guardian phones.
     *
     * @param string $phone
     * @param array $guardians shortname => phone
     * @param array $record extra user fields
     * @return \stdClass
     */
    private function student(string $phone, array $guardians, array $record = []): \stdClass {
        $user = $this->getDataGenerator()->create_user(['phone1' => $phone] + $record);
        profile_save_custom_fields($user->id, $guardians);
        return $user;
    }

    public function test_normalize_phone_matches_local_and_international_forms(): void {
        foreach (['01012345678', '+201012345678', '00201012345678', '2010 1234 5678', '(010) 1234-5678'] as $raw) {
            $this->assertSame('201012345678', dashboard::normalize_phone($raw), $raw);
        }
        $this->assertSame('', dashboard::normalize_phone('abc'));
        $this->assertSame('', dashboard::normalize_phone('000'));
    }

    public function test_valid_phone_rejects_letters_and_short_numbers(): void {
        $this->assertTrue(dashboard::valid_phone('01012345678'));
        $this->assertTrue(dashboard::valid_phone('+20 101 234 5678'));
        $this->assertFalse(dashboard::valid_phone('0101234567a'));
        $this->assertFalse(dashboard::valid_phone('12345'));
        $this->assertFalse(dashboard::valid_phone(''));
    }

    public function test_find_student_needs_both_phones_to_match(): void {
        $student = $this->student('01000000099', ['parentphone' => '01100000088']);

        $this->assertSame((int) $student->id, dashboard::find_student('01000000099', '01100000088'));
        $this->assertSame((int) $student->id, dashboard::find_student('+20 100 000 0099', '0020-110-000-0088'),
            'formatting does not matter');
        $this->assertNull(dashboard::find_student('01000000099', '01155555555'), 'wrong parent phone');
        $this->assertNull(dashboard::find_student('01000000098', '01100000088'), 'wrong student phone');
        $this->assertNull(dashboard::find_student('01100000088', '01000000099'), 'phones swapped');
        $this->assertNull(dashboard::find_student('', '01100000088'), 'empty student phone');
        $this->assertNull(dashboard::find_student('0100000009x', '01100000088'), 'letters');
    }

    public function test_find_student_accepts_father_and_mother_phones(): void {
        $student = $this->student('010 0000 0077', ['fatherphone' => '01200000011', 'motherphone' => '01500000022']);

        $this->assertSame((int) $student->id, dashboard::find_student('01000000077', '01200000011'));
        $this->assertSame((int) $student->id, dashboard::find_student('01000000077', '01500000022'));
    }

    public function test_find_student_does_not_return_a_sibling_or_an_inactive_account(): void {
        // Two siblings share a parent phone: each one only with their own phone.
        $first = $this->student('01000000001', ['parentphone' => '01100000000']);
        $second = $this->student('01000000002', ['parentphone' => '01100000000']);
        $this->assertSame((int) $first->id, dashboard::find_student('01000000001', '01100000000'));
        $this->assertSame((int) $second->id, dashboard::find_student('01000000002', '01100000000'));

        $this->student('01000000003', ['parentphone' => '01100000003'], ['suspended' => 1]);
        $this->assertNull(dashboard::find_student('01000000003', '01100000003'), 'suspended');

        $deleted = $this->student('01000000004', ['parentphone' => '01100000004']);
        delete_user($deleted);
        $this->assertNull(dashboard::find_student('01000000004', '01100000004'), 'deleted');
    }

    public function test_wrong_pairs_block_the_client_after_the_limit(): void {
        $ip = '10.1.2.3';
        for ($i = 0; $i < dashboard::MAX_FAILURES - 1; $i++) {
            dashboard::record_failure($ip);
        }
        $this->assertFalse(dashboard::is_blocked($ip));
        dashboard::record_failure($ip);
        $this->assertTrue(dashboard::is_blocked($ip));
        $this->assertFalse(dashboard::is_blocked('10.1.2.4'), 'another client is not blocked');
    }

    public function test_report_lists_lectures_with_exam_and_homework_states(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 2]);
        $student = $this->student('01000000055', ['parentphone' => '01100000055']);
        $gen->enrol_user($student->id, $course->id, 'student');

        $graded = $gen->create_module('quiz', ['course' => $course->id, 'section' => 1, 'grade' => 10]);
        $missed = $gen->create_module('quiz', ['course' => $course->id, 'section' => 1]);
        $handedin = $gen->create_module('assign', ['course' => $course->id, 'section' => 1]);
        $gen->create_module('quiz', ['course' => $course->id, 'section' => 1, 'visible' => 0]);
        $gen->create_module('page', ['course' => $course->id, 'section' => 0]);

        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $graded->id, 'courseid' => $course->id]);
        $item->update_final_grade($student->id, 7);
        $DB->insert_record('assign_submission', (object) ['assignment' => $handedin->id, 'userid' => $student->id,
            'status' => 'submitted', 'latest' => 1, 'attemptnumber' => 0, 'groupid' => 0,
            'timecreated' => time(), 'timemodified' => time()]);

        $report = dashboard::report((int) $student->id);
        $this->assertCount(1, $report);
        $this->assertSame((int) $course->id, $report[0]['id']);

        // Section 0 holds only a page, so it is left out; sections 1 and 2 remain.
        $sections = $report[0]['sections'];
        $this->assertCount(2, $sections);
        [$first, $second] = $sections;

        $this->assertCount(2, $first['exams'], 'the hidden quiz is not reported');
        $this->assertSame('done', $first['exams'][0]['state']);
        $this->assertEquals(70.0, $first['exams'][0]['percent']);
        $this->assertSame('absent', $first['exams'][1]['state']);
        $this->assertSame($missed->name, $first['exams'][1]['name']);
        $this->assertCount(1, $first['homework']);
        $this->assertSame('pending', $first['homework'][0]['state']);
        $this->assertSame([], $first['videos']);
        $this->assertGreaterThan(0, $first['started']);

        $this->assertSame([], $second['exams'] + $second['homework'] + $second['videos'], 'an empty lecture');
        $this->assertSame(0, $second['started']);
    }

    public function test_report_is_empty_without_active_enrolments(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $this->student('01000000066', ['parentphone' => '01100000066']);
        $gen->enrol_user($student->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->assertSame([], dashboard::report((int) $student->id));
    }
}
