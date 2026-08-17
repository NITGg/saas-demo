<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_vdocipher_settings',
        get_string('pluginname', 'local_vdocipher'));

    // ── Credentials ──────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_vdocipher/credsheading',
        get_string('credsheading', 'local_vdocipher'),
        get_string('credsheading_desc', 'local_vdocipher')
    ));

    // The VdoCipher API secret. Stored unmasked in config but never sent to any
    // client — only the server uses it to sign requests / mint OTPs.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_vdocipher/apisecret',
        get_string('apisecret', 'local_vdocipher'),
        get_string('apisecret_desc', 'local_vdocipher'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_vdocipher/apibase',
        get_string('apibase', 'local_vdocipher'),
        get_string('apibase_desc', 'local_vdocipher'),
        'https://dev.vdocipher.com/api',
        PARAM_URL
    ));

    // ── Playback / security ──────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_vdocipher/playbackheading',
        get_string('playbackheading', 'local_vdocipher'),
        get_string('playbackheading_desc', 'local_vdocipher')
    ));

    // How long a minted OTP is valid, in seconds. Kept short: the app fetches a
    // fresh OTP immediately before playback.
    $settings->add(new admin_setting_configtext(
        'local_vdocipher/otpttl',
        get_string('otpttl', 'local_vdocipher'),
        get_string('otpttl_desc', 'local_vdocipher'),
        '300',
        PARAM_INT
    ));

    // Watermark text template. Placeholders {fullname}, {email}, {userid} are
    // filled server-side with the *viewer's* details at OTP time, so the overlay
    // can't be forged or stripped by the client.
    $settings->add(new admin_setting_configtext(
        'local_vdocipher/watermarktext',
        get_string('watermarktext', 'local_vdocipher'),
        get_string('watermarktext_desc', 'local_vdocipher'),
        '{fullname} · {email}',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_vdocipher/watermarkenabled',
        get_string('watermarkenabled', 'local_vdocipher'),
        get_string('watermarkenabled_desc', 'local_vdocipher'),
        1
    ));

    // Watermark opacity (0..1) and font size for the rtext annotation.
    $settings->add(new admin_setting_configtext(
        'local_vdocipher/watermarkalpha',
        get_string('watermarkalpha', 'local_vdocipher'),
        get_string('watermarkalpha_desc', 'local_vdocipher'),
        '0.60',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_vdocipher/watermarksize',
        get_string('watermarksize', 'local_vdocipher'),
        get_string('watermarksize_desc', 'local_vdocipher'),
        '15',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);

    // Connection smoke-test / diagnostics page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_vdocipher_diagnose',
        get_string('diagnose', 'local_vdocipher'),
        new moodle_url('/local/vdocipher/diagnose.php')
    ));
}
