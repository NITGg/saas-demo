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

namespace local_nit_tutors\local;

/**
 * Messages about applications: to the reviewers when one arrives, to the teacher when it is
 * decided. Never throws — a message that cannot be sent must not undo a decision.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {

    /**
     * A new application (or a resubmission) waits for review.
     *
     * @param int $userid the applicant
     */
    public static function submitted(int $userid): void {
        $user = \core_user::get_user($userid);
        if (!$user) {
            return;
        }
        $url = new \moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $userid]);
        foreach (self::reviewers() as $reviewer) {
            self::send('applicationsubmitted', $reviewer, get_string('msg_submitted_subject', 'local_nit_tutors', fullname($user)),
                get_string('msg_submitted_body', 'local_nit_tutors', ['name' => fullname($user), 'url' => $url->out(false)]),
                $url);
        }
    }

    /**
     * An approved teacher changed what the academy reviews: the intro video or a qualification.
     *
     * @param int $userid
     */
    public static function updates_waiting(int $userid): void {
        $user = \core_user::get_user($userid);
        if (!$user) {
            return;
        }
        $url = new \moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $userid]);
        foreach (self::reviewers() as $reviewer) {
            self::send('applicationsubmitted', $reviewer, get_string('msg_updates_subject', 'local_nit_tutors', fullname($user)),
                get_string('msg_updates_body', 'local_nit_tutors', ['name' => fullname($user), 'url' => $url->out(false)]), $url);
        }
    }

    /**
     * Tell the teacher the decision.
     *
     * @param int $userid
     * @param string $status approved|rejected|changes|suspended
     * @param string $note
     */
    public static function reviewed(int $userid, string $status, string $note): void {
        $user = \core_user::get_user($userid);
        if (!$user) {
            return;
        }
        $url = new \moodle_url('/local/nit_tutors/application.php');
        $a = ['name' => fullname($user), 'note' => trim($note) !== '' ? trim($note) : '-', 'url' => $url->out(false)];
        self::send('applicationupdate', $user, get_string('msg_' . $status . '_subject', 'local_nit_tutors'),
            get_string('msg_' . $status . '_body', 'local_nit_tutors', $a), $url);
    }

    /**
     * Tell the teacher what happened to their video.
     *
     * @param int $userid
     * @param bool $approved
     * @param string $note
     */
    public static function video_reviewed(int $userid, bool $approved, string $note): void {
        $user = \core_user::get_user($userid);
        if (!$user) {
            return;
        }
        $url = new \moodle_url('/local/nit_tutors/application.php');
        $key = $approved ? 'msg_videook' : 'msg_videono';
        self::send('applicationupdate', $user, get_string($key . '_subject', 'local_nit_tutors'),
            get_string($key . '_body', 'local_nit_tutors', ['note' => trim($note) !== '' ? trim($note) : '-',
                'url' => $url->out(false)]), $url);
    }

    /**
     * Users who review applications.
     *
     * @return \stdClass[]
     */
    private static function reviewers(): array {
        $users = get_users_by_capability(\context_system::instance(), 'local/nit_tutors:review', 'u.*');
        foreach (get_admins() as $admin) {
            $users[$admin->id] = $admin;
        }
        return array_values($users);
    }

    /**
     * Send one message (best effort).
     *
     * @param string $provider
     * @param \stdClass $to
     * @param string $subject
     * @param string $body plain text
     * @param \moodle_url $url
     */
    private static function send(string $provider, \stdClass $to, string $subject, string $body, \moodle_url $url): void {
        try {
            $message = new \core\message\message();
            $message->component = 'local_nit_tutors';
            $message->name = $provider;
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $to;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = nl2br(s($body));
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $subject;
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_tutors: message not sent: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
