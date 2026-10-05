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

namespace local_nit_lessons\api;

use local_nit_lessons\local\notifier;
use local_nit_lessons\service\lesson_service;

/**
 * Public facade for the lesson lifecycle.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class lessons {
    /** Past tense of a respond action, for the notification event. */
    const PAST = ['accept' => 'accepted', 'reject' => 'rejected', 'suggest' => 'suggested'];

    /**
     * Notify the other participant, then hand the lesson back.
     *
     * @param string $event
     * @param array $lesson
     * @param int $actorid
     * @return array the lesson
     */
    private static function notify(string $event, array $lesson, int $actorid): array {
        notifier::send($event, $lesson, $actorid);
        return $lesson;
    }

    /**
     * Student requests a lesson (US-LS-1-1).
     *
     * @param int $studentid
     * @param int $teacherid
     * @param string $subject
     * @param int $when unix start time
     * @param string $note
     * @return array
     */
    public static function request(int $studentid, int $teacherid, string $subject, int $when,
            string $note): array {
        return self::notify('requested',
            (new lesson_service())->request_lesson($studentid, $teacherid, $subject, $when, $note), $studentid);
    }

    /**
     * Teacher accepts / rejects / suggests (US-LS-2-1 / 2-3).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string $action
     * @param array $opts
     * @return array
     */
    public static function teacher_respond(int $teacherid, int $lessonid, string $action, array $opts = []): array {
        return self::notify((self::PAST[$action] ?? $action) . '_by_teacher',
            (new lesson_service())->teacher_respond($teacherid, $lessonid, $action, $opts), $teacherid);
    }

    /**
     * Student accepts / rejects / suggests (US-LS-2-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $action
     * @param array $opts
     * @return array
     */
    public static function student_respond(int $studentid, int $lessonid, string $action, array $opts = []): array {
        return self::notify((self::PAST[$action] ?? $action) . '_by_student',
            (new lesson_service())->student_respond($studentid, $lessonid, $action, $opts), $studentid);
    }

    /**
     * Teacher starts a confirmed lesson (US-LS-3-1).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @return array
     */
    public static function start(int $teacherid, int $lessonid): array {
        return self::notify('started',
            (new lesson_service())->start_lesson($teacherid, $lessonid), $teacherid);
    }

    /**
     * Teacher completes a lesson (US-LS-3-2).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string|null $note
     * @return array
     */
    public static function complete(int $teacherid, int $lessonid, ?string $note = null): array {
        return self::notify('completed',
            (new lesson_service())->complete_lesson($teacherid, $lessonid, $note), $teacherid);
    }

    /**
     * Teacher reports the student absent (US-LS-3-3).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @return array
     */
    public static function report_student_absent(int $teacherid, int $lessonid): array {
        return self::notify('student_absent',
            (new lesson_service())->report_student_absent($teacherid, $lessonid), $teacherid);
    }

    /**
     * Student reports the teacher absent (US-LS-3-4).
     *
     * @param int $studentid
     * @param int $lessonid
     * @return array
     */
    public static function report_teacher_absent(int $studentid, int $lessonid): array {
        return self::notify('teacher_absent',
            (new lesson_service())->report_teacher_absent($studentid, $lessonid), $studentid);
    }

    /**
     * Student withdraws an un-confirmed request (US-ST-2-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function cancel_request(int $studentid, int $lessonid, string $reason = ''): array {
        return self::notify('request_withdrawn',
            (new lesson_service())->cancel_request_as_student($studentid, $lessonid, $reason), $studentid);
    }

    /**
     * Student cancels a confirmed lesson (US-LS-4-1).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function cancel_as_student(int $studentid, int $lessonid, string $reason = ''): array {
        return self::notify('cancelled_by_student',
            (new lesson_service())->cancel_as_student($studentid, $lessonid, $reason), $studentid);
    }

    /**
     * Teacher cancels a confirmed lesson (US-LS-4-2).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function cancel_as_teacher(int $teacherid, int $lessonid, string $reason = ''): array {
        return self::notify('cancelled_by_teacher',
            (new lesson_service())->cancel_as_teacher($teacherid, $lessonid, $reason), $teacherid);
    }

    /**
     * Request a time update on a confirmed lesson (US-LS-5-1).
     *
     * @param int $userid
     * @param int $lessonid
     * @param int $when
     * @return array
     */
    public static function request_time_update(int $userid, int $lessonid, int $when): array {
        return self::notify('reschedule_requested',
            (new lesson_service())->request_time_update($userid, $lessonid, $when), $userid);
    }

    /**
     * Respond to a time-update request (US-LS-5-2).
     *
     * @param int $userid
     * @param int $lessonid
     * @param string $action
     * @return array
     */
    public static function respond_time_update(int $userid, int $lessonid, string $action): array {
        return self::notify('reschedule_answered',
            (new lesson_service())->respond_time_update($userid, $lessonid, $action), $userid);
    }

    /**
     * Admin reverses a distributed Flex (US-FN-1-5).
     *
     * @param int $lessonid
     * @param int $adminid
     * @param string $reason
     * @return array
     */
    public static function reverse_flex(int $lessonid, int $adminid, string $reason): array {
        return (new lesson_service())->reverse_flex($lessonid, $adminid, $reason);
    }

    /**
     * Lessons for a user (US-TR-1-2 / US-ST-2-2).
     *
     * @param int $userid
     * @param string $role
     * @param string $status
     * @return array
     */
    public static function my_lessons(int $userid, string $role = '', string $status = ''): array {
        return (new lesson_service())->get_my_lessons($userid, $role, $status);
    }

    /**
     * A single lesson.
     *
     * @param int $userid
     * @param int $lessonid
     * @return array
     */
    public static function get(int $userid, int $lessonid): array {
        return (new lesson_service())->get_lesson($userid, $lessonid);
    }
}
