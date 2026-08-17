<?php
defined('MOODLE_INTERNAL') || die();

function local_payments_extend_navigation(global_navigation $navigation) {
    // Navigation hooks will be added as needed.
}

function local_payments_extend_navigation_course(\navigation_node $navigation, \stdClass $course, \context_course $context) {
    if (has_capability('local/payments:managecoursepricing', $context)) {
        $url = new \moodle_url('/local/payments/course_pricing.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursepricing', 'local_payments'),
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            'local_payments_pricing',
            new \pix_icon('i/payment', '')
        );
    }
}

/**
 * Add a "Payment history" link to the user's own profile page.
 *
 * This is the student-facing entry point to /local/payments/history.php —
 * it appears under the "Miscellaneous" category on the profile page for the
 * logged-in user (and to admins viewing other users).
 */
function local_payments_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;

    if (!$iscurrentuser && !is_siteadmin()) {
        return;
    }

    $url = new \moodle_url('/local/payments/history.php');
    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'local_payments_history',
        get_string('paymenthistory', 'local_payments'),
        null,
        $url
    );
    $tree->add_node($node);
}

// The course-view payment gate previously implemented here as
// local_payments_before_http_headers() is now a hook callback
// (\local_payments\hook_callbacks::before_http_headers) registered in db/hooks.php,
// as required by the Moodle 4.4+ Hooks API.
