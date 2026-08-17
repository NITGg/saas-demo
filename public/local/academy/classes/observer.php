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
