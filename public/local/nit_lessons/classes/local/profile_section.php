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

use local_nit_lessons\service\teacher_service;

/**
 * The teacher's live-lesson settings (take bookings, headline, subjects, weekly
 * hours) as a card of the profile page /local/academy/profile.php — it replaced
 * the old /local/nit_lessons/teacher_profile.php. The card's inputs post with the
 * profile form; save() stores them.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_section {

    /** Name of the hidden input that says the card was on the posted form. */
    const MARKER = 'nitlx_profile';

    /**
     * Whether the user gets the card: a teacher, with lesson packages on.
     *
     * @param int $userid
     * @return bool
     */
    public static function applies(int $userid): bool {
        global $CFG;
        require_once($CFG->dirroot . '/local/nit_flex/lib.php');
        return local_nit_flex_enabled() && (new teacher_service())->is_teacher($userid);
    }

    /**
     * Save the posted card (call only for a confirmed sesskey).
     *
     * @param int $userid
     * @return string|null an error message, or null when saved / not on the form
     */
    public static function save(int $userid): ?string {
        if (!optional_param(self::MARKER, 0, PARAM_BOOL) || !self::applies($userid)) {
            return null;
        }
        $days = optional_param_array('nitlx_day', [], PARAM_INT);
        $starts = optional_param_array('nitlx_start', [], PARAM_RAW_TRIMMED);
        $ends = optional_param_array('nitlx_end', [], PARAM_RAW_TRIMMED);
        $hours = [];
        foreach ($days as $i => $day) {
            if (($starts[$i] ?? '') === '' && ($ends[$i] ?? '') === '') {
                continue;
            }
            $hours[] = ['dayofweek' => (int) $day, 'starttime' => (string) ($starts[$i] ?? ''),
                'endtime' => (string) ($ends[$i] ?? '')];
        }
        try {
            (new teacher_service())->save($userid, (bool) optional_param('nitlx_available', 0, PARAM_BOOL),
                optional_param('nitlx_headline', '', PARAM_TEXT), optional_param_array('nitlx_subjects', [], PARAM_TEXT), $hours);
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }
        return null;
    }

    /**
     * The card's HTML ('' when it does not apply).
     *
     * @param int $userid
     * @return string
     */
    public static function render(int $userid): string {
        global $OUTPUT, $USER;
        if (!self::applies($userid)) {
            return '';
        }
        $profile = (new teacher_service())->profile($userid);
        $daynames = [];
        foreach (range(0, 6) as $d) {
            $daynames[] = ['value' => $d, 'label' => get_string('day' . $d, 'local_nit_lessons')];
        }
        $hours = [];
        foreach ($profile['hours'] as $h) {
            $hours[] = [
                'start' => $h['starttime'],
                'end' => $h['endtime'],
                'days' => array_map(fn($d) => $d + ['selected' => $d['value'] === $h['dayofweek']], $daynames),
            ];
        }
        $options = teacher_service::subject_options($userid);
        $select = function(string $chosen) use ($options): array {
            return array_map(fn($o) => ['value' => $o, 'label' => format_string($o), 'selected' => $o === $chosen], $options);
        };
        $subjects = array_map(fn($s) => ['options' => $select($s)], $profile['subjects'] ?: ['']);

        return $OUTPUT->render_from_template('local_nit_lessons/profile_section', [
            'marker' => self::MARKER,
            'available' => $profile['available'],
            'headline' => $profile['headline'],
            'subjects' => $subjects,
            'blankoptions' => $select(''),
            'hassubjectoptions' => !empty($options),
            'hours' => $hours,
            'daynames' => $daynames,
            'timezone' => \core_date::get_localised_timezone(\core_date::get_user_timezone($USER)),
            'mylessonsurl' => (new \moodle_url('/local/nit_lessons/my_lessons.php'))->out(false),
        ]);
    }
}
