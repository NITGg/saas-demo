<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('paymentprovider_kashier', get_string('pluginname', 'paymentprovider_kashier'));

    // Sandbox mode.
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_kashier/sandbox_mode',
        get_string('sandbox_mode', 'paymentprovider_kashier'),
        get_string('sandbox_mode_desc', 'paymentprovider_kashier'),
        1
    ));

    // Merchant ID.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/merchant_id',
        get_string('merchant_id', 'paymentprovider_kashier'),
        get_string('merchant_id_desc', 'paymentprovider_kashier'),
        '',
        PARAM_TEXT
    ));

    // API Key (used for HMAC signature + header).
    $settings->add(new admin_setting_configpasswordunmask(
        'paymentprovider_kashier/api_key',
        get_string('api_key', 'paymentprovider_kashier'),
        get_string('api_key_desc', 'paymentprovider_kashier'),
        ''
    ));

    // Secret Key (Authorization header).
    $settings->add(new admin_setting_configpasswordunmask(
        'paymentprovider_kashier/secret_key',
        get_string('secret_key', 'paymentprovider_kashier'),
        get_string('secret_key_desc', 'paymentprovider_kashier'),
        ''
    ));

    // Base URL.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/base_url',
        get_string('base_url', 'paymentprovider_kashier'),
        get_string('base_url_desc', 'paymentprovider_kashier'),
        'https://api.kashier.io',
        PARAM_URL
    ));

    // Refund base URL (different host).
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/refund_base_url',
        get_string('refund_base_url', 'paymentprovider_kashier'),
        get_string('refund_base_url_desc', 'paymentprovider_kashier'),
        'https://fep.kashier.io',
        PARAM_URL
    ));

    // Allowed payment methods.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/allowed_methods',
        get_string('allowed_methods', 'paymentprovider_kashier'),
        get_string('allowed_methods_desc', 'paymentprovider_kashier'),
        'card,wallet',
        PARAM_TEXT
    ));

    // Enable 3D Secure.
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_kashier/enable_3ds',
        get_string('enable_3ds', 'paymentprovider_kashier'),
        get_string('enable_3ds_desc', 'paymentprovider_kashier'),
        1
    ));

    // Max failure attempts.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_kashier/max_failure_attempts',
        get_string('max_failure_attempts', 'paymentprovider_kashier'),
        get_string('max_failure_attempts_desc', 'paymentprovider_kashier'),
        '3',
        PARAM_INT
    ));
}
