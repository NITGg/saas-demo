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

namespace local_nit_lessons;

use local_nit_lessons\local\instant_ui;
use local_nit_lessons\service\instant_service;

/**
 * The instant-lesson widgets on a page: a student with an open request gets its floating box on
 * every page (so they can open the tutor's profile and still confirm), and the page config says
 * which request it is.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\local\instant_ui
 */
final class instant_ui_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->reset_added();
    }

    /** Lets script() print again (it prints once per page). */
    private function reset_added(): void {
        $prop = new \ReflectionProperty(instant_ui::class, 'added');
        $prop->setValue(null, false);
    }

    private function request(int $studentid, string $status): int {
        global $DB;
        return (int) $DB->insert_record('nit_instant_request', (object) [
            'studentid' => $studentid, 'subject' => 'Physics', 'status' => $status,
            'expiresat' => time() + 60, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function footer_html(): string {
        global $PAGE;
        $hook = new \core\hook\output\before_standard_footer_html_generation($PAGE->get_renderer('core'));
        instant_ui::footer($hook);
        return $hook->get_output();
    }

    public function test_open_request_id_only_counts_searching_and_accepted(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->assertSame(0, instant_ui::open_request_id((int) $student->id));

        $this->request((int) $student->id, instant_service::EXPIRED);
        $this->request((int) $student->id, instant_service::MATCHED);
        $this->assertSame(0, instant_ui::open_request_id((int) $student->id));

        $searching = $this->request((int) $student->id, instant_service::SEARCHING);
        $this->assertSame($searching, instant_ui::open_request_id((int) $student->id));

        global $DB;
        $DB->set_field('nit_instant_request', 'status', instant_service::ACCEPTED_WAIT, ['id' => $searching]);
        $this->assertSame($searching, instant_ui::open_request_id((int) $student->id));

        // Someone else's request is not theirs.
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame(0, instant_ui::open_request_id((int) $other->id));
    }

    public function test_footer_adds_the_script_for_a_student_with_an_open_request(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $this->assertSame('', $this->footer_html(), 'Nothing on the page without a request.');

        $id = $this->request((int) $student->id, instant_service::ACCEPTED_WAIT);
        $html = $this->footer_html();
        $this->assertStringContainsString('instant.js', $html);
        $this->assertMatchesRegularExpression('/"request":' . $id . '\b/', $html);
        $this->assertStringContainsString('student.php?tab=book', str_replace('\/', '/', $html));
    }

    public function test_script_config_has_no_request_when_none_is_open(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $html = instant_ui::script((int) $student->id);
        $this->assertStringContainsString('"request":0', $html);
        foreach (['onlinestatus', 'offlinestatus', 'joinnow', 'backtorequest', 'close', 'matchedhere'] as $key) {
            $this->assertStringContainsString('"' . $key . '":', $html, $key);
        }
    }

    public function test_footer_adds_nothing_when_instant_lessons_are_off(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $this->request((int) $student->id, instant_service::SEARCHING);
        set_config('instant_enabled', 0, 'local_nit_lessons');
        $this->assertSame('', $this->footer_html());
    }
}
