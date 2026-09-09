<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add a "My plan" entry to the flat navigation / nav drawer, so the academy
 * owner (and NIT admin) can reach their licence + subscription status page
 * without knowing the URL. Shown only to users who hold the platform-manage
 * capability — students and teachers never see it.
 *
 * Mirrors the convention used by local_nit_subscriptions_extend_navigation.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_license_extend_navigation(global_navigation $navigation): void {
    $context = context_system::instance();
    if (!has_capability('local/academy:manageplatform', $context)) {
        return;
    }
    $node = $navigation->add(
        get_string('myplan_nav', 'local_license'),
        new moodle_url('/local/license/my.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_license_myplan',
        new pix_icon('i/settings', '')
    );
    // Boost's nav drawer / flat navigation only renders nodes flagged for it.
    $node->showinflatnavigation = true;
}
