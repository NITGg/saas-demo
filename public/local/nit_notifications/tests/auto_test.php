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

namespace local_nit_notifications;

/**
 * Automatic notifications: quizzes, session reminders, subscriptions, switches.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_notifications\auto
 */
final class auto_test extends \advanced_testcase {

    /**
     * How many notifications a source produced.
     *
     * @param string $source
     * @return int
     */
    private function sent(string $source): int {
        global $DB;
        return $DB->count_records('local_nit_notif', ['source' => $source]);
    }

    public function test_new_quiz_once_and_only_when_visible(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->redirectMessages();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_and_enrol($course, 'student');
        $gen->create_and_enrol($course, 'editingteacher');

        $gen->create_module('quiz', ['course' => $course->id]);
        $this->assertSame(1, $this->sent('newquiz'));

        $hidden = $gen->create_module('quiz', ['course' => $course->id, 'visible' => 0]);
        $this->assertSame(1, $this->sent('newquiz'), 'a hidden quiz is not announced');
        set_coursemodule_visible($hidden->cmid, 1);
        $cm = get_coursemodule_from_id('quiz', $hidden->cmid);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        $this->assertSame(2, $this->sent('newquiz'), 'announced when shown');
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        $this->assertSame(2, $this->sent('newquiz'), 'never twice');

        set_config('auto_newquiz', 0, 'local_nit_notifications');
        $gen->create_module('quiz', ['course' => $course->id]);
        $this->assertSame(2, $this->sent('newquiz'), 'switched off');
    }

    public function test_session_reminders_follow_the_lead_times(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->redirectMessages();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $now = time();
        $sid = $DB->insert_record('academy_live_sessions', (object) ['courseid' => $course->id, 'teacherid' => $teacher->id,
            'title' => 'Revision', 'start_time' => $now + 50 * MINSECS, 'duration' => 50, 'status' => 'scheduled',
            'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('academy_session_students', (object) ['sessionid' => $sid, 'userid' => $student->id]);

        auto::send_session_reminders($now);
        $this->assertSame(1, $this->sent('session_reminder'), 'one hour lead');
        auto::send_session_reminders($now + MINSECS);
        $this->assertSame(1, $this->sent('session_reminder'), 'not again');
        auto::send_session_reminders($now + 41 * MINSECS);
        $this->assertSame(2, $this->sent('session_reminder'), 'ten minutes lead');

        // A lesson confirmed five minutes ahead gets one reminder each, not one per lead.
        $DB->insert_record('nit_lesson', (object) ['studentid' => $student->id, 'teacherid' => $teacher->id,
            'subject' => 'Physics', 'status' => 'confirmed', 'requested_time' => 0, 'confirmed_time' => $now + 5 * MINSECS,
            'timecreated' => $now, 'timemodified' => $now]);
        auto::send_session_reminders($now);
        $this->assertSame(2, $this->sent('lesson_reminder'));
    }

    public function test_subscription_started_then_renewed_in_the_users_language(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $user = $this->getDataGenerator()->create_user();
        $planid = $DB->insert_record('nit_subscription', (object) ['name' => 'Gold', 'price' => 100, 'duration_days' => 30,
            'status' => 'active', 'timecreated' => time(), 'timemodified' => time()]);

        \local_nit_subscriptions\subscription_purchase_manager::fulfil_from_gateway($user->id, $planid, 100, 'ref-1');
        $this->assertSame(1, $this->sent('subscription_started'));
        $this->assertStringContainsString('Gold', $sink->get_messages()[0]->subject);

        $DB->set_field('nit_sub_purchase', 'status', 'expired', ['userid' => $user->id]);
        \local_nit_subscriptions\subscription_purchase_manager::fulfil_from_gateway($user->id, $planid, 100, 'ref-2');
        $this->assertSame(1, $this->sent('subscription_renewed'));
    }

    public function test_from_string_writes_every_language_and_resolves_placeholders(): void {
        $this->resetAfterTest();
        $text = mlang::from_string('auto_newquiz_title', 'local_nit_notifications',
            ['quiz' => mlang::compose(['en' => 'Unit 1', 'ar' => 'الوحدة 1'])]);
        $this->assertSame('New quiz: Unit 1', mlang::resolve($text, 'en'));
        $this->assertStringNotContainsString('{mlang', mlang::resolve($text, 'en'));
    }
}
