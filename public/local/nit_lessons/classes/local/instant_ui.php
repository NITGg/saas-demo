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

namespace local_nit_lessons\local;

use local_nit_lessons\service\instant_service;
use local_nit_lessons\service\teacher_service;

/**
 * Puts the instant-lesson widgets (js/instant.js) on a page: the student panel, the teacher switch,
 * and — for a teacher who is online — the request prompts on every page.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class instant_ui {
    /** @var bool the script is already on this page */
    private static $added = false;

    /** @var string[] Strings the script needs (instant_<key>). */
    const STRINGS = ['title', 'intro', 'noteph', 'go', 'online', 'nobodyonline', 'searching', 'asking', 'cancel',
        'matched', 'expired', 'cancelled', 'expiredhelp', 'again', 'available', 'availablehelp', 'prompttitle',
        'minutes', 'flex', 'accept', 'decline', 'photo', 'notifyme', 'notifyset', 'booklater'];

    /**
     * The script and its settings, once per page (empty when instant lessons are off).
     *
     * @param int $userid
     * @return string HTML
     */
    public static function script(int $userid): string {
        global $CFG;
        $service = new instant_service();
        if (self::$added || !$service->enabled() || isguestuser($userid)) {
            return '';
        }
        self::$added = true;
        $strings = [];
        foreach (self::STRINGS as $key) {
            $strings[$key] = get_string('instant_' . $key, 'local_nit_lessons');
        }
        $config = [
            'url' => (new \moodle_url('/local/nit_lessons/instant.php'))->out(false),
            'joinurl' => (new \moodle_url('/local/nit_lessons/join.php'))->out(false) . '?id=',
            'sesskey' => sesskey(),
            'teacher' => (new teacher_service())->is_teacher($userid),
            'online' => $service->presence($userid)['online'] || self::marked_online($userid),
            'strings' => $strings,
        ];
        $src = (new \moodle_url('/local/nit_lessons/js/instant.js', ['v' => get_config('local_nit_lessons', 'version')]))
            ->out(false);
        return '<script>window.NIT_INSTANT=' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
            . '<script defer src="' . s($src) . '"></script>';
    }

    /**
     * The teacher switched online (even if their heartbeat lapsed while they were away).
     *
     * @param int $userid
     * @return bool
     */
    public static function marked_online(int $userid): bool {
        global $DB;
        return $DB->record_exists('nit_teacher_presence', ['teacherid' => $userid, 'online' => 1]);
    }

    /**
     * Footer hook: an online teacher gets the prompts on every page.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook
     * @return void
     */
    public static function footer(\core\hook\output\before_standard_footer_html_generation $hook): void {
        global $USER, $PAGE;
        if (during_initial_install() || !isloggedin() || isguestuser()
                || in_array($PAGE->pagelayout, ['popup', 'embedded', 'redirect', 'maintenance'], true)) {
            return;
        }
        try {
            if (self::marked_online((int) $USER->id)) {
                $hook->add_html(self::script((int) $USER->id));
            }
        } catch (\Throwable $e) {
            debugging('local_nit_lessons: instant prompts not added: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
