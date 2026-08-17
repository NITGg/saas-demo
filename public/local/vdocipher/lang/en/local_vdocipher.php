<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'VdoCipher';

// Capabilities.
$string['vdocipher:manage'] = 'Create, edit and delete VdoCipher videos';
$string['vdocipher:view']   = 'Play VdoCipher videos';

// Settings — credentials.
$string['credsheading']      = 'VdoCipher credentials';
$string['credsheading_desc'] = 'API credentials for your VdoCipher account. The secret is used only on the server to sign requests and mint short-lived playback OTPs; it is never sent to any client.';
$string['apisecret']         = 'API secret';
$string['apisecret_desc']    = 'The API secret from your VdoCipher dashboard (Config → API keys). Sent as the "Authorization: Apisecret &lt;key&gt;" header.';
$string['apibase']           = 'API base URL';
$string['apibase_desc']      = 'VdoCipher REST API base. Default: https://dev.vdocipher.com/api';

// Settings — playback / security.
$string['playbackheading']      = 'Playback &amp; security';
$string['playbackheading_desc'] = 'Controls the short-lived playback OTP and the dynamic user watermark burned onto the video.';
$string['otpttl']               = 'OTP time-to-live (seconds)';
$string['otpttl_desc']          = 'How long a minted playback OTP stays valid. Keep this short — clients fetch a fresh OTP right before playback.';
$string['watermarktext']        = 'Watermark text';
$string['watermarktext_desc']   = 'Text overlaid on the video. Placeholders {fullname}, {email} and {userid} are filled server-side with the viewer\'s details, so the watermark cannot be forged or removed by the client.';
$string['watermarkenabled']     = 'Enable watermark';
$string['watermarkenabled_desc']= 'Burn the viewer\'s identity onto the video as a moving overlay.';
$string['watermarkalpha']       = 'Watermark opacity';
$string['watermarkalpha_desc']  = 'Opacity of the watermark text, 0 (invisible) to 1 (solid). Default 0.60.';
$string['watermarksize']        = 'Watermark font size';
$string['watermarksize_desc']   = 'Font size of the watermark text in pixels. Default 15.';

// Diagnostics.
$string['diagnose']             = 'VdoCipher diagnostics';

// Errors.
$string['err_nosecret']         = 'The VdoCipher API secret is not configured. Set it in Site administration → Plugins → Local plugins → VdoCipher.';
$string['err_apifailed']        = 'The VdoCipher API request failed: {$a}';
$string['err_novideo']          = 'No VdoCipher video is attached to this activity.';
$string['err_noaccess']         = 'You do not have access to this video.';
