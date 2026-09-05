<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_vimeo_settings',
        get_string('pluginname', 'local_vimeo'));

    // ── Credentials ──────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_vimeo/credsheading',
        get_string('credsheading', 'local_vimeo'),
        get_string('credsheading_desc', 'local_vimeo')
    ));

    // The Vimeo access token. This is a SHARED, platform-level token: the nit2
    // provisioner writes it per-package at academy-create / tier-change time. It
    // is stored unmasked in config but never sent to any client — only the server
    // uses it as "Authorization: bearer <token>".
    $settings->add(new admin_setting_configpasswordunmask(
        'local_vimeo/access_token',
        get_string('access_token', 'local_vimeo'),
        get_string('access_token_desc', 'local_vimeo'),
        ''
    ));

    // Client id / secret are kept for completeness (OAuth app identity / future
    // token refresh). Playback and upload use the access token above.
    $settings->add(new admin_setting_configtext(
        'local_vimeo/client_id',
        get_string('client_id', 'local_vimeo'),
        get_string('client_id_desc', 'local_vimeo'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_vimeo/client_secret',
        get_string('client_secret', 'local_vimeo'),
        get_string('client_secret_desc', 'local_vimeo'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_vimeo/apibase',
        get_string('apibase', 'local_vimeo'),
        get_string('apibase_desc', 'local_vimeo'),
        'https://api.vimeo.com',
        PARAM_URL
    ));

    // ── Playback / privacy ────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_vimeo/playbackheading',
        get_string('playbackheading', 'local_vimeo'),
        get_string('playbackheading_desc', 'local_vimeo')
    ));

    // On upload, add the academy domain to each new video's embed whitelist so
    // the private embed is playable here. Turn off only if you whitelist domains
    // some other way (e.g. centrally in the Vimeo dashboard).
    $settings->add(new admin_setting_configcheckbox(
        'local_vimeo/autowhitelist',
        get_string('autowhitelist', 'local_vimeo'),
        get_string('autowhitelist_desc', 'local_vimeo'),
        1
    ));

    // Domain to whitelist; blank derives it from this site's wwwroot host.
    $settings->add(new admin_setting_configtext(
        'local_vimeo/whitelistdomain',
        get_string('whitelistdomain', 'local_vimeo'),
        get_string('whitelistdomain_desc', 'local_vimeo'),
        '',
        PARAM_HOST
    ));

    $ADMIN->add('localplugins', $settings);

    // Connection smoke-test / diagnostics page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_vimeo_diagnose',
        get_string('diagnose', 'local_vimeo'),
        new moodle_url('/local/vimeo/diagnose.php')
    ));
}
