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

namespace local_nit_lessons\local;

/**
 * Tells the other side of a live lesson what just happened (request, answer, start, cancel…).
 *
 * Called after the action committed. Never throws: a message that cannot be sent must not
 * undo a lesson change or a money move.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {

    /** Events and who hears about them. */
    const RECIPIENT = [
        'requested' => 'teacher',
        'accepted_by_teacher' => 'student',
        'rejected_by_teacher' => 'student',
        'suggested_by_teacher' => 'student',
        'accepted_by_student' => 'teacher',
        'rejected_by_student' => 'teacher',
        'suggested_by_student' => 'teacher',
        'started' => 'student',
        'completed' => 'student',
        'student_absent' => 'student',
        'teacher_absent' => 'teacher',
        'request_withdrawn' => 'teacher',
        'cancelled_by_student' => 'teacher',
        'cancelled_by_teacher' => 'student',
        'reschedule_requested' => 'other',
        'reschedule_answered' => 'other',
    ];

    /**
     * Send the message for an event.
     *
     * @param string $event key of RECIPIENT
     * @param array $lesson formatted lesson (lesson_service::format_lesson)
     * @param int $actorid who did it
     * @return void
     */
    public static function send(string $event, array $lesson, int $actorid): void {
        try {
            $who = self::RECIPIENT[$event] ?? null;
            if ($who === null) {
                return;
            }
            if ($who === 'other') {
                $toid = $actorid === (int) $lesson['studentid'] ? (int) $lesson['teacherid'] : (int) $lesson['studentid'];
            } else {
                $toid = (int) $lesson[$who . 'id'];
            }
            $to = \core_user::get_user($toid);
            if (!$to || $to->deleted || $to->suspended || $toid === $actorid) {
                return;
            }
            $isteacher = $toid === (int) $lesson['teacherid'];
            $url = $isteacher
                ? new \moodle_url('/local/nit_lessons/my_lessons.php')
                : new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'lessons']);
            $a = (object) [
                'subject' => $lesson['subject'],
                'teacher' => $lesson['teacher_name'] ?? '',
                'student' => $lesson['student_name'] ?? '',
                'time' => userdate((int) $lesson['effective_time'], get_string('strftimedaydatetime', 'langconfig'),
                    \core_date::get_user_timezone($to)),
            ];

            $message = new \core\message\message();
            $message->component = 'local_nit_lessons';
            $message->name = 'lessonupdate';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $to;
            $message->subject = get_string('notif_' . $event, 'local_nit_lessons', $a);
            $message->fullmessage = $message->subject . "\n" . get_string('notif_details', 'local_nit_lessons', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string($isteacher ? 'mylessons' : 'studenthub', 'local_nit_lessons');
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_lessons: notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
