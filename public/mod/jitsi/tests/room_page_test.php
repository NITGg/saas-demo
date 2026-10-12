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

namespace mod_jitsi;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

/**
 * The room page (view.php): opening the activity shows a "Join" step instead of the call,
 * and the "Session recordings" tab follows the recordings' visibility rule.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::jitsi_prejoin_html
 * @covers     ::jitsi_show_recordings_tab
 * @covers     ::jitsi_closed_html
 * @covers     ::jitsi_room_name
 * @covers     ::jitsi_is_lesson_room
 * @covers     \mod_jitsi\local\access::waiting_room
 * @covers     ::jitsi_dock_context
 */
final class room_page_test extends \advanced_testcase {

    public function test_prejoin_links_to_the_call(): void {
        $cm = (object) ['id' => 42];
        $html = jitsi_prejoin_html($cm, false);

        $this->assertStringContainsString('id="jitsi-prejoin"', $html);
        $this->assertStringContainsString('/mod/jitsi/view.php?id=42&amp;join=1', $html);
        $this->assertStringContainsString(get_string('joinsession', 'jitsi'), $html);
        $this->assertStringNotContainsString(get_string('prejoin_autorecord', 'jitsi'), $html);
    }

    public function test_prejoin_warns_the_host_about_the_recording(): void {
        $html = jitsi_prejoin_html((object) ['id' => 42], true);
        $this->assertStringContainsString(get_string('prejoin_autorecord', 'jitsi'), $html);
    }

    public function test_closed_room_says_why(): void {
        $cm = (object) ['id' => 42, 'availableinfo' => ''];
        $waiting = (object) ['code' => \mod_jitsi\local\access::WAITING,
            'message' => get_string('waitingforteacher', 'jitsi')];
        $html = jitsi_closed_html($waiting, $cm);
        $this->assertStringContainsString('data-code="waitingforteacher"', $html);
        $this->assertStringContainsString(get_string('waiting_title', 'jitsi'), $html);
        $this->assertStringNotContainsString('join=1', $html);
        // Opened the activity: the "Join" button comes when the teacher arrives.
        $this->assertStringContainsString(get_string('waiting_next_button', 'jitsi'), $html);
        $this->assertStringNotContainsString(get_string('waiting_next_admit', 'jitsi'), $html);
        // Pressed "Join" in a room with a waiting room: in automatically, then the teacher admits.
        $html = jitsi_closed_html($waiting, $cm, true, true);
        $this->assertStringContainsString(get_string('waiting_title_join', 'jitsi'), $html);
        $this->assertStringContainsString(get_string('waiting_next_join', 'jitsi'), $html);
        $this->assertStringContainsString(get_string('waiting_next_admit', 'jitsi'), $html);

        // A Moodle restriction shows its own text (or "ended" when it has none).
        $hidden = (object) ['code' => \mod_jitsi\local\access::UNAVAILABLE, 'message' => 'x'];
        $cm->availableinfo = 'Available until 1 Jan';
        $this->assertStringContainsString('Available until 1 Jan', jitsi_closed_html($hidden, $cm));
        $cm->availableinfo = '';
        $this->assertStringContainsString(get_string('sessionended', 'jitsi'), jitsi_closed_html($hidden, $cm));
    }

    public function test_lesson_room_name_tells_the_recorder(): void {
        $this->resetAfterTest();
        set_config('lessons_courseid', 25, 'local_nit_lessons');
        $jitsi = (object) ['id' => 7];
        $lesson = (object) ['id' => 69, 'course' => 25];
        $courseroom = (object) ['id' => 70, 'course' => 4];

        $this->assertTrue(jitsi_is_lesson_room($lesson));
        $this->assertFalse(jitsi_is_lesson_room($courseroom));
        // nit_<slug>_<cmid>_<hash>: 12 hex for a lesson, 8 for a course room (the server's
        // recorder script keeps only a course room's recording on the teacher).
        $this->assertMatchesRegularExpression('/^nit_[a-z0-9-]+_69_[a-f0-9]{12}$/', jitsi_room_name($jitsi, $lesson));
        $this->assertMatchesRegularExpression('/^nit_[a-z0-9-]+_70_[a-f0-9]{8}$/', jitsi_room_name($jitsi, $courseroom));

        // No lessons course set: every room is a course room.
        set_config('lessons_courseid', 0, 'local_nit_lessons');
        $this->assertFalse(jitsi_is_lesson_room($lesson));
    }

    public function test_lesson_rooms_have_a_waiting_room(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        // A live session's room: in the lessons course (a private lesson) and elsewhere.
        $room = function(int $courseid) use ($gen, $teacher, $student): \cm_info {
            $jitsi = $gen->create_module('jitsi', ['course' => $courseid, 'lobby_enabled' => 0]);
            \local_academysessions\session_manager::create_session($courseid, $teacher->id, 'Live', time(),
                [$student->id], '', 60, null, $jitsi->id);
            return get_fast_modinfo($courseid)->get_cm($jitsi->cmid);
        };
        $lessons = $gen->create_course();
        set_config('lessons_courseid', $lessons->id, 'local_nit_lessons');

        // The student asks, the teacher lets them in (the activity's own setting is off).
        $this->assertTrue(\mod_jitsi\local\access::waiting_room($room((int) $lessons->id)));
        // Other live sessions: Moodle's gate only, no lobby.
        $this->assertFalse(\mod_jitsi\local\access::waiting_room($room((int) $gen->create_course()->id)));
    }

    public function test_dock_buttons_per_role(): void {
        $actions = fn(array $ctx) => array_column($ctx['buttons'], 'action');

        // The host: the whiteboard, everyone's list (with who knocks), grid view, mute all,
        // recording, end for all.
        $host = jitsi_dock_context(true, false);
        $this->assertSame(['mic', 'camera', 'screen', 'chat', 'hand', 'whiteboard', 'people', 'tile', 'muteall',
            'record', 'settings', 'fullscreen'], $actions($host));
        $this->assertTrue($host['ishost']);
        // A student who sees the teacher only: no whiteboard button (Jitsi lets only a host
        // open it; it reaches the student's screen by itself), no list, no grid view, no host tools.
        $student = jitsi_dock_context(false, true);
        $this->assertSame(['mic', 'camera', 'screen', 'chat', 'hand', 'settings', 'fullscreen'], $actions($student));
        $this->assertFalse($student['ishost']);

        // Every icon is there, as plain SVG with its two-tone layer.
        foreach (array_merge($host['buttons'], [['icon' => $host['leaveicon']], ['icon' => $host['endallicon']]]) as $b) {
            $this->assertStringStartsWith('<svg', $b['icon']);
            $this->assertStringContainsString('class="duo"', $b['icon']);
            $this->assertStringNotContainsString('<script', $b['icon']);
        }
        $this->assertStringStartsWith('<svg', $host['buttons'][0]['iconoff']);
    }

    public function test_recordings_tab_visibility(): void {
        $now = time();
        $live = (object) ['status' => 'live', 'start_time' => $now - 600, 'duration' => 60];
        $ended = (object) ['status' => 'ended', 'start_time' => $now - 600, 'duration' => 60];
        $over = (object) ['status' => 'live', 'start_time' => $now - 7200, 'duration' => 60];

        // The host: always.
        $this->assertTrue(jitsi_show_recordings_tab(true, $live));
        // A student: not while the linked session runs, once it ended or its time passed.
        $this->assertFalse(jitsi_show_recordings_tab(false, $live));
        $this->assertTrue(jitsi_show_recordings_tab(false, $ended));
        $this->assertTrue(jitsi_show_recordings_tab(false, $over));
        // A standalone course room (no session): always.
        $this->assertTrue(jitsi_show_recordings_tab(false, null));
        $this->assertTrue(jitsi_show_recordings_tab(false, false));
    }
}
