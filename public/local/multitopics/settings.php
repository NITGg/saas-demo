<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Mobile-app settings served by getsettings.php.
 * Site administration → Plugins → Local plugins → Multitopics course content API.
 */

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_multitopics_settings',
        get_string('mobilesettings', 'local_multitopics'));

    $settings->add(new admin_setting_heading('local_multitopics/mobileheading',
        get_string('mobilesettings', 'local_multitopics'),
        get_string('mobilesettings_desc', 'local_multitopics')));

    // ── Tokens + OAuth ───────────────────────────────────────────────────────
    $settings->add(new admin_setting_configpasswordunmask('local_multitopics/user_token',
        get_string('user_token', 'local_multitopics'),
        get_string('user_token_desc', 'local_multitopics'), ''));
    $settings->add(new admin_setting_configpasswordunmask('local_multitopics/admin_token',
        get_string('admin_token', 'local_multitopics'),
        get_string('admin_token_desc', 'local_multitopics'), ''));
    $settings->add(new admin_setting_configtext('local_multitopics/google_client_id',
        get_string('google_client_id', 'local_multitopics'),
        get_string('google_client_id_desc', 'local_multitopics'), '', PARAM_RAW_TRIMMED));

    // ── Media / DRM ──────────────────────────────────────────────────────────
    $settings->add(new admin_setting_configcheckbox('local_multitopics/prevent_screen_recording',
        get_string('prevent_screen_recording', 'local_multitopics'),
        get_string('prevent_screen_recording_desc', 'local_multitopics'), 1));
    $settings->add(new admin_setting_configcheckbox('local_multitopics/watermark',
        get_string('watermark', 'local_multitopics'),
        get_string('watermark_desc', 'local_multitopics'), 0));
    $settings->add(new admin_setting_configtext('local_multitopics/watermark_text',
        get_string('watermark_text', 'local_multitopics'), '', '{fullname}', PARAM_TEXT));
    // Overlay style — served to the app. Colour is 6 hex digits with NO leading '#'.
    $settings->add(new admin_setting_configtext('local_multitopics/watermark_color',
        get_string('watermark_color', 'local_multitopics'),
        get_string('watermark_color_desc', 'local_multitopics'), 'ffffff', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_multitopics/watermark_speed',
        get_string('watermark_speed', 'local_multitopics'),
        get_string('watermark_speed_desc', 'local_multitopics'), '0.002', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_multitopics/watermark_fontsize',
        get_string('watermark_fontsize', 'local_multitopics'),
        get_string('watermark_fontsize_desc', 'local_multitopics'), '14', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_multitopics/videourl',
        get_string('videourl', 'local_multitopics'), '', '', PARAM_URL));

    // ── Contact ──────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_configtext('local_multitopics/whatsapp_phone',
        get_string('whatsapp_phone', 'local_multitopics'), '', '', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_multitopics/whatsapp_message',
        get_string('whatsapp_message', 'local_multitopics'), '', '', PARAM_TEXT));

    // ── Payments ─────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_configcheckbox('local_multitopics/allow_paymob',
        get_string('allow_paymob', 'local_multitopics'), '', 0));

    // ── Force-update gate ────────────────────────────────────────────────────
    $settings->add(new admin_setting_configtext('local_multitopics/android_version',
        get_string('android_version', 'local_multitopics'), '', '', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_multitopics/android_url',
        get_string('android_url', 'local_multitopics'), '', '', PARAM_URL));
    $settings->add(new admin_setting_configtext('local_multitopics/ios_version',
        get_string('ios_version', 'local_multitopics'), '', '', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_multitopics/ios_url',
        get_string('ios_url', 'local_multitopics'), '', '', PARAM_URL));

    // ── Social login (fallback — licence-driven when local_license present) ───
    $settings->add(new admin_setting_configcheckbox('local_multitopics/google_login',
        get_string('google_login', 'local_multitopics'),
        get_string('social_login_desc', 'local_multitopics'), 1));
    $settings->add(new admin_setting_configcheckbox('local_multitopics/apple_login',
        get_string('apple_login', 'local_multitopics'), '', 0));
    $settings->add(new admin_setting_configcheckbox('local_multitopics/facebook_login',
        get_string('facebook_login', 'local_multitopics'), '', 0));

    // ── Networking ───────────────────────────────────────────────────────────
    $settings->add(new admin_setting_configtext('local_multitopics/server_timeout_duration',
        get_string('server_timeout_duration', 'local_multitopics'), '', '30', PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}
