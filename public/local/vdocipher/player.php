<?php
/**
 * VdoCipher web player — session-authenticated, embeddable.
 *
 *   /local/vdocipher/player.php?cmid=<resource2 cmid>
 *
 * Uses the browser session (require_login enforces enrolment + activity access),
 * mints a short-lived watermarked OTP for the logged-in user, and renders the
 * VdoCipher web SDK. DRM blocks download; the moving watermark carries the
 * viewer's identity. Designed to be embedded in an iframe from the resource2
 * view or a theme page.
 */

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

// Full session gate: login, enrolment, and activity availability/visibility.
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/vdocipher:view', $context);

$PAGE->set_url(new moodle_url('/local/vdocipher/player.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($cm->name));

$row = $DB->get_record('local_vdocipher_videos', ['cmid' => $cmid]);

echo $OUTPUT->header();

if (!$row) {
    echo $OUTPUT->notification(get_string('err_novideo', 'local_vdocipher'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

try {
    $data = \local_vdocipher\playback_service::mint($row->videoid, $USER);
} catch (\Throwable $e) {
    debugging('local_vdocipher player mint error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    echo $OUTPUT->notification(get_string('err_apifailed', 'local_vdocipher', $e->getMessage()),
        \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    exit;
}

$src = 'https://player.vdocipher.com/v2/?otp=' . rawurlencode($data['otp'])
     . '&playbackInfo=' . rawurlencode($data['playbackInfo']);
?>
<div style="position:relative;width:100%;max-width:960px;margin:0 auto;aspect-ratio:16/9;background:#000;">
  <iframe src="<?php echo s($src); ?>"
          style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
          allow="encrypted-media" allowfullscreen></iframe>
</div>
<?php
echo $OUTPUT->footer();
