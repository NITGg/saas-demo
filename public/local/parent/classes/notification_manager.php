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

namespace local_parent;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends notifications to linked parents about student activity (quizzes, grades, courses).
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_manager {

    /**
     * Dispatch notification to all linked parents of a student.
     *
     * @param int $studentid
     * @param string $subject
     * @param string $body
     * @param string $smallmessage
     * @param \moodle_url|null $contexturl
     */
    public static function notify_parents(
        int $studentid,
        string $subject,
        string $body,
        string $smallmessage = '',
        ?\moodle_url $contexturl = null
    ): void {
        global $DB;

        if ($studentid <= 0) {
            return;
        }

        $parentids = link_manager::list_parents($studentid);
        if (empty($parentids)) {
            return;
        }

        if ($contexturl === null) {
            $contexturl = new \moodle_url('/local/parent/index.php', ['child' => $studentid]);
        }

        $noreply = \core_user::get_noreply_user();

        foreach ($parentids as $pid) {
            $parent = $DB->get_record('user', ['id' => $pid], '*', IGNORE_MISSING);
            if (!$parent || $parent->deleted || $parent->suspended) {
                continue;
            }

            $message = new \core\message\message();
            $message->component         = 'local_parent';
            $message->name              = 'child_activity';
            $message->userfrom          = $noreply;
            $message->userto            = $parent;
            $message->subject           = $subject;
            $message->fullmessage       = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = '<p>' . nl2br(s($body)) . '</p>';
            $message->smallmessage      = $smallmessage !== '' ? $smallmessage : $subject;
            $message->notification      = 1;
            $message->contexturl        = $contexturl->out(false);
            $message->contexturlname    = get_string('dashboard', 'local_parent');

            message_send($message);
        }
    }

    /**
     * Handle quiz attempt submission event.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        $studentid = (int) $event->relateduserid;
        if ($studentid <= 0) {
            return;
        }

        $parents = link_manager::list_parents($studentid);
        if (empty($parents)) {
            return;
        }

        $student = $DB->get_record('user', ['id' => $studentid], 'id, firstname, lastname', IGNORE_MISSING);
        if (!$student) {
            return;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], '*', IGNORE_MISSING);
        $quizid = $event->other['quizid'] ?? ($attempt ? $attempt->quiz : 0);
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, course, sumgrades', IGNORE_MISSING);
        $course = $quiz ? $DB->get_record('course', ['id' => $quiz->course], 'id, fullname', IGNORE_MISSING) : null;

        $childname  = fullname($student);
        $quizname   = $quiz ? format_string($quiz->name) : get_string('col_quiz', 'local_parent');
        $coursename = $course ? format_string($course->fullname) : '';

        $scoreinfo = '—';
        if ($attempt && $attempt->sumgrades !== null && $quiz && $quiz->sumgrades !== null) {
            $scoreinfo = format_float($attempt->sumgrades, 2) . ' / ' . format_float($quiz->sumgrades, 2);
        }

        $url = new \moodle_url('/local/parent/index.php', ['child' => $studentid]);

        $data = (object) [
            'child'  => $childname,
            'quiz'   => $quizname,
            'course' => $coursename,
            'score'  => $scoreinfo,
            'url'    => $url->out(false),
        ];

        $subject = get_string('notif_quiz_subject', 'local_parent', $data);
        $body    = get_string('notif_quiz_body', 'local_parent', $data);

        self::notify_parents($studentid, $subject, $body, $subject, $url);
    }

    /**
     * Handle user graded event.
     *
     * @param \core\event\user_graded $event
     */
    public static function user_graded(\core\event\user_graded $event): void {
        global $DB;

        $studentid = (int) $event->relateduserid;
        if ($studentid <= 0) {
            return;
        }

        $parents = link_manager::list_parents($studentid);
        if (empty($parents)) {
            return;
        }

        $finalgrade = $event->other['finalgrade'] ?? null;
        if ($finalgrade === null) {
            return;
        }

        $itemid = $event->other['itemid'] ?? 0;
        $gradeitem = $DB->get_record('grade_items', ['id' => $itemid], '*', IGNORE_MISSING);
        if (!$gradeitem) {
            return;
        }

        // Avoid duplicate notification for quizzes (handled by quiz_attempt_submitted).
        if ($gradeitem->itemmodule === 'quiz') {
            return;
        }

        $student = $DB->get_record('user', ['id' => $studentid], 'id, firstname, lastname', IGNORE_MISSING);
        if (!$student) {
            return;
        }

        $childname  = fullname($student);
        $itemname   = $gradeitem->itemname ? format_string($gradeitem->itemname) : get_string('col_grade', 'local_parent');
        $course     = $DB->get_record('course', ['id' => $gradeitem->courseid], 'id, fullname', IGNORE_MISSING);
        $coursename = $course ? format_string($course->fullname) : '';

        $gradestr = format_float((float) $finalgrade, 2);
        if ($gradeitem->grademax > 0) {
            $gradestr .= ' / ' . format_float($gradeitem->grademax, 2);
        }

        $url = new \moodle_url('/local/parent/index.php', ['child' => $studentid]);

        $data = (object) [
            'child'  => $childname,
            'item'   => $itemname,
            'course' => $coursename,
            'grade'  => $gradestr,
            'url'    => $url->out(false),
        ];

        $subject = get_string('notif_grade_subject', 'local_parent', $data);
        $body    = get_string('notif_grade_body', 'local_parent', $data);

        self::notify_parents($studentid, $subject, $body, $subject, $url);
    }

    /**
     * Handle course completion event.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;

        $studentid = (int) $event->relateduserid;
        if ($studentid <= 0) {
            return;
        }

        $parents = link_manager::list_parents($studentid);
        if (empty($parents)) {
            return;
        }

        $student = $DB->get_record('user', ['id' => $studentid], 'id, firstname, lastname', IGNORE_MISSING);
        $course  = $DB->get_record('course', ['id' => $event->courseid], 'id, fullname', IGNORE_MISSING);
        if (!$student || !$course) {
            return;
        }

        $childname  = fullname($student);
        $coursename = format_string($course->fullname);
        $url = new \moodle_url('/local/parent/index.php', ['child' => $studentid]);

        $data = (object) [
            'child'  => $childname,
            'course' => $coursename,
            'url'    => $url->out(false),
        ];

        $subject = get_string('notif_course_completed_subject', 'local_parent', $data);
        $body    = get_string('notif_course_completed_body', 'local_parent', $data);

        self::notify_parents($studentid, $subject, $body, $subject, $url);
    }

    /**
     * Handle user enrolment created event.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        global $DB;

        $studentid = (int) $event->relateduserid;
        if ($studentid <= 0) {
            return;
        }

        $parents = link_manager::list_parents($studentid);
        if (empty($parents)) {
            return;
        }

        $student = $DB->get_record('user', ['id' => $studentid], 'id, firstname, lastname', IGNORE_MISSING);
        $course  = $DB->get_record('course', ['id' => $event->courseid], 'id, fullname', IGNORE_MISSING);
        if (!$student || !$course) {
            return;
        }

        $childname  = fullname($student);
        $coursename = format_string($course->fullname);
        $url = new \moodle_url('/local/parent/index.php', ['child' => $studentid]);

        $data = (object) [
            'child'  => $childname,
            'course' => $coursename,
            'url'    => $url->out(false),
        ];

        $subject = get_string('notif_course_enrolled_subject', 'local_parent', $data);
        $body    = get_string('notif_course_enrolled_body', 'local_parent', $data);

        self::notify_parents($studentid, $subject, $body, $subject, $url);
    }
}
