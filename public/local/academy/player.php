<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The course player for NON-video lessons.
 *
 * Renders the T1 player frame (curriculum sidebar, progress, prev/next) around
 * the REAL activity view, embedded chrome-free (?nitplayer=1 → the theme swaps
 * that request to the `embedded` layout). Video lessons never come here — their
 * own module page renders the same frame around the video embed.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// A video lesson has its own player page.
if (in_array($cm->modname, \local_academy\player::VIDEO_MODS, true) && $cm->url) {
    redirect($cm->url);
}
if ($cm->url === null) {
    // Nothing to show on its own page (a label) — back to the course.
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$PAGE->set_url(new moodle_url('/local/academy/player.php', ['cmid' => $cm->id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('nit_fullwidth');

$embed = new moodle_url($cm->url);
$embed->param('nitplayer', 1);

// Make the frame follow its content's height (same origin, so we can read it).
$stage = '<div class="nit-player__frame">'
    . '<iframe id="nit-player-frame" src="' . s($embed->out(false)) . '" title="' . s(format_string($cm->name)) . '"'
    . ' loading="eager" allow="fullscreen" allowfullscreen></iframe></div>'
    . '<script>(function(){var f=document.getElementById("nit-player-frame");if(!f){return;}'
    . 'function fit(){try{var d=f.contentDocument;if(!d){return;}var h=Math.max(d.documentElement.scrollHeight,d.body?d.body.scrollHeight:0);'
    . 'if(h>200){f.style.minHeight=(h+24)+"px";}}catch(e){}}'
    . 'f.addEventListener("load",function(){fit();try{new MutationObserver(fit).observe(f.contentDocument.body,{subtree:true,childList:true,attributes:true});}catch(e){}});'
    . 'window.addEventListener("resize",fit);})();</script>';

echo $OUTPUT->header();
echo \local_academy\player::render($cm, $course, $stage, '', false);
echo $OUTPUT->footer();
