<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_academy.
 */
class observer {

    /**
     * Send a one-time welcome message the first time an email-signup user logs
     * in (i.e. right after they confirm their email — Moodle fires no dedicated
     * "user confirmed" event, so first login is the reliable signal).
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $event->objectid]);
        if (!$user || !empty($user->deleted) || isguestuser($user)) {
            return;
        }

        // Only for users who signed up with email/password.
        if ($user->auth !== 'email') {
            return;
        }

        // Send it once, ever.
        if (get_user_preferences('local_academy_welcomed', 0, $user)) {
            return;
        }

        self::send_welcome($user);
        set_user_preference('local_academy_welcomed', 1, $user);
    }

    /**
     * Make a newly-created course available IMMEDIATELY. Core defaults a new
     * course's start date to TOMORROW (course/edit_form.php), which gates its
     * content (and the course's own visibility, via show_started_courses_task)
     * until then. Academies enrol learners now, so a just-created course starts
     * today. Only touches CREATE — a later edit can still schedule a future date.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event): void {
        global $DB;
        $courseid = (int) $event->objectid;
        if ($courseid <= (int) SITEID) {
            return;
        }
        $startdate = (int) $DB->get_field('course', 'startdate', ['id' => $courseid]);
        $now = time();
        if ($startdate > $now) {
            // Today at 00:00 — <= now, so content is visible right away.
            $DB->set_field('course', 'startdate', usergetmidnight($now), ['id' => $courseid]);
            rebuild_course_cache($courseid, true);
        }
    }

    /** Build and send the welcome notification (in-app + email). */
    private static function send_welcome(\stdClass $user): void {
        $sitename = format_string(get_site()->fullname);

        $body = get_string('welcome_body', 'local_academy', [
            'name' => fullname($user),
            'site' => $sitename,
        ]);

        $message = new \core\message\message();
        $message->component         = 'local_academy';
        $message->name              = 'welcome';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user;
        $message->subject           = get_string('welcome_subject', 'local_academy', $sitename);
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = '<p>' . nl2br(s($body)) . '</p>';
        $message->smallmessage      = get_string('welcome_small', 'local_academy', $sitename);
        $message->notification      = 1;
        $message->contexturl        = (new \moodle_url('/'))->out(false);
        $message->contexturlname    = $sitename;

        message_send($message);
    }
}
