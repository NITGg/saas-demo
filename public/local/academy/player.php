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

// Lock-order: a lesson opens only after the earlier tracked lessons are complete.
\local_academy\player::require_unlocked($course, (int) $cm->id);

$PAGE->set_url(new moodle_url('/local/academy/player.php', ['cmid' => $cm->id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('nit_fullwidth');

$embed = new moodle_url($cm->url);
$embed->param('nitplayer', 1);

$stage = '<div class="nit-player__frame">'
    . '<iframe id="nit-player-frame" src="' . s($embed->out(false)) . '" title="' . s(format_string($cm->name)) . '"'
    . ' loading="eager" allow="fullscreen" allowfullscreen></iframe></div>'
    . '<style>.nit-player__frame iframe.is-dialog{position:fixed;inset:0;width:100vw;height:100vh!important;min-height:0;'
    . 'z-index:1090;background:transparent}</style>'
    . '<script>' . <<<'JS'
(function () {
    // The frame (same origin, so readable) follows the height of its content, so the
    // page scrolls as one. While the lesson shows a dialog (a Moodle modal such as the
    // quiz "Submit all and finish" confirmation, or the YUI file picker) the frame is
    // laid over the whole window instead: the dialog then covers the navbar and the
    // sidebar like on any page, and it is centred in what the learner sees — inside a
    // page-tall frame it was centred far below, and the file picker, re-centred on
    // every resize, kept growing the frame (and scrolling the page) for ever.
    var f = document.getElementById('nit-player-frame');
    if (!f) {
        return;
    }
    var open = false, saved = 0;

    function doc() {
        try {
            return f.contentDocument && f.contentDocument.body ? f.contentDocument : null;
        } catch (e) {
            return null;
        }
    }
    function dialogOpen(d) {
        if (d.body.classList.contains('modal-open') || d.querySelector('.modal.show')) {
            return true;
        }
        return Array.prototype.some.call(d.querySelectorAll('.moodle-dialogue.yui3-widget-modal'), function (n) {
            return !n.classList.contains('yui3-widget-hidden') && n.offsetParent !== null;
        });
    }
    function fit() {
        var d = doc();
        if (!d || open) {
            return;
        }
        // The content's own height: #page, not the document (which is at least as tall
        // as the frame, so measuring it could only ever grow the frame).
        var page = d.getElementById('page');
        var h = page ? Math.ceil(page.getBoundingClientRect().bottom + d.defaultView.scrollY) + 24
            : d.documentElement.scrollHeight;
        if (h > 200) {
            f.style.height = h + 'px';
        }
    }
    function sync() {
        var d = doc();
        if (!d) {
            return;
        }
        var now = dialogOpen(d);
        if (now === open) {
            fit();
            return;
        }
        open = now;
        if (open) {
            // Keep the part of the lesson the learner was looking at behind the dialog.
            saved = window.scrollY;
            var into = Math.max(0, -f.getBoundingClientRect().top);
            f.classList.add('is-dialog');
            document.documentElement.style.overflow = 'hidden';
            d.defaultView.scrollTo(0, into);
            d.defaultView.dispatchEvent(new Event('resize')); // Moodle dialogues re-centre.
        } else {
            f.classList.remove('is-dialog');
            document.documentElement.style.overflow = '';
            d.defaultView.scrollTo(0, 0);
            fit();
            window.scrollTo(0, saved);
        }
    }

    f.addEventListener('load', function () {
        var d = doc();
        if (!d) {
            return;
        }
        // A page that lost ?nitplayer=1 on the way (and so came with the full site
        // chrome) is opened again without it.
        var u = new URL(d.location.href);
        if (!d.body.classList.contains('nit-player-embed') && !u.searchParams.has('nitplayer')
                && u.origin === window.location.origin && d.body.className.indexOf('cmid-') !== -1) {
            u.searchParams.set('nitplayer', '1');
            d.location.replace(u.toString());
            return;
        }
        open = false;
        f.classList.remove('is-dialog');
        document.documentElement.style.overflow = '';
        sync();
        new MutationObserver(sync).observe(d.body, {subtree: true, childList: true, attributes: true,
            attributeFilter: ['class', 'style']});
    });
    window.addEventListener('resize', fit);
})();
JS
    . '</script>';

echo $OUTPUT->header();
echo \local_academy\player::render($cm, $course, $stage, '', false);
echo $OUTPUT->footer();
