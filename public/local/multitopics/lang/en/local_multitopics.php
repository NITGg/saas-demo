<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Multitopics course content API';

// Mobile app settings (served by getsettings.php).
$string['mobilesettings']    = 'Mobile app settings';
$string['mobilesettings_desc'] = 'Values served anonymously to the mobile app by getsettings.php (read before login). Tokens are per academy; google_client_id must be the SAME for every academy.';

$string['user_token']        = 'User web-service token';
$string['user_token_desc']   = 'This academy\'s mobile web-service token.';
$string['admin_token']       = 'Admin / registration token';
$string['admin_token_desc']  = 'Shared pre-login token the app uses for registration. Per academy — rotate the EAAC value once live.';
$string['google_client_id']  = 'Google OAuth client id';
$string['google_client_id_desc'] = 'MUST be identical for every academy — the OAuth client is bound to the app bundle id, not the site.';

$string['prevent_screen_recording'] = 'Prevent screen recording';
$string['prevent_screen_recording_desc'] = 'Ask the app to block screenshots / screen recording.';
$string['watermark']         = 'Watermark (fallback)';
$string['watermark_desc']    = 'Fallback only — the app prefers the VdoCipher plugin\'s watermark setting when installed.';
$string['watermark_text']    = 'Watermark text (fallback)';
$string['videourl']          = 'Video base URL';

$string['whatsapp_phone']    = 'WhatsApp phone';
$string['whatsapp_message']  = 'WhatsApp default message';
$string['allow_paymob']      = 'Allow Paymob payments';

$string['android_version']   = 'Android latest version';
$string['android_url']       = 'Android store URL';
$string['ios_version']       = 'iOS latest version';
$string['ios_url']           = 'iOS store URL';

$string['google_login']      = 'Google login (fallback)';
$string['apple_login']       = 'Apple login (fallback)';
$string['facebook_login']    = 'Facebook login (fallback)';
$string['social_login_desc'] = 'Fallback only — driven by the licence (local_license) when installed.';

$string['server_timeout_duration'] = 'Server timeout (seconds)';
