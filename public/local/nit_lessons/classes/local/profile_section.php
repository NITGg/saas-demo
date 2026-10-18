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

use local_nit_lessons\entity\lesson;
use local_nit_lessons\service\teacher_service;

/**
 * The teacher's live-lesson settings (take bookings, headline, Flex per lesson, subjects, weekly
 * hours) as a card of the teacher profile /local/nit_tutors/application.php (it was on
 * /local/academy/profile.php, and before that the old /local/nit_lessons/teacher_profile.php).
 * The card's inputs post with that page's form: posted() reads them, save() stores them.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_section {

    /** Name of the hidden input that says the card was on the posted form. */
    const MARKER = 'nitlx_profile';

    /** Where the card lives. */
    const PAGE = '/local/nit_tutors/application.php';

    /**
     * The page of the card, at the card.
     *
     * @return \moodle_url
     */
    public static function url(): \moodle_url {
        return new \moodle_url(self::PAGE, null, 'lessons');
    }

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
     * The posted card, or null when it was not on the form.
     *
     * @return array|null {available: bool, headline: string, subjects: string[], hours: array[],
     *     lessonflex: int|null (null = keep), errors: [lessonflex|hours => message]}
     */
    public static function posted(): ?array {
        if (!optional_param(self::MARKER, 0, PARAM_BOOL)) {
            return null;
        }
        $days = optional_param_array('nitlx_day', [], PARAM_INT);
        $starts = optional_param_array('nitlx_start', [], PARAM_RAW_TRIMMED);
        $ends = optional_param_array('nitlx_end', [], PARAM_RAW_TRIMMED);
        $hours = [];
        foreach ($days as $i => $day) {
            $hours[] = ['dayofweek' => $day, 'starttime' => $starts[$i] ?? '', 'endtime' => $ends[$i] ?? ''];
        }
        return self::card((bool) optional_param('nitlx_available', 0, PARAM_BOOL),
            optional_param('nitlx_headline', '', PARAM_TEXT), optional_param_array('nitlx_subjects', [], PARAM_TEXT),
            $hours, optional_param('nitlx_lessonflex', '', PARAM_RAW_TRIMMED));
    }

    /**
     * The card's values, checked (the web form and the app send the same values).
     *
     * @param bool $available
     * @param string $headline
     * @param array $subjects
     * @param array $hours [{dayofweek, starttime "HH:MM", endtime "HH:MM"}]; a row with no times is dropped
     * @param string $lessonflex '' = keep the saved price
     * @return array {available, headline, subjects, hours, lessonflex: int|null (null = keep),
     *     errors: [lessonflex|hours => message]}
     */
    public static function card(bool $available, string $headline, array $subjects, array $hours, string $lessonflex): array {
        $errors = [];
        $rows = [];
        foreach ($hours as $h) {
            $day = (int) ($h['dayofweek'] ?? -1);
            $start = trim((string) ($h['starttime'] ?? ''));
            $end = trim((string) ($h['endtime'] ?? ''));
            if ($start === '' && $end === '') {
                continue;
            }
            $rows[] = ['dayofweek' => $day, 'starttime' => $start, 'endtime' => $end];
            if ($day < 0 || $day > 6 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)
                    || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)
                    || self::minutes($end) - self::minutes($start) < lesson::DEFAULT_DURATION) {
                $errors['hours'] = get_string('err_badhours', 'local_nit_lessons');
            }
        }
        // Flex per lesson: an empty box keeps the saved price; anything else must be a whole number ≥ 1.
        $lessonflex = trim($lessonflex);
        if ($lessonflex !== '' && (!preg_match('/^\d{1,18}$/', $lessonflex) || (int) $lessonflex < 1)) {
            $errors['lessonflex'] = get_string('err_badlessonflex', 'local_nit_lessons');
        }
        return [
            'available' => $available,
            'headline' => trim(clean_param($headline, PARAM_TEXT)),
            'subjects' => array_values(array_filter(array_map(fn($s) => clean_param((string) $s, PARAM_TEXT), $subjects),
                fn($s) => trim($s) !== '')),
            'hours' => $rows,
            'lessonflex' => $lessonflex === '' || isset($errors['lessonflex']) ? null : (int) $lessonflex,
            'errors' => $errors,
        ];
    }

    /**
     * Save the posted card (call only for a confirmed sesskey).
     *
     * @param int $userid
     * @param bool $force save it for a user the card does not apply to (an applicant on the teacher profile)
     * @return string|null an error message, or null when saved / not on the form
     */
    public static function save(int $userid, bool $force = false): ?string {
        $card = self::posted();
        if ($card === null || (!$force && !self::applies($userid))) {
            return null;
        }
        if ($card['errors']) {
            return reset($card['errors']);
        }
        try {
            (new teacher_service())->save($userid, $card['available'], $card['headline'], $card['subjects'], $card['hours'],
                $card['lessonflex']);
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }
        return null;
    }

    /**
     * The card's HTML ('' when it does not apply).
     *
     * @param int $userid
     * @param bool $force show it to a user the card does not apply to (an applicant on the teacher profile)
     * @param array $values shown instead of the saved ones: headline, subjects (a change waiting for
     *     review, or a refused post), available, lessonflex, hours; plus note, subjectsrequired, err
     * @return string
     */
    public static function render(int $userid, bool $force = false, array $values = []): string {
        global $OUTPUT, $USER;
        if (!$force && !self::applies($userid)) {
            return '';
        }
        $profile = $values + (new teacher_service())->profile($userid);
        $daynames = [];
        foreach (range(0, 6) as $d) {
            $daynames[] = ['value' => $d, 'label' => get_string('day' . $d, 'local_nit_lessons')];
        }
        $hours = [];
        foreach ($profile['hours'] as $h) {
            $hours[] = [
                'start' => $h['starttime'],
                'end' => $h['endtime'],
                'days' => array_map(fn($d) => $d + ['selected' => $d['value'] === (int) $h['dayofweek']], $daynames),
            ];
        }
        $options = teacher_service::subject_options($userid);
        $select = function(string $chosen) use ($options): array {
            return array_map(fn($o) => ['value' => $o, 'label' => format_string($o), 'selected' => $o === $chosen], $options);
        };
        $subjects = array_map(fn($s) => ['options' => $select((string) $s)], $profile['subjects'] ?: ['']);

        return $OUTPUT->render_from_template('local_nit_lessons/profile_section', [
            'marker' => self::MARKER,
            'available' => (bool) $profile['available'],
            'headline' => $profile['headline'],
            'lessonflex' => $values['lessonflex'] ?? $profile['lesson_flex'],
            'subjects' => $subjects,
            'blankoptions' => $select(''),
            'hassubjectoptions' => !empty($options),
            'hours' => $hours,
            'daynames' => $daynames,
            'timezone' => \core_date::get_localised_timezone(\core_date::get_user_timezone($USER)),
            'mylessonsurl' => (new \moodle_url('/local/nit_lessons/my_lessons.php'))->out(false),
            'note' => (string) ($values['note'] ?? ''),
            'subjectsrequired' => !empty($values['subjectsrequired']),
            'err' => array_map('strval', (array) ($values['err'] ?? [])),
        ]);
    }

    /**
     * Minutes since midnight of "HH:MM".
     *
     * @param string $t
     * @return int
     */
    private static function minutes(string $t): int {
        [$h, $m] = array_map('intval', explode(':', $t) + [0, 0]);
        return $h * 60 + $m;
    }
}
