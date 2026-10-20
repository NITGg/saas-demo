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

/**
 * Open a Flash Card Set: its settings and the caller's deck table (the activity's first
 * screen). Also marks the activity viewed for completion.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_flashcard extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Flash Card Set'),
        ]);
    }

    /**
     * The activity and the caller's decks.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $api = new api($cmid);
        self::validate_context($api->context);
        $api->require_open();

        (new \completion_info($api->course))->set_module_viewed($api->cm);

        $f = $api->flashcard;
        return [
            'cmid' => (int) $api->cm->id,
            'courseid' => (int) $api->course->id,
            'name' => format_string($f->name, true, ['context' => $api->context]),
            'intro' => format_module_intro('flashcard', $f, $api->cm->id),
            'leitner' => (bool) ($f->models & FLASHCARD_MODEL_LEITNER),
            'freeplay' => (bool) ($f->models & FLASHCARD_MODEL_FREEUSE)
                && has_capability('mod/flashcard:canplayfree', $api->context),
            'questiontype' => api::MEDIA[(int) $f->questionsmediatype] ?? 'text',
            'answertype' => api::MEDIA[(int) $f->answersmediatype] ?? 'text',
            'flipped' => (bool) $f->flipdeck,
            'autoplaysound' => (bool) $f->audiostart,
            'deckcount' => (int) $f->decks,
            'totalcards' => $DB->count_records('flashcard_deckdata', ['flashcardid' => $f->id]),
            'opensat' => (int) $f->starttime,
            'closesat' => (int) $f->endtime,
            'canmanage' => $api->can_manage(),
            'decks' => $api->decks((int) $USER->id),
        ];
    }

    /**
     * The deck table structure, shared with submit_answer.
     *
     * @return external_multiple_structure
     */
    public static function decks_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'deck' => new external_value(PARAM_INT, 'Deck number, 1 = hardest'),
            'title' => new external_value(PARAM_TEXT, 'Deck title in the requested language'),
            'count' => new external_value(PARAM_INT, 'Cards of the caller in this deck'),
            'state' => new external_value(PARAM_ALPHA, 'due = can be played now, waiting = reviewed, opens later, empty'),
            'waitseconds' => new external_value(PARAM_INT, 'Seconds until it opens (0 when due or empty)'),
            'opensat' => new external_value(PARAM_INT, 'Unix time it opens (0 when due or empty)'),
        ]));
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'intro' => new external_value(PARAM_RAW, 'Description, HTML'),
            'leitner' => new external_value(PARAM_BOOL, 'Leitner play is on'),
            'freeplay' => new external_value(PARAM_BOOL, 'Free play is on and the caller may use it'),
            'questiontype' => new external_value(PARAM_ALPHA, 'Front face media: text, image, sound, imageandsound, video'),
            'answertype' => new external_value(PARAM_ALPHA, 'Back face media: text, image, sound, imageandsound, video'),
            'flipped' => new external_value(PARAM_BOOL, 'The teacher flipped the cards (already applied to every card)'),
            'autoplaysound' => new external_value(PARAM_BOOL, 'Start sound/video as soon as a face is shown'),
            'deckcount' => new external_value(PARAM_INT, 'Number of decks, 2 to 4'),
            'totalcards' => new external_value(PARAM_INT, 'Cards in the set'),
            'opensat' => new external_value(PARAM_INT, 'Unix time the activity opens, 0 = no limit'),
            'closesat' => new external_value(PARAM_INT, 'Unix time the activity closes, 0 = no limit'),
            'canmanage' => new external_value(PARAM_BOOL, 'The caller is a teacher: may open any deck and read the report'),
            'decks' => self::decks_structure(),
        ]);
    }
}
