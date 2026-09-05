<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']         = 'Vimeo Video';
$string['modulename']         = 'Vimeo Video';
$string['modulenameplural']   = 'Vimeo Videos';
$string['modulename_help']    = 'The Vimeo Video activity embeds a Vimeo-hosted video. Upload a file straight to Vimeo from your browser, or paste an existing Vimeo link. Playback is restricted to this academy\'s domain.';
$string['pluginadministration'] = 'Vimeo Video administration';

// Capabilities.
$string['vimeo:addinstance'] = 'Add a new Vimeo Video activity';
$string['vimeo:view']        = 'View a Vimeo Video';

// Form.
$string['videosource']       = 'Video';
$string['videofile']         = 'Upload a video';
$string['videofile_help']    = 'Choose a video file. It uploads directly from your browser to Vimeo (it does not pass through the server), so large files and slow connections are fine — watch the progress bar. When it finishes, the video ID field below is filled automatically; then click Save.';
$string['videoid']           = 'Vimeo video ID or URL';
$string['videoid_help']      = 'Filled automatically after an upload finishes. You can also paste a Vimeo video ID (the number in vimeo.com/123456789) or a full Vimeo URL instead of uploading.';
$string['videohash']         = 'Privacy hash (optional)';
$string['videohash_help']    = 'For an unlisted Vimeo video whose link looks like vimeo.com/123456789/abcdef123, enter the second part (abcdef123) here. Leave blank for normal videos.';
$string['err_novideosource'] = 'Add a video: upload a file or paste a Vimeo video ID / URL.';

// Index.
$string['noinstances']       = 'There are no Vimeo Videos in this course.';

// Privacy.
$string['privacy:metadata']  = 'The Vimeo Video plugin does not store personal data. Videos are hosted on Vimeo; playback happens directly between the viewer\'s browser and Vimeo.';
