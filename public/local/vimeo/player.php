<?php
/**
 * Vimeo web player — session-authenticated, embeddable.
 *
 *   /local/vimeo/player.php?cmid=<resource2 cmid>
 *
 * Uses the browser session (require_login enforces enrolment + activity access)
 * and renders the Vimeo embed iframe. The video's privacy is embed-whitelisted
 * to the academy domain, so it only plays when served from here. There is no OTP
 * or watermark. Designed to be embedded in an iframe from the resource2 view or
 * a theme page.
 */

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

// Full session gate: login, enrolment, and activity availability/visibility.
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/vimeo:view', $context);

$PAGE->set_url(new moodle_url('/local/vimeo/player.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($cm->name));

$row = $DB->get_record('local_vimeo_videos', ['cmid' => $cmid]);

echo $OUTPUT->header();

if (!$row) {
    echo $OUTPUT->notification(get_string('err_novideo', 'local_vimeo'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

$data = \local_vimeo\playback_service::embed($row);
$src  = $data['embedurl'];
?>
<div style="position:relative;width:100%;max-width:960px;margin:0 auto;aspect-ratio:16/9;background:#000;">
  <iframe src="<?php echo s($src); ?>"
          style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
          allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
          allowfullscreen></iframe>
</div>
<?php
echo $OUTPUT->footer();
