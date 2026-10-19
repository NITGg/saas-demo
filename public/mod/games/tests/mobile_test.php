<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_games;

use core_external\external_api;

/**
 * What the mobile app relies on to play a Game activity.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::games_get_coursemodule_info
 * @covers     \mod_games\external\record_play
 */
final class mobile_test extends \advanced_testcase {

    public function test_course_contents_expose_gameid(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/externallib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('games', ['course' => $course->id, 'gameid' => 'quiz']);
        $this->setAdminUser();

        $sections = external_api::clean_returnvalue(\core_course_external::get_course_contents_returns(),
            \core_course_external::get_course_contents($course->id));

        $found = null;
        foreach ($sections as $section) {
            foreach ($section['modules'] as $module) {
                if ($module['modname'] === 'games') {
                    $found = $module;
                }
            }
        }
        $this->assertNotNull($found);
        $this->assertSame('quiz', json_decode($found['customdata'], true)['gameid']);
    }

    public function test_record_play_accumulates_for_the_student(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('games', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        \mod_games\external\record_play::execute($cm->cmid, 5, 3, 50);
        $result = external_api::clean_returnvalue(\mod_games\external\record_play::execute_returns(),
            \mod_games\external\record_play::execute($cm->cmid, 7, 6, 40));

        $this->assertSame(2, $result['plays']);
        $this->assertSame(12, $result['points']);
        $this->assertSame(50, $result['bestscore']);
        $this->assertSame(6, $result['beststreak']);
    }

    public function test_record_play_rejects_user_not_in_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('games', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        \mod_games\external\record_play::execute($cm->cmid, 5, 3, 50);
    }
}
