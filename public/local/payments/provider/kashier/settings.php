<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('paymentprovider_kashier', get_string('pluginname', 'paymentprovider_kashier'));

    // ── Payment mode ─────────────────────────────────────────────────────────
    // The single source of truth for this academy: LIVE (real) or TEST (sandbox).
    // New academies are seeded from the platform default at provisioning; after that
    // NIT (from the platform dashboard) or the owner (here) can change it, and NIT's
    // dashboard writes THIS same field.
    $settings->add(new admin_setting_configselect(
        'paymentprovider_kashier/payment_mode',
        get_string('payment_mode', 'paymentprovider_kashier'),
        get_string('payment_mode_desc', 'paymentprovider_kashier'),
        'test',
        [
            'test' => get_string('mode_test', 'paymentprovider_kashier'),
            'live' => get_string('mode_live', 'paymentprovider_kashier'),
        ]
    ));

    // ── LIVE credentials ─────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('paymentprovider_kashier/heading_live',
        get_string('heading_live', 'paymentprovider_kashier'), ''));
    $settings->add(new admin_setting_configtext('paymentprovider_kashier/live_merchant_id',
        get_string('merchant_id', 'paymentprovider_kashier'), '', '', PARAM_TEXT));
    $settings->add(new admin_setting_configpasswordunmask('paymentprovider_kashier/live_api_key',
        get_string('api_key', 'paymentprovider_kashier'), '', ''));
    $settings->add(new admin_setting_configpasswordunmask('paymentprovider_kashier/live_secret_key',
        get_string('secret_key', 'paymentprovider_kashier'), '', ''));
    $settings->add(new admin_setting_configtext('paymentprovider_kashier/live_base_url',
        get_string('base_url', 'paymentprovider_kashier'), '', 'https://api.kashier.io', PARAM_URL));
    $settings->add(new admin_setting_configtext('paymentprovider_kashier/live_fep_url',
        get_string('fep_url', 'paymentprovider_kashier'), get_string('fep_url_desc', 'paymentprovider_kashier'),
        'https://fep.kashier.io', PARAM_URL));

    // ── TEST / sandbox credentials ───────────────────────────────────────────
    $settings->add(new admin_setting_heading('paymentprovider_kashier/heading_test',
        get_string('heading_test', 'paymentprovider_kashier'), ''));
    $settings->add(new admin_setting_configtext('paymentprovider_kashier/test_merchant_id',
        get_string('merchant_id', 'paymentprovider_kashier'), '', '', PARAM_TEXT));
    $settings->add(new admin_setting_configpasswordunmask('paymentprovider_kashier/test_api_key',
        get_string('api_key', 'paymentprovider_kashier'), '', ''));
    $settings->add(new admin_setting_configpasswordunmask('paymentprovider_kashier/test_secret_key',
        get_string('secret_key', 'paymentprovider_kashier'), '', ''));
    $settings->add(new admin_setting_configtext('paymentprovider_kashier/test_base_url',
        get_string('base_url', 'paymentprovider_kashier'), '', 'https://test-api.kashier.io', PARAM_URL));
    $settings->add(new admin_setting_configtext('paymentprovider_kashier/test_fep_url',
        get_string('fep_url', 'paymentprovider_kashier'), get_string('fep_url_desc', 'paymentprovider_kashier'),
        'https://test-fep.kashier.io', PARAM_URL));

    // ── Other ────────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('paymentprovider_kashier/heading_other', '', ''));
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/allowed_methods',
        get_string('allowed_methods', 'paymentprovider_kashier'),
        get_string('allowed_methods_desc', 'paymentprovider_kashier'),
        'card,wallet',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_kashier/enable_3ds',
        get_string('enable_3ds', 'paymentprovider_kashier'),
        get_string('enable_3ds_desc', 'paymentprovider_kashier'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/max_failure_attempts',
        get_string('max_failure_attempts', 'paymentprovider_kashier'),
        get_string('max_failure_attempts_desc', 'paymentprovider_kashier'),
        '3',
        PARAM_INT
    ));
}
