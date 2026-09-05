<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vimeo';

// Capabilities.
$string['vimeo:manage'] = 'Create, edit and delete Vimeo videos';
$string['vimeo:view']   = 'Play Vimeo videos';

// Settings — credentials.
$string['credsheading']       = 'Vimeo credentials';
$string['credsheading_desc']  = 'Credentials for your Vimeo account. This is a SHARED, platform-level access token provisioned per package by nit2; it is used only on the server as the "Authorization: bearer &lt;token&gt;" header and is never sent to any client.';
$string['access_token']       = 'Access token';
$string['access_token_desc']  = 'The Vimeo access token (scopes: public, private, edit, upload, video_files). Sent as the "Authorization: bearer &lt;token&gt;" header. Provisioned automatically per package by nit2.';
$string['client_id']          = 'Client ID';
$string['client_id_desc']     = 'Vimeo app client identifier. Kept for completeness (OAuth app identity); upload and playback use the access token above.';
$string['client_secret']      = 'Client secret';
$string['client_secret_desc'] = 'Vimeo app client secret. Kept for completeness; not required for upload or playback with a personal access token.';
$string['apibase']            = 'API base URL';
$string['apibase_desc']       = 'Vimeo REST API base. Default: https://api.vimeo.com';

// Settings — playback / privacy.
$string['playbackheading']        = 'Playback &amp; privacy';
$string['playbackheading_desc']   = 'Vimeo videos are created private (view disabled) with embed-whitelist privacy — they play only when embedded on a whitelisted domain. There is no per-view OTP or watermark.';
$string['autowhitelist']          = 'Auto-whitelist this domain';
$string['autowhitelist_desc']     = 'When a video is created, add the academy domain to its embed whitelist so the private embed plays here. Turn off only if you manage whitelisting another way.';
$string['whitelistdomain']        = 'Whitelist domain override';
$string['whitelistdomain_desc']   = 'Host to add to each new video\'s embed whitelist, e.g. academy.example.com. Leave blank to use this site\'s own host (from wwwroot).';

// Diagnostics.
$string['diagnose']           = 'Vimeo diagnostics';

// Errors.
$string['err_notoken']        = 'The Vimeo access token is not configured. Set it in Site administration → Plugins → Local plugins → Vimeo.';
$string['err_nosecret']       = 'The Vimeo access token is not configured. Set it in Site administration → Plugins → Local plugins → Vimeo.';
$string['err_apifailed']      = 'The Vimeo API request failed: {$a}';
$string['err_novideo']        = 'No Vimeo video is attached to this activity.';
$string['err_noaccess']       = 'You do not have access to this video.';

// Privacy (no personal data stored beyond the standard usermodified/timestamps).
$string['privacy:metadata']   = 'The Vimeo plugin stores a mapping between course activities and Vimeo video ids; it does not store personal data about video viewers. Videos and their metadata are held by Vimeo.';
