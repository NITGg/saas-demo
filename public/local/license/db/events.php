<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for licence enforcement.
 *
 * The teacher cap can't be enforced from the before_http_headers hook (teachers
 * are added through AJAX enrol/assign flows the hook deliberately skips), so we
 * watch the role_assigned event instead and revert any assignment that pushes a
 * new distinct editing-teacher past the tier's limit.
 */
$observers = [
    [
        'eventname' => '\core\event\role_assigned',
        'callback'  => '\local_license\local\observers::role_assigned',
        'internal'  => false, // run after the DB transaction commits, so the row exists to count / unassign.
    ],
    [
        // Course cap backstop: the before_http_headers guard only catches the
        // /course/edit.php page load — course restore, CSV upload and web-service
        // creation all bypass it. Watch course_created and delete any course that
        // pushes past the tier's maxcourses.
        'eventname' => '\core\event\course_created',
        'callback'  => '\local_license\local\observers::course_created',
        'internal'  => false, // after commit, so the course row exists to count / delete.
    ],
];
