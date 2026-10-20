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
 * The cards to play, shuffled.
 *
 * deck >= 1: one Leitner round — the caller's cards in that deck. A student gets them only
 *            when the deck is due (error "decklocked" otherwise, with the wait in the
 *            message); a teacher always gets them, to preview.
 * deck = 0:  free play — every card of the set, no progress is kept.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_cards extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Flash Card Set'),
            'deck' => new external_value(PARAM_INT, 'Deck to play (1 = hardest), or 0 for free play', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The cards.
     *
     * @param int $cmid
     * @param int $deck
     * @return array
     */
    public static function execute(int $cmid, int $deck = 0): array {
        global $DB, $USER;

        ['cmid' => $cmid, 'deck' => $deck] = self::validate_parameters(self::execute_parameters(),
            ['cmid' => $cmid, 'deck' => $deck]);
        $api = new api($cmid);
        self::validate_context($api->context);
        $api->require_open();
        $f = $api->flashcard;

        $cards = [];
        if ($deck === 0) {
            if (!($f->models & FLASHCARD_MODEL_FREEUSE)) {
                throw new \moodle_exception('errorfreeplayoff', 'flashcard');
            }
            require_capability('mod/flashcard:canplayfree', $api->context);
            foreach ($DB->get_records('flashcard_deckdata', ['flashcardid' => $f->id]) as $entry) {
                $cards[] = $api->card($entry);
            }
        } else {
            if (!($f->models & FLASHCARD_MODEL_LEITNER)) {
                throw new \moodle_exception('errorleitneroff', 'flashcard');
            }
            if ($deck < 1 || $deck > $f->decks) {
                throw new \invalid_parameter_exception('deck must be between 1 and ' . $f->decks);
            }
            flashcard_initialize($f, $USER->id);
            $wait = flashcard_deck_wait($f, $deck, $USER->id);
            if ($wait && !$api->can_manage()) {
                throw new \moodle_exception('decklocked', 'flashcard', '', format_time($wait));
            }
            $rows = $DB->get_records_sql("SELECT c.id AS cardid, d.*
                                            FROM {flashcard_card} c
                                            JOIN {flashcard_deckdata} d ON d.id = c.entryid
                                           WHERE c.flashcardid = ? AND c.userid = ? AND c.deck = ?",
                [$f->id, $USER->id, $deck]);
            foreach ($rows as $row) {
                $cardid = (int) $row->cardid;
                $cards[] = $api->card($row, $cardid, $deck);
            }
        }
        shuffle($cards);

        return ['deck' => $deck, 'cards' => $cards];
    }

    /**
     * One face of a card.
     *
     * @param string $which
     * @return external_single_structure
     */
    protected static function side_structure(string $which): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'text, image, sound, imageandsound or video'),
            'text' => new external_value(PARAM_RAW, 'HTML, for a text face ("" otherwise)'),
            'imageurl' => new external_value(PARAM_URL, 'Image file (add ?token=), "" if none'),
            'soundurl' => new external_value(PARAM_URL, 'Sound file (add ?token=), "" if none'),
            'videourl' => new external_value(PARAM_URL, 'Video file (add ?token=), "" if none'),
        ], 'The ' . $which);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deck' => new external_value(PARAM_INT, 'The deck asked for (0 = free play)'),
            'cards' => new external_multiple_structure(new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'The caller\'s card id: send it to submit_answer (0 in free play)'),
                'entryid' => new external_value(PARAM_INT, 'The word\'s id in the set'),
                'deck' => new external_value(PARAM_INT, 'Deck the card is in (0 in free play)'),
                'question' => self::side_structure('front face, shown first'),
                'answer' => self::side_structure('back face, shown when the card is turned over'),
            ])),
        ]);
    }
}
