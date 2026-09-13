<?php
defined('MOODLE_INTERNAL') || die();

// NIT SaaS note: one shared Jitsi + Excalidraw server serves EVERY academy, so the
// connection details below are baked as defaults (a fresh academy needs no config).
// Rooms are namespaced per-academy in mod_jitsi (jitsi_room_name()), so tenants can
// safely share the same Jitsi host. Recording storage is VdoCipher (local_vdocipher)
// — MinIO/Bunny were removed. The Jitsi ACTIVITY is gated to the Professional tier
// via local_license::has_feature('jitsi').
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_academysessions', get_string('pluginname', 'local_academysessions'));

    $settings->add(new admin_setting_heading(
        'local_academysessions/jitsi_heading',
        'Jitsi Meet Settings',
        'Shared self-hosted Jitsi Meet server used by all academies for live sessions.'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/jitsi_host',
        'Jitsi Host',
        'Hostname (and port) of the shared Jitsi server, e.g. academy2026.nitg-eg.com:8443.',
        'academy2026.nitg-eg.com:8443'
    ));

    // JWT auth for Jitsi. app_id/secret must match the shared Jitsi server's config.
    $settings->add(new admin_setting_configtext(
        'local_academysessions/jitsi_jwt_app_id',
        'Jitsi JWT App ID',
        'The app_id configured on the Jitsi server (iss/aud in the token).',
        'academy_jitsi'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_academysessions/jitsi_jwt_app_secret',
        'Jitsi JWT App Secret',
        'The shared HS256 secret configured on the Jitsi server. Change from the default in production.',
        'academy_jitsi_secret_2024_change_in_prod'
    ));

    $settings->add(new admin_setting_heading(
        'local_academysessions/excalidraw_heading',
        'Excalidraw Whiteboard Settings',
        'Shared collaborative whiteboard shown alongside the Jitsi session.'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/excalidraw_host',
        'Excalidraw Room Server (WebSocket relay)',
        'Hostname and port of the Socket.IO relay server (e.g. academy2026.nitg-eg.com or localhost:9090).',
        'localhost:9090'
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/excalidraw_app',
        'Excalidraw App URL',
        'Full URL of the Excalidraw frontend app (e.g. https://academy2026.nitg-eg.com/whiteboard).',
        'https://academy2026.nitg-eg.com/whiteboard'
    ));

    $ADMIN->add('localplugins', $settings);
}
