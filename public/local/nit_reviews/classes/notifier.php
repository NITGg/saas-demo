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

namespace local_nit_reviews;

/**
 * Review notifications (popup + app push):
 *  - review_pending: tells the moderators of the review's course that a comment
 *    waits for approval;
 *  - review_moderated: tells the author their review was approved or rejected.
 *
 * Each message is written in the recipient's language. A failure to notify never
 * breaks the save or the moderation that triggered it.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {

    /** Most moderators told about one review. */
    private const MAX_MODERATORS = 50;

    /**
     * Tell the course's moderators (and the site admins) about a pending review.
     *
     * @param \stdClass $review
     */
    public static function review_pending(\stdClass $review): void {
        $context = api::review_context($review);
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $recipients = get_users_by_capability($context, 'local/nit_reviews:moderate',
            "u.id, u.lang, u.email, u.auth, u.suspended, u.deleted, u.emailstop, u.mailformat, $fields",
            'u.id', '', self::MAX_MODERATORS);
        foreach (get_admins() as $admin) {
            $recipients[(int) $admin->id] = $admin;
        }
        unset($recipients[(int) $review->userid]);

        $url = new \moodle_url('/local/nit_reviews/moderate.php', ['status' => api::STATUS_PENDING]);
        $a = self::target($review);
        foreach (array_slice($recipients, 0, self::MAX_MODERATORS, true) as $user) {
            $lang = $user->lang ?? '';
            self::send('reviewpending', $user, (int) $review->courseid,
                (new \lang_string('msg_pending_subject', 'local_nit_reviews', $a))->out($lang),
                (new \lang_string('msg_pending_body', 'local_nit_reviews', $a))->out($lang),
                $url, (new \lang_string('moderatereviews', 'local_nit_reviews'))->out($lang));
        }
    }

    /**
     * Tell the author their review was approved or rejected (with the reason).
     *
     * @param \stdClass $review the moderated record
     */
    public static function review_moderated(\stdClass $review): void {
        $user = \core_user::get_user((int) $review->userid);
        if (!$user || $user->deleted) {
            return;
        }
        $lang = $user->lang ?? '';
        $a = self::target($review);
        $a->reason = trim((string) $review->rejectreason);
        $approved = (int) $review->status === api::STATUS_APPROVED;
        $key = $approved ? 'msg_approved' : ($a->reason !== '' ? 'msg_rejected_reason' : 'msg_rejected');
        if ((int) $review->teacherid) {
            $url = new \moodle_url('/local/academy/teacher.php', ['id' => $review->teacherid]);
        } else {
            $url = new \moodle_url('/course/view.php', ['id' => $review->courseid]);
        }
        self::send('reviewmoderated', $user, (int) $review->courseid,
            (new \lang_string($key . '_subject', 'local_nit_reviews', $a))->out($lang),
            (new \lang_string($key . '_body', 'local_nit_reviews', $a))->out($lang),
            $url, $a->target);
    }

    /**
     * What the review is about, for the message strings: {target, course, teacher, author, rating}.
     *
     * @param \stdClass $review
     * @return \stdClass
     */
    private static function target(\stdClass $review): \stdClass {
        global $DB;
        $course = (int) $review->courseid > 1 ? $DB->get_field('course', 'fullname', ['id' => $review->courseid]) : '';
        $course = $course ? format_string($course, true, ['context' => api::review_context($review)]) : '';
        $teacher = (int) $review->teacherid ? \core_user::get_user((int) $review->teacherid) : null;
        $author = \core_user::get_user((int) $review->userid);
        $a = (object) [
            'course' => $course,
            'teacher' => $teacher ? fullname($teacher) : '',
            'author' => $author ? fullname($author) : '',
            'rating' => (int) $review->rating,
        ];
        $a->target = $a->teacher !== '' ? $a->teacher : $a->course;
        return $a;
    }

    /**
     * Send one notification, swallowing delivery errors.
     *
     * @param string $name provider
     * @param \stdClass $to
     * @param int $courseid
     * @param string $subject
     * @param string $body
     * @param \moodle_url $url
     * @param string $urlname
     */
    private static function send(string $name, \stdClass $to, int $courseid, string $subject, string $body,
            \moodle_url $url, string $urlname): void {
        $message = new \core\message\message();
        $message->component = 'local_nit_reviews';
        $message->name = $name;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $to;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $urlname;
        $message->courseid = $courseid > 1 ? $courseid : SITEID;
        try {
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_reviews: notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
