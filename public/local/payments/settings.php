<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_payments_category',
        get_string('pluginname', 'local_payments')));

    // General settings.
    $settings = new admin_settingpage('local_payments_settings',
        get_string('generalsettings', 'local_payments'));

    $settings->add(new admin_setting_configtext(
        'local_payments/default_country',
        get_string('default_country', 'local_payments'),
        get_string('default_country_desc', 'local_payments'),
        'EG',
        PARAM_ALPHA
    ));

    $settings->add(new admin_setting_configtext(
        'local_payments/payment_ttl',
        get_string('payment_ttl', 'local_payments'),
        get_string('payment_ttl_desc', 'local_payments'),
        '1800',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_payments/default_currency',
        get_string('default_currency', 'local_payments'),
        get_string('default_currency_desc', 'local_payments'),
        'USD',
        PARAM_ALPHA
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_payments/show_sale_badge',
        get_string('show_sale_badge', 'local_payments'),
        get_string('show_sale_badge_desc', 'local_payments'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_payments/auto_expire_enabled',
        get_string('auto_expire_enabled', 'local_payments'),
        get_string('auto_expire_enabled_desc', 'local_payments'),
        1
    ));

    $ADMIN->add('local_payments_category', $settings);

    // Provider management link.
    $ADMIN->add('local_payments_category', new admin_externalpage(
        'local_payments_providers',
        get_string('manageproviders', 'local_payments'),
        new moodle_url('/local/payments/admin/providers.php')
    ));

    // Reports link.
    $ADMIN->add('local_payments_category', new admin_externalpage(
        'local_payments_reports',
        get_string('reports', 'local_payments'),
        new moodle_url('/local/payments/report.php')
    ));

    // Payment provider sub-plugins: Moodle's admin/settings/plugins.php has no
    // built-in handling for our custom "paymentprovider" sub-plugin type, so we
    // must discover and load each provider's settings.php ourselves (same
    // pattern core uses for cachestore plugins in admin/settings/plugins.php).
    $ADMIN->add('local_payments_category', new admin_category('local_payments_providers_settings',
        get_string('providersettings', 'local_payments')));

    foreach (core_component::get_plugin_list('paymentprovider') as $providername => $providerdir) {
        $settingspath = $providerdir . '/settings.php';
        if (file_exists($settingspath)) {
            include($settingspath);
            if (isset($settings)) {
                $ADMIN->add('local_payments_providers_settings', $settings);
                unset($settings);
            }
        }
    }
}
