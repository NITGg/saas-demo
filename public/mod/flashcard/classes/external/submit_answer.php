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
use core_external\external_single_structure;
use core_external\external_value;
use mod_flashcard\local\api;

/**
 * Record one Leitner answer: "I got it" (known = 1) or "I missed" (known = 0).
 *
 * Accepted only while the card is still in the deck the app is playing, so sending the
 * same answer twice (a double tap, a retry after a timeout) moves a card at most once:
 * the second call comes back with accepted = false.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_answer extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the Flash Card Set'),
            'deck' => new external_value(PARAM_INT, 'The deck being played (the card\'s deck from get_cards)'),
            'cardid' => new external_value(PARAM_INT, 'cardid from get_cards'),
            'known' => new external_value(PARAM_BOOL, '1 = I got it, 0 = I missed'),
        ]);
    }

    /**
     * Record the answer.
     *
     * @param int $cmid
     * @param int $deck
     * @param int $cardid
     * @param bool $known
     * @return array
     */
    public static function execute(int $cmid, int $deck, int $cardid, bool $known): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            ['cmid' => $cmid, 'deck' => $deck, 'cardid' => $cardid, 'known' => $known]);
        $api = new api($params['cmid']);
        self::validate_context($api->context);
        $api->require_open();

        $result = $api->answer((int) $USER->id, $params['deck'], $params['cardid'], (bool) $params['known']);
        $result['decks'] = $api->decks((int) $USER->id);
        return $result;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'accepted' => new external_value(PARAM_BOOL, 'false = this card had already left that deck; nothing changed'),
            'deck' => new external_value(PARAM_INT, 'The deck the card is in now'),
            'decks' => get_flashcard::decks_structure(),
        ]);
    }
}
