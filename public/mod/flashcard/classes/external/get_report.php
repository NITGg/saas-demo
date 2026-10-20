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

namespace mod_flashcard\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_flashcard\local\api;
use mod_flashcard\local\report;

/**
 * The teacher's follow-up (the web's "Summary" page): every student with their progress,
 * status and the words they keep missing, and every word with how the class does on it.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_report extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Flash Card Set'),
            'groupid' => new external_value(PARAM_INT, 'Only this group (0 = all students)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The report.
     *
     * @param int $cmid
     * @param int $groupid
     * @return array
     */
    public static function execute(int $cmid, int $groupid = 0): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);
        $api = new api($params['cmid']);
        self::validate_context($api->context);
        require_capability('mod/flashcard:manage', $api->context);

        $report = new report($api->flashcard, $api->cm);
        $text = function($html) use ($api): string {
            return format_string(strip_tags((string) $html), true, ['context' => $api->context]);
        };

        $students = [];
        foreach ($report->students($params['groupid']) as $row) {
            if (!$row->started) {
                $status = 'notstarted';
            } else if ($row->isoverdue) {
                $status = 'late';
            } else if ($row->isdue) {
                $status = 'due';
            } else if ($row->total && $row->mastered >= $row->total) {
                $status = 'mastered';
            } else {
                $status = 'ontrack';
            }
            $decks = [];
            foreach ($row->decks as $deck => $count) {
                $decks[] = ['deck' => (int) $deck, 'count' => (int) $count];
            }
            $hard = [];
            foreach ($row->hard as $word) {
                $hard[] = ['question' => $text($word->question), 'answer' => $text($word->answer), 'tries' => $word->tries];
            }
            $picture = new \core\output\user_picture($row->user);
            $picture->size = 100;
            $students[] = [
                'userid' => (int) $row->user->id,
                'fullname' => fullname($row->user),
                'pictureurl' => $picture->get_url($PAGE)->out(false),
                'status' => $status,
                'duedecks' => array_map('intval', $row->duedecks ?? []),
                'latedays' => (int) ($row->latedays ?? 0),
                'nextreviewin' => (int) ($row->nextwait ?? 0),
                'mastered' => (int) $row->mastered,
                'total' => (int) $row->total,
                'percent' => (int) $row->percent,
                'unseen' => (int) $row->unseen,
                'lastseen' => (int) $row->lastseen,
                'decks' => $decks,
                'hardwords' => $hard,
            ];
        }

        $words = [];
        foreach ($report->words($params['groupid']) as $row) {
            $words[] = [
                'question' => $text($row->question),
                'answer' => $text($row->answer),
                'hard' => $row->hard,
                'learning' => $row->learning,
                'mastered' => $row->mastered,
                'unseen' => $row->unseen,
                'tries' => $row->tries,
            ];
        }

        return ['students' => $students, 'words' => $words];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $word = [
            'question' => new external_value(PARAM_TEXT, 'Front face text ("" for a media face)'),
            'answer' => new external_value(PARAM_TEXT, 'Back face text ("" for a media face)'),
        ];
        return new external_single_structure([
            'students' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'pictureurl' => new external_value(PARAM_URL, 'Profile picture'),
                'status' => new external_value(PARAM_ALPHA, 'late, due, ontrack, mastered or notstarted'),
                'duedecks' => new external_multiple_structure(new external_value(PARAM_INT, 'Deck number'),
                    'Decks waiting for this student now'),
                'latedays' => new external_value(PARAM_INT, 'status = late: days the oldest due deck has waited'),
                'nextreviewin' => new external_value(PARAM_INT, 'status = ontrack: seconds to the next review'),
                'mastered' => new external_value(PARAM_INT, 'Words in the last deck'),
                'total' => new external_value(PARAM_INT, 'Words in the set'),
                'percent' => new external_value(PARAM_INT, 'mastered / total, 0-100'),
                'unseen' => new external_value(PARAM_INT, 'Words not answered yet'),
                'lastseen' => new external_value(PARAM_INT, 'Unix time of the last answer, 0 = never'),
                'decks' => new external_multiple_structure(new external_single_structure([
                    'deck' => new external_value(PARAM_INT, 'Deck number'),
                    'count' => new external_value(PARAM_INT, 'Words in it'),
                ])),
                'hardwords' => new external_multiple_structure(new external_single_structure($word + [
                    'tries' => new external_value(PARAM_INT, 'Times answered'),
                ]), 'Words still in deck 1 after being answered, most tried first'),
            ])),
            'words' => new external_multiple_structure(new external_single_structure($word + [
                'hard' => new external_value(PARAM_INT, 'Students for whom it is still in deck 1'),
                'learning' => new external_value(PARAM_INT, 'Students with it in a middle deck'),
                'mastered' => new external_value(PARAM_INT, 'Students with it in the last deck'),
                'unseen' => new external_value(PARAM_INT, 'Students who have not answered it yet'),
                'tries' => new external_value(PARAM_INT, 'Answers given by the class'),
            ]), 'Hardest first'),
        ]);
    }
}
