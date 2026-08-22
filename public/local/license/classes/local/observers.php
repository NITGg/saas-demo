<?php
namespace local_license\local;

use local_license\license;
use local_license\enforcer;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers that enforce licence limits which can't be caught by the
 * page-load hook (which skips AJAX / web-service requests).
 */
class observers {

    /**
     * Enforce the tier's teacher cap.
     *
     * Fires whenever any role is assigned. If the role is the editing-teacher
     * role and this assignment makes a brand-new distinct teacher that exceeds
     * {@see license::max_teachers()}, we immediately revert it — the academy's
     * admin has to upgrade (or free a seat) to add more teachers.
     *
     * Reverting a *new distinct* teacher (not merely any assignment) matches how
     * {@see enforcer::count_teachers()} counts: a user who already teaches one
     * course doesn't consume a second seat when added to another.
     *
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event): void {
        global $DB;

        if (!license::is_enforced()) {
            return;
        }
        $max = license::max_teachers();
        if ($max < 0) {
            return; // unlimited — nothing to enforce.
        }

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$teacherroleid || (int) $event->objectid !== (int) $teacherroleid) {
            return; // not a teacher-role assignment.
        }

        // Still within the cap after this assignment? Then it's fine.
        if (enforcer::count_teachers() <= $max) {
            return;
        }

        $userid = (int) $event->relateduserid;
        $thisraid = (int) ($event->other['id'] ?? 0);

        // Was this user ALREADY a teacher through some other assignment? If so this
        // one didn't add a new distinct teacher, so the cap wasn't really exceeded.
        $alreadyteacher = $DB->record_exists_select(
            'role_assignments',
            'roleid = :roleid AND userid = :userid AND id <> :thisid',
            ['roleid' => $teacherroleid, 'userid' => $userid, 'thisid' => $thisraid]
        );
        if ($alreadyteacher) {
            return;
        }

        // This assignment tipped a new teacher over the limit — revert just it.
        $context = \context::instance_by_id($event->contextid, IGNORE_MISSING);
        if (!$context) {
            return;
        }
        role_unassign($teacherroleid, $userid, $context->id);

        // Best-effort notice (shows on the next full page load; silently ignored on AJAX).
        try {
            \core\notification::error(get_string('limit_teacher', 'local_license', [
                'tier' => license::tiername(),
                'max'  => $max,
            ]));
        } catch (\Throwable $e) {
            // Notifications need a session; never let that break the enrol flow.
            debugging('local_license: teacher-cap notice skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
