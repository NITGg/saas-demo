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

namespace local_nit_videoprogress;

use moodle_url;

/**
 * Puts progress tracking on a video lesson page.
 *
 * A video module's view.php calls {@see self::tracker()} and prints the
 * returned strip next to its player iframe; js/tracker.js finds the iframe,
 * resumes from the saved position and reports progress while it plays.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {

    /**
     * Load the tracker for this lesson and return the progress strip HTML.
     *
     * Guests and users who are not logged in get nothing (there is no one to
     * save progress for).
     *
     * @param \cm_info|\stdClass $cm the video lesson (id, modname)
     * @return string HTML
     */
    public static function tracker($cm): string {
        global $PAGE, $USER;
        if (!isloggedin() || isguestuser() || !in_array($cm->modname, progress::PROVIDERS, true)) {
            return '';
        }
        $row = progress::get((int) $USER->id, (int) $cm->id);
        $percent = $row ? (int) $row->percent : 0;
        $resume = progress::resume_position($row);

        // The plugin version in the URL: browsers fetch the script again after an update.
        $PAGE->requires->js(new moodle_url('/local/nit_videoprogress/js/tracker.js',
            ['v' => get_config('local_nit_videoprogress', 'version')]));
        $s = fn(string $key, $a = null) => get_string($key, 'local_nit_videoprogress', $a);

        $attrs = [
            'class' => 'nitvp nit-brand-18',
            'data-nitvp' => '1',
            'data-cmid' => (int) $cm->id,
            'data-provider' => $cm->modname,
            'data-resume' => $resume,
            'data-percent' => $percent,
        ];
        $html = \html_writer::start_div('', $attrs);
        $html .= \html_writer::start_div('nitvp__row');
        $html .= \html_writer::span($s('watched'), 'nitvp__label');
        $html .= \html_writer::div(\html_writer::tag('i', '', ['style' => 'width:' . $percent . '%', 'data-nitvp-bar' => '1']),
            'nitvp__bar', ['role' => 'progressbar', 'aria-valuemin' => 0, 'aria-valuemax' => 100,
                'aria-valuenow' => $percent, 'aria-label' => $s('watched')]);
        $html .= \html_writer::span($percent . '%', 'nitvp__pct', ['data-nitvp-pct' => '1']);
        $html .= \html_writer::end_div();
        if ($resume > 0) {
            $html .= \html_writer::div(
                \html_writer::span($s('resumedfrom', progress::format_time($resume)), 'nitvp__resumetext')
                . \html_writer::tag('button', $s('startover'), ['type' => 'button', 'class' => 'nitvp__restart',
                    'data-nitvp-restart' => '1']),
                'nitvp__resume', ['data-nitvp-resume' => '1', 'hidden' => 'hidden']);
        }
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Per-course list of video lessons with how much was watched (student and
     * guardian pages).
     *
     * @param array $overview from progress::overview()
     * @param bool $links link lesson names to the lessons (not for the guardian)
     * @return string HTML
     */
    public static function overview(array $overview, bool $links = false): string {
        $s = fn(string $key, $a = null) => get_string($key, 'local_nit_videoprogress', $a);
        if (!$overview) {
            return \html_writer::tag('p', $s('novideos'), ['class' => 'text-muted']);
        }
        $html = '';
        foreach ($overview as $course) {
            $rows = '';
            foreach ($course['lessons'] as $lesson) {
                $name = $links && $lesson['url'] !== ''
                    ? \html_writer::link($lesson['url'], $lesson['name']) : s($lesson['name']);
                $meta = $lesson['lastwatched']
                    ? $s('lastposition', progress::format_time($lesson['position']))
                        . ' · ' . userdate($lesson['lastwatched'], get_string('strftimedatefullshort', 'langconfig'))
                    : $s('notstarted');
                $rows .= \html_writer::div(
                    \html_writer::span($name, 'nitvp-lesson__name')
                    . \html_writer::span($meta, 'nitvp-lesson__meta')
                    . \html_writer::div(\html_writer::tag('i', '', ['style' => 'width:' . (int) $lesson['percent'] . '%']),
                        'nitvp-lesson__bar', ['role' => 'progressbar', 'aria-valuemin' => 0, 'aria-valuemax' => 100,
                            'aria-valuenow' => (int) $lesson['percent'], 'aria-label' => $lesson['name']])
                    . \html_writer::span((int) $lesson['percent'] . '%', 'nitvp-lesson__pct'),
                    'nitvp-lesson');
            }
            $html .= \html_writer::div(
                \html_writer::div(\html_writer::tag('h3', s($course['coursename']), ['class' => 'nitvp-course__name'])
                    . self::chip((int) $course['percent']), 'nitvp-course__head')
                . $rows,
                'nitvp-course');
        }
        return \html_writer::div($html, 'nitvp-list nit-brand-18');
    }

    /**
     * A small "watched X%" chip for lesson lists (course page, player sidebar).
     *
     * @param int $percent
     * @return string HTML
     */
    public static function chip(int $percent): string {
        $percent = max(0, min(100, $percent));
        return \html_writer::span(
            \html_writer::span('', 'nitvp-chip__ring', ['style' => '--nitvp-p:' . $percent . '%'])
            . \html_writer::span($percent . '%', 'nitvp-chip__num'),
            'nitvp-chip nit-brand-18' . ($percent >= 100 ? ' nitvp-chip--done' : ''),
            ['title' => get_string('watchedpercent', 'local_nit_videoprogress', $percent)]);
    }
}
