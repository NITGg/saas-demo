<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. See <http://www.gnu.org/licenses/>.

/**
 * Hook callbacks for local_academy.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Study system → Division linking on Moodle's course and user forms.
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_academy\local\linked_selects::class, 'before_footer'],
    ],
    [
        // course/view.php → the course details page for learners and visitors.
        'hook' => \core\hook\after_config::class,
        'callback' => [\local_academy\local\course_route::class, 'after_config'],
    ],
    [
        // login/signup.php (core sign-up, open with email self-registration) → our form.
        'hook' => \core\hook\after_config::class,
        'callback' => [\local_academy\local\registration::class, 'after_config'],
    ],
    [
        // login/forgot_password.php: the same quick answer for every email; the email goes out in the background.
        'hook' => \core\hook\after_config::class,
        'callback' => [\local_academy\local\forgot_password::class, 'after_config'],
    ],
    [
        // A student's own phone stays valid and unique on every page that saves the account.
        'hook' => \core_user\hook\before_user_updated::class,
        'callback' => [\local_academy\local\student_rules::class, 'before_user_updated'],
    ],
];
