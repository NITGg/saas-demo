<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_license_settings',
        get_string('pluginname', 'local_license'));

    // Master switch — OFF by default so an existing academy is untouched.
    $settings->add(new admin_setting_configcheckbox(
        'local_license/enabled',
        get_string('enabled', 'local_license'),
        get_string('enabled_desc', 'local_license'),
        0
    ));

    // Tier.
    $tiers = [];
    foreach (\local_license\license::TIERS as $key => $def) {
        $tiers[$key] = $def['name'];
    }
    $settings->add(new admin_setting_configselect(
        'local_license/tier',
        get_string('tier', 'local_license'),
        get_string('tier_desc', 'local_license'),
        'demo',
        $tiers
    ));

    // Expiry date (YYYY-MM-DD). Empty = never expires.
    $settings->add(new admin_setting_configtext(
        'local_license/expirydate',
        get_string('expirydate', 'local_license'),
        get_string('expirydate_desc', 'local_license'),
        '',
        PARAM_RAW_TRIMMED
    ));

    // Grace period (days) after expiry before the academy locks.
    $settings->add(new admin_setting_configtext(
        'local_license/gracedays',
        get_string('gracedays', 'local_license'),
        get_string('gracedays_desc', 'local_license'),
        '0',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);

    // Live usage vs. limits.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_license_status',
        get_string('statuspage', 'local_license'),
        new moodle_url('/local/license/status.php')
    ));
}
