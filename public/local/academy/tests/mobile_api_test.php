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

namespace local_academy;

use local_academy\api\courses;
use local_academy\api\endpoint;
use local_academy\local\academic_structure;
use local_academy\local\user_fields;

/**
 * Tests for the shared mobile API layer and the academy mobile functions.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\api\endpoint
 * @covers     \local_academy\profile_manager
 * @covers     \local_academy\api\courses
 */
final class mobile_api_test extends \advanced_testcase {

    public function test_describe_shows_business_errors_without_err_prefix(): void {
        [$code, $message] = endpoint::describe(new \moodle_exception('err_invalidphone', 'local_academy'));
        $this->assertSame('invalidphone', $code);
        $this->assertSame(get_string('err_invalidphone', 'local_academy'), $message);
    }

    public function test_describe_hides_database_and_coding_errors(): void {
        $this->assertSame('internalerror', endpoint::describe(new \dml_write_exception('secret sql'))[0]);
        $this->assertSame('internalerror', endpoint::describe(new \coding_exception('secret'))[0]);
        $this->assertSame('internalerror', endpoint::describe(new \RuntimeException('secret'))[0]);
        $this->assertStringNotContainsString('secret', endpoint::describe(new \dml_write_exception('secret sql'))[1]);
    }

    public function test_describe_maps_capability_errors_to_nopermissions(): void {
        $e = new \required_capability_exception(\context_system::instance(), 'moodle/site:config', 'nopermissions', '');
        $this->assertSame('nopermissions', endpoint::describe($e)[0]);
    }

    public function test_site_locked_when_suspended_except_for_admins(): void {
        $this->resetAfterTest();
        if (!class_exists('\local_license\license')) {
            $this->markTestSkipped('local_license not installed');
        }
        $user = $this->getDataGenerator()->create_user();
        set_config('suspended', 0, 'local_license');
        $this->assertFalse(endpoint::site_locked((int) $user->id));
        set_config('suspended', 1, 'local_license');
        $this->assertTrue(endpoint::site_locked((int) $user->id));
        $this->assertFalse(endpoint::site_locked((int) get_admin()->id));
    }

    public function test_update_profile_validates_and_saves(): void {
        $this->resetAfterTest();
        user_fields::ensure();
        $user = $this->getDataGenerator()->create_user();

        profile_manager::update_profile((int) $user->id, [
            'firstname' => 'Mona', 'phone' => '01012345678', 'fields' => ['school' => 'Test school'],
        ]);
        $this->assertSame('Mona', \core_user::get_user($user->id)->firstname);
        $this->assertSame('01012345678', \core_user::get_user($user->id)->phone1);
        $this->assertSame('Test school', user_fields::values((int) $user->id)['school']);

        foreach ([
            [['phone' => 'abc'], 'err_invalidphone'],
            [['firstname' => '  '], 'err_requiredfield'],
            [['fields' => ['nationalid' => '123']], 'err_invalidnationalid'],
            [['fields' => ['gender' => 'not an option']], 'err_invalidoption'],
        ] as [$data, $expected]) {
            try {
                profile_manager::update_profile((int) $user->id, $data);
                $this->fail('Expected ' . $expected);
            } catch (\moodle_exception $e) {
                $this->assertSame($expected, $e->errorcode);
            }
        }
    }

    public function test_update_profile_rejects_a_division_of_another_system(): void {
        $this->resetAfterTest();
        user_fields::ensure();
        $user = $this->getDataGenerator()->create_user();
        $map = academic_structure::map();
        $systems = array_keys($map);
        $this->assertGreaterThanOrEqual(2, count($systems));
        // A division of the second system that the first system does not have.
        $foreign = array_values(array_diff($map[$systems[1]], $map[$systems[0]]));
        if (!$foreign) {
            $foreign = array_values(array_diff($map[$systems[2]] ?? [], $map[$systems[0]]));
        }
        $this->assertNotEmpty($foreign);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_divisionmismatch', 'local_academy'));
        profile_manager::update_profile((int) $user->id, ['fields' => [
            academic_structure::USER_SYSTEM => $systems[0],
            academic_structure::USER_DIVISION => $foreign[0],
        ]]);
    }

    public function test_field_specs_lists_division_systems(): void {
        $this->resetAfterTest();
        user_fields::ensure();
        $specs = array_column(profile_manager::field_specs(), null, 'shortname');
        $this->assertArrayHasKey(academic_structure::USER_DIVISION, $specs);
        $division = $specs[academic_structure::USER_DIVISION];
        $this->assertSame('menu', $division['type']);
        $this->assertNotEmpty($division['options']);
        $this->assertArrayHasKey('systems', $division['options'][0]);
        $this->assertNotEmpty($division['options'][0]['systems']);
    }

    public function test_lessons_require_enrolment(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_user();
        $outsider = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $data = courses::lessons((int) $course->id);
        $this->assertSame((int) $course->id, $data['courseid']);
        $this->assertCount(1, $data['sections'][0]['lessons']);
        $this->assertFalse($data['sections'][0]['lessons'][0]['locked']);

        $this->setUser($outsider);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_notenrolled', 'local_academy'));
        courses::lessons((int) $course->id);
    }

    public function test_locked_lesson_cannot_be_opened(): void {
        $this->resetAfterTest();
        set_config('player_lockorder', 1, 'local_academy');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $first = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $second = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        courses::require_lesson_open((int) $first->cmid); // The first lesson is open.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_lessonlocked', 'local_academy'));
        courses::require_lesson_open((int) $second->cmid);
    }
}
