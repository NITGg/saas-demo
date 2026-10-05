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

namespace mod_jobform;

use core_external\external_api;

/**
 * Tests for the teacher submission functions (manager + web services).
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_jobform\submission_manager::list_submissions
 * @covers     \mod_jobform\submission_manager::get_answer_rows
 * @covers     \mod_jobform_external
 */
final class teacher_submissions_test extends \advanced_testcase {

    /** @var \stdClass */
    protected $course;
    /** @var \stdClass the jobform module record (has ->cmid) */
    protected $jobform;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student;
    /** @var int[] field ids: text, date, checkbox */
    protected $fields;

    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/jobform/classes/external.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->jobform = $gen->create_module('jobform', ['course' => $this->course->id]);
        $this->teacher = $gen->create_user();
        $this->student = $gen->create_user();
        $gen->enrol_user($this->teacher->id, $this->course->id, 'teacher');
        $gen->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setAdminUser();
        $this->fields = [
            instance_manager::save_field($this->jobform->id, (object) ['name' => 'Full name', 'type' => 'text']),
            instance_manager::save_field($this->jobform->id, (object) ['name' => 'Birth date', 'type' => 'date']),
            instance_manager::save_field($this->jobform->id, (object) ['name' => 'Has car', 'type' => 'checkbox']),
        ];
    }

    public function test_list_submissions_filters_status_and_pages(): void {
        $other = $this->getDataGenerator()->create_user();
        submission_manager::save($this->jobform->id, $this->student->id, [], submission_manager::STATUS_SUBMITTED);
        submission_manager::save($this->jobform->id, $other->id, [], submission_manager::STATUS_DRAFT);

        $r = submission_manager::list_submissions($this->jobform->id);
        $this->assertSame(1, $r['total']);
        $this->assertEquals($this->student->id, $r['rows'][0]->userid);

        $r = submission_manager::list_submissions($this->jobform->id, 'all', 1, 1);
        $this->assertSame(2, $r['total']);
        $this->assertCount(1, $r['rows']);

        $r = submission_manager::list_submissions($this->jobform->id, submission_manager::STATUS_DRAFT);
        $this->assertEquals($other->id, $r['rows'][0]->userid);
    }

    public function test_get_answer_rows_labels_and_formats(): void {
        $sid = submission_manager::save($this->jobform->id, $this->student->id,
            [$this->fields[0] => 'Ali', $this->fields[1] => '946684800', $this->fields[2] => '1'],
            submission_manager::STATUS_SUBMITTED);
        $rows = submission_manager::get_answer_rows($this->jobform->id, $sid);
        $this->assertSame(['Full name', 'Birth date', 'Has car'], array_column($rows, 'name'));
        $this->assertSame('Ali', $rows[0]['display']);
        $this->assertSame(get_string('yes'), $rows[2]['display']);
        $this->assertSame('946684800', $rows[1]['value']);
        $this->assertNotSame('946684800', $rows[1]['display']); // A readable date.
    }

    public function test_teacher_lists_views_and_deletes(): void {
        global $DB;
        $sid = submission_manager::save($this->jobform->id, $this->student->id,
            [$this->fields[0] => 'Ali'], submission_manager::STATUS_SUBMITTED);
        $this->setUser($this->teacher);

        $list = external_api::clean_returnvalue(\mod_jobform_external::get_submissions_returns(),
            \mod_jobform_external::get_submissions((int) $this->jobform->cmid));
        $this->assertSame(1, $list['total']);
        $this->assertSame($sid, $list['submissions'][0]['id']);
        $this->assertSame(fullname($this->student), $list['submissions'][0]['user']['fullname']);

        $one = external_api::clean_returnvalue(\mod_jobform_external::get_submission_returns(),
            \mod_jobform_external::get_submission($sid));
        $this->assertSame('Ali', $one['answers'][0]['display']);
        $this->assertCount(3, $one['answers']);

        external_api::clean_returnvalue(\mod_jobform_external::delete_submission_returns(),
            \mod_jobform_external::delete_submission($sid));
        $this->assertFalse($DB->record_exists('jobform_submission', ['id' => $sid]));
        $this->assertFalse($DB->record_exists('jobform_submission_data', ['submissionid' => $sid]));
    }

    public function test_student_cannot_use_teacher_functions(): void {
        $sid = submission_manager::save($this->jobform->id, $this->student->id, [], submission_manager::STATUS_SUBMITTED);
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \mod_jobform_external::delete_submission($sid);
    }

    public function test_unknown_submission_is_a_clear_error(): void {
        $this->setUser($this->teacher);
        $this->expectExceptionMessage(get_string('errorsubmissionnotfound', 'mod_jobform', 999999));
        \mod_jobform_external::get_submission(999999);
    }
}
