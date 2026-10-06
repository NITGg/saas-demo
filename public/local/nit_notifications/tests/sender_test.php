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
 * Who may send to whom, who receives, delivery and the scoped log.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_notifications\audience
 * @covers     \local_nit_notifications\sender
 */
final class sender_test extends \advanced_testcase {

    /**
     * Two categories: A (courses c1, c2) and B (course c3), each with a manager;
     * c1 has an active student, a suspended enrolment and a teacher; c2 a student.
     *
     * @return \stdClass
     */
    private function setup_site(): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $d = new \stdClass();
        $d->cata = $gen->create_category();
        $d->catb = $gen->create_category();
        $d->c1 = $gen->create_course(['category' => $d->cata->id]);
        $d->c2 = $gen->create_course(['category' => $d->cata->id]);
        $d->c3 = $gen->create_course(['category' => $d->catb->id]);
        $d->s1 = $gen->create_and_enrol($d->c1, 'student');
        $d->s2 = $gen->create_and_enrol($d->c1, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $d->s3 = $gen->create_and_enrol($d->c2, 'student');
        $d->loner = $gen->create_user();
        $d->teacher = $gen->create_and_enrol($d->c1, 'editingteacher');
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $d->mgra = $gen->create_user();
        $d->mgrb = $gen->create_user();
        role_assign($managerrole, $d->mgra->id, \context_coursecat::instance($d->cata->id)->id);
        role_assign($managerrole, $d->mgrb->id, \context_coursecat::instance($d->catb->id)->id);
        return $d;
    }

    public function test_rights_follow_the_manager_scope(): void {
        $this->resetAfterTest();
        $d = $this->setup_site();
        $admin = get_admin();

        $this->assertCount(6, audience::allowed($admin->id));
        $this->assertSame(['course_students', 'course_teachers'], audience::allowed($d->mgra->id));
        $scope = audience::course_ids($d->mgra->id);
        sort($scope);
        $this->assertSame([(int) $d->c1->id, (int) $d->c2->id], $scope);
        $this->assertSame([], audience::allowed($d->teacher->id), 'teachers do not send');
        $this->assertSame('err_audience', audience::validate('all_students', 0, $d->mgra->id));
        $this->assertSame('err_course', audience::validate('course_students', $d->c3->id, $d->mgra->id));
        $this->assertNull(audience::validate('course_students', 0, $d->mgra->id), 'all my courses');
        $this->assertSame('err_choosecourse', audience::validate('course_students', 0, $admin->id));
    }

    public function test_audiences(): void {
        $this->resetAfterTest();
        $d = $this->setup_site();
        $admin = get_admin();

        $this->assertSame([(int) $d->s1->id], audience::resolve('course_students', $d->c1->id, $admin->id),
            'active student enrolments only');
        $this->assertSame([(int) $d->teacher->id], audience::resolve('course_teachers', $d->c1->id, $admin->id));
        $this->assertEqualsCanonicalizing([(int) $d->s1->id, (int) $d->s3->id],
            audience::resolve('course_students', 0, $d->mgra->id));

        $students = audience::resolve('all_students', 0, $admin->id);
        $this->assertContains((int) $d->loner->id, $students, 'students need no enrolment');
        foreach ([$d->teacher, $d->mgra, $admin] as $staff) {
            $this->assertNotContains((int) $staff->id, $students);
        }
        $this->assertContains((int) $d->teacher->id, audience::resolve('all_teachers', 0, $admin->id));
        $this->assertEqualsCanonicalizing([(int) $d->mgra->id, (int) $d->mgrb->id],
            audience::resolve('managers', 0, $admin->id));
        $this->assertNotContains((int) $admin->id, audience::resolve('admins', 0, $admin->id), 'never the sender');
    }

    public function test_send_delivers_and_tracks_reading(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $d = $this->setup_site();
        $this->setUser($d->mgra);
        $sink = $this->redirectMessages();

        $notif = sender::send(['audience' => 'course_students', 'courseid' => 0, 'type' => 'courses',
            'title' => 'Exam on Thursday', 'body' => 'Revise unit 1', 'url' => 'https://example.com/c']);

        $this->assertSame('done', $notif->status);
        $this->assertSame(2, (int) $notif->total);
        $this->assertSame(2, (int) $notif->sent);
        $this->assertCount(2, $sink->get_messages());
        $this->assertSame('Exam on Thursday', $sink->get_messages()[0]->subject);
        $this->assertEquals(2, $DB->count_records('local_nit_notif_rcpt',
            ['notifid' => $notif->id, 'status' => sender::RCPT_SENT]));
    }

    public function test_send_rejects_bad_input(): void {
        $this->resetAfterTest();
        $d = $this->setup_site();
        $this->setUser($d->mgra);
        $base = ['audience' => 'course_students', 'courseid' => $d->c1->id, 'type' => 'general', 'title' => 'T', 'body' => 'B'];
        $cases = [
            'err_audience' => ['audience' => 'all_students'],
            'err_course' => ['courseid' => $d->c3->id],
            'err_type' => ['type' => 'spam'],
            'err_titletoolong' => ['title' => str_repeat('x', sender::MAX_TITLE + 1)],
            'err_url' => ['url' => 'javascript:alert(1)'],
        ];
        foreach ($cases as $code => $override) {
            try {
                sender::send($override + $base);
                $this->fail("expected $code");
            } catch (\moodle_exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }
        $this->expectExceptionMessage(get_string('err_norecipients', 'local_nit_notifications'));
        sender::send(['audience' => 'course_teachers', 'courseid' => $d->c2->id] + $base);
    }

    public function test_log_is_scoped_and_automatic_notifications_land_in_it(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $d = $this->setup_site();
        $this->redirectMessages();

        $auto = sender::send_to_users('subscription_expiry', 'subscriptions', [$d->s1->id], 'Ends soon', '3 days', $d->c1->id);
        $this->assertSame(0, (int) $auto->senderid);
        $this->assertSame(1, (int) $auto->sent);

        $ids = fn($userid) => array_map(fn($n) => (int) $n->id, sender::log([], 0, 50, $userid)['items']);
        $this->assertContains((int) $auto->id, $ids($d->mgra->id), 'about a course they manage');
        $this->assertNotContains((int) $auto->id, $ids($d->mgrb->id));
        $this->assertContains((int) $auto->id, $ids(get_admin()->id));
        $this->assertFalse(sender::can_view($auto, $d->mgrb->id));
    }

    public function test_email_needs_the_box_and_the_users_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $d = $this->setup_site();
        $this->redirectMessages();
        // s1 turned email on for administration notifications (and, from before, for the
        // plain notification too — which must not email by itself any more); s3 never chose.
        set_user_preference('message_provider_local_nit_notifications_announcement_email_enabled', 'email', $d->s1);
        set_user_preference('message_provider_local_nit_notifications_announcement_enabled', 'popup,email', $d->s1);
        $this->setUser($d->mgra);
        $base = ['audience' => 'course_students', 'courseid' => 0, 'type' => 'general', 'title' => 'T', 'body' => 'B'];

        $emails = $this->redirectEmails();
        sender::send($base + ['email' => 0]);
        $this->assertSame(0, $emails->count(), 'box not ticked: no email, even for s1');

        $emails->clear();
        $notif = sender::send($base + ['email' => 1]);
        $this->assertSame(1, $emails->count(), 'box ticked: only the user who turned email on');
        $this->assertSame($d->s1->email, $emails->get_messages()[0]->to);
        $this->assertEquals(sender::EMAIL_OFF, $DB->get_field('local_nit_notif_rcpt', 'emailstatus',
            ['notifid' => $notif->id, 'userid' => $d->s3->id]));
    }

    public function test_deliver_works_in_batches(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $d = $this->setup_site();
        $this->redirectMessages();
        $notif = sender::send_to_users('test', 'general', [$d->s1->id, $d->s3->id], 'T', 'B');
        $DB->set_field('local_nit_notif_rcpt', 'status', sender::RCPT_QUEUED, ['notifid' => $notif->id]);

        $this->assertSame(1, sender::deliver((int) $notif->id, 1));
        $this->assertSame(0, sender::deliver((int) $notif->id, 1));
        $this->assertSame('done', $DB->get_field('local_nit_notif', 'status', ['id' => $notif->id]));
    }
}
