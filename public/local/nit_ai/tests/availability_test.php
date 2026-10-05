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

namespace local_nit_ai;

/**
 * Tests for the assistant availability gates and the get_status web service.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_ai\api::availability
 * @covers     \local_nit_ai\external\get_status
 */
final class availability_test extends \advanced_testcase {

    /**
     * A course + page module + enrolled student, logged in as the student.
     *
     * @return array [cm (pretending to be a vdocipher video), context]
     */
    protected function setup_student(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $cm = (object) ['id' => $page->cmid, 'modname' => 'vdocipher', 'instance' => 0, 'course' => $course->id];
        return [$cm, \context_module::instance($page->cmid)];
    }

    /**
     * Insert a transcript row.
     *
     * @param int $cmid
     * @param array $over
     * @return int
     */
    protected function transcript(int $cmid, array $over = []): int {
        global $DB;
        return $DB->insert_record(api::TABLE, (object) ($over + [
            'cmid' => $cmid, 'courseid' => 0, 'component' => 'mod_vdocipher', 'sourceref' => 'vid1',
            'format' => 'vtt', 'lang' => 'ar', 'hastimestamps' => 1, 'segmentcount' => 1, 'lasttimestamp' => 5,
            'plaintext' => 'x', 'enabled' => 1, 'approved' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    public function test_reasons_follow_the_gates_in_order(): void {
        global $DB;
        $this->resetAfterTest();
        [$cm, $context] = $this->setup_student();

        $this->assertSame('notranscript', api::availability($cm, $context, 'vid1')['reason']);

        $id = $this->transcript((int) $cm->id, ['enabled' => 0, 'approved' => 0]);
        $this->assertSame('disabled', api::availability($cm, $context, 'vid1')['reason']);

        $DB->set_field(api::TABLE, 'enabled', 1, ['id' => $id]);
        $this->assertSame('notapproved', api::availability($cm, $context, 'vid1')['reason']);

        $DB->set_field(api::TABLE, 'approved', 1, ['id' => $id]);
        $this->assertSame('stale', api::availability($cm, $context, 'another-video')['reason']);

        // Every reason agrees with the boolean gate.
        $state = api::availability($cm, $context, 'vid1');
        $this->assertSame($state['available'], api::is_available($cm, $context, 'vid1'));
    }

    public function test_no_capability_is_reported(): void {
        global $DB;
        $this->resetAfterTest();
        [$cm, $context] = $this->setup_student();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('local/nit_ai:use', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->assertSame('nopermission', api::availability($cm, $context)['reason']);
    }

    public function test_get_status_rejects_non_video_activities(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $res = \core_external\external_api::clean_returnvalue(external\get_status::execute_returns(),
            external\get_status::execute((int) $page->cmid));
        $this->assertFalse($res['available']);
        $this->assertSame('unsupported', $res['reason']);
        $this->assertSame('', $res['provider']);
        $this->assertNotSame('', $res['message']);
    }
}
