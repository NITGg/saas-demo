<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_license.
 *
 * @package    local_license
 */

$callbacks = [
    [
        // Fires early (before headers) — used to lock an expired academy and to
        // block creating activities/courses past the tier limit.
        'hook'     => \core\hook\output\before_http_headers::class,
        'callback' => \local_license\local\hook_callbacks::class . '::before_http_headers',
    ],
];
