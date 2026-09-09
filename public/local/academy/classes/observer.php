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
        // Core defaults a new course's start date to TOMORROW. Pull only that
        // default back to today (start <= now, so content is visible now) — but do
        // NOT touch a deliberately scheduled future date (more than ~2 days out),
        // so an academy can still launch a course later on purpose.
        if ($startdate > $now && $startdate <= $now + 2 * DAYSECS) {
            $DB->set_field('course', 'startdate', usergetmidnight($now), ['id' => $courseid]);
            rebuild_course_cache($courseid, true);
        }

        // Enrol the creator as an editing teacher so the course shows on THEIR
        // dashboard ("Course overview" is enrolment-based). Core auto-assigns the
        // creator role only for non-admin course creators; the academy owner is
        // admin-like, so Moodle skips it and their dashboard stays empty even
        // though they own every course. Enrol them explicitly here.
        self::enrol_course_creator($courseid, (int) $event->userid);

        // Purge the theme's front-page cache so the new course appears on the home
        // courses grid immediately. theme_nit_get_courses() caches its assembled
        // list for ~5 min keyed by user+country; without this a just-created course
        // is invisible on the Site home until that TTL expires.
        try {
            \cache_helper::purge_by_definition('theme_nit', 'frontpage');
        } catch (\Throwable $e) {
            // Theme not installed / cache undefined — nothing to purge.
        }
    }

    /**
     * Enrol $userid into $courseid as an editing teacher via the manual plugin
     * (creating a manual instance if the course has none). No-op for the guest /
     * system user, or when they are already enrolled.
     *
     * @param int $courseid
     * @param int $userid the course creator (event->userid)
     */
    private static function enrol_course_creator(int $courseid, int $userid): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }
        $context = \context_course::instance($courseid);
        // Already has a role / enrolment here? Leave it alone.
        if (is_enrolled($context, $userid)) {
            return;
        }
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$roleid) {
            return;
        }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return;
        }
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            $instanceid = $plugin->add_default_instance($course);
            if (!$instanceid) {
                $instanceid = $plugin->add_instance($course);
            }
            $instance = $instanceid ? $DB->get_record('enrol', ['id' => $instanceid]) : null;
        }
        if (!$instance) {
            return;
        }
        $plugin->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
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
