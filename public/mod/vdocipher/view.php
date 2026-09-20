<?php
/**
 * Display a VdoCipher Video activity: the secure player for the logged-in user.
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // course module id

$cm      = get_coursemodule_from_id('vdocipher', $id, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$moduleinstance = $DB->get_record('vdocipher', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/vdocipher:view', $context);

// Log the view / completion.
$event = \mod_vdocipher\event\course_module_viewed::create([
    'objectid' => $moduleinstance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('vdocipher', $moduleinstance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url(new moodle_url('/mod/vdocipher/view.php', ['id' => $cm->id]));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth'); // Same immersive player frame as mod_vimeo.

echo $OUTPUT->header();

if (empty($moduleinstance->videoid)) {
    echo $OUTPUT->notification(get_string('err_novideo', 'local_vdocipher'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

try {
    $data = \local_vdocipher\playback_service::mint($moduleinstance->videoid, $USER);
} catch (\Throwable $e) {
    debugging('mod_vdocipher view mint error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    echo $OUTPUT->notification(get_string('err_apifailed', 'local_vdocipher', $e->getMessage()),
        \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    exit;
}

$src = 'https://player.vdocipher.com/v2/?otp=' . rawurlencode($data['otp'])
     . '&playbackInfo=' . rawurlencode($data['playbackInfo']);
$iframe = '<iframe src="' . s($src) . '" title="' . s(format_string($moduleinstance->name)) . '"'
    . ' style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"'
    . ' allow="encrypted-media" allowfullscreen></iframe>';

// The shared T1 player frame (local_academy\player); bare embed on any error.
$rendered = false;
try {
    $cminfo = get_fast_modinfo($course)->get_cm($cm->id);
    $overview = !empty($moduleinstance->intro)
        ? format_module_intro('vdocipher', $moduleinstance, $cm->id) : '';
    echo \local_academy\player::render($cminfo, $course,
        '<div class="nit-player__video">' . $iframe . '</div>', $overview, true);
    $rendered = true;
} catch (\Throwable $ex) {
    debugging('mod_vdocipher T1 player failed, using bare embed: ' . $ex->getMessage(), DEBUG_DEVELOPER);
}
if (!$rendered) {
    if (!empty($moduleinstance->intro)) {
        echo $OUTPUT->box(format_module_intro('vdocipher', $moduleinstance, $cm->id), 'generalbox', 'intro');
    }
    echo '<div style="position:relative;width:100%;max-width:960px;margin:1rem auto;aspect-ratio:16/9;background:#000;">'
        . $iframe . '</div>';
}
echo $OUTPUT->footer();
