<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_academy.
 */
$observers = [
    // There is no core "user confirmed" event (auth_email just sets confirmed=1),
    // so we use the user's first successful login as the "just confirmed" signal
    // and send the welcome message once.
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_academy\observer::user_loggedin',
    ],
    // New courses default to a TOMORROW start date (core), which hides their
    // content until then — reset it to today so the course is usable immediately.
    [
        'eventname' => '\core\event\course_created',
        'callback'  => '\local_academy\observer::course_created',
    ],
];
