<?php
/**
 * Display a Vimeo Video activity: the embedded player for the logged-in user.
 *
 * Access is enforced by Moodle (require_login + capability) and, on Vimeo's side,
 * by embed-whitelist privacy on the academy domain. There is no OTP/watermark.
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // course module id

$cm      = get_coursemodule_from_id('vimeo', $id, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$moduleinstance = $DB->get_record('vimeo', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/vimeo:view', $context);

// Log the view / completion.
$event = \mod_vimeo\event\course_module_viewed::create([
    'objectid' => $moduleinstance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('vimeo', $moduleinstance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url(new moodle_url('/mod/vimeo/view.php', ['id' => $cm->id]));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

if (!empty($moduleinstance->intro)) {
    echo $OUTPUT->box(format_module_intro('vimeo', $moduleinstance, $cm->id), 'generalbox', 'intro');
}

if (empty($moduleinstance->videoid)) {
    echo $OUTPUT->notification(get_string('err_novideo', 'local_vimeo'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

$src = 'https://player.vimeo.com/video/' . rawurlencode($moduleinstance->videoid);
if (!empty($moduleinstance->videohash)) {
    $src .= '?h=' . rawurlencode($moduleinstance->videohash);
}
?>
<div style="position:relative;width:100%;max-width:960px;margin:1rem auto;aspect-ratio:16/9;background:#000;">
  <iframe src="<?php echo s($src); ?>"
          style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
          allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
</div>
<?php
echo $OUTPUT->footer();
