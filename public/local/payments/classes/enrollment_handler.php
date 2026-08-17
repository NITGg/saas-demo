<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class enrollment_handler {

    /**
     * Enrol a user into a course using manual enrolment.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $roleid Role to assign. Default: student (5).
     * @return bool
     */
    public static function enrol_user(int $userid, int $courseid, int $roleid = 5): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $enrol = enrol_get_plugin('manual');
        if (!$enrol) {
            throw new \moodle_exception('enrolpluginnotinstalled', 'local_payments', '', 'manual');
        }

        // Find the manual enrolment instance for this course.
        $instances = enrol_get_instances($courseid, true);
        $manual_instance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manual_instance = $instance;
                break;
            }
        }

        // Create manual instance if it doesn't exist.
        if (!$manual_instance) {
            $enrolid = $enrol->add_instance($course);
            $manual_instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        }

        // Check if already actively enrolled. An expired/suspended enrolment
        // must fall through so we re-enrol (manual enrol_user updates the
        // existing record's timestart/timeend), restoring access.
        if (is_enrolled(\context_course::instance($courseid), $userid, '', true)) {
            return true;
        }

        $enrol->enrol_user($manual_instance, $userid, $roleid, time(), 0);

        return is_enrolled(\context_course::instance($courseid), $userid, '', true);
    }

    /**
     * Check if a user has an *active* enrolment in a course.
     *
     * Uses $onlyactive = true so that expired enrolments (past their timeend,
     * e.g. a lapsed subscription/package) or suspended ones are treated as
     * not-enrolled — matching what actual course access allows. Otherwise the
     * UI would keep showing "Enrolled" while the course itself denies entry.
     */
    public static function is_enrolled(int $userid, int $courseid): bool {
        return is_enrolled(\context_course::instance($courseid), $userid, '', true);
    }

    /**
     * Whether the user has an enrolment record in the course that is no longer
     * active — i.e. a lapsed subscription/package: they were enrolled once
     * (timeend has passed or the enrolment was suspended) but active access has
     * ended. Used to surface a "Renew your subscription" hint on course cards
     * that keep showing in "My courses" after access expired.
     */
    public static function has_expired_enrolment(int $userid, int $courseid): bool {
        $context = \context_course::instance($courseid);
        // Enrolled at all (incl. expired/suspended) but not actively.
        return is_enrolled($context, $userid, '', false)
            && !is_enrolled($context, $userid, '', true);
    }

    /**
     * Unenrol a user from a course (for refund scenarios).
     */
    public static function unenrol_user(int $userid, int $courseid): bool {
        $enrol = enrol_get_plugin('manual');
        if (!$enrol) {
            return false;
        }

        $instances = enrol_get_instances($courseid, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $enrol->unenrol_user($instance, $userid);
                return true;
            }
        }
        return false;
    }
}
