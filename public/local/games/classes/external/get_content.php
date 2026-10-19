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

namespace local_games\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_games\content;
use local_games\registry;

/**
 * The material every game is built from, in one call.
 *
 * The banks are shared - six games read the same questions, four read the same
 * picture words - so they travel once and not with every game a child opens.
 * They only change when the language pack does, which is why the response
 * carries a revision an app can cache against.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_content extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gameid' => new external_value(PARAM_ALPHANUMEXT,
                'Which game to fetch the content of. Content belongs to one game; there are no shared banks.'),
        ]);
    }

    /**
     * Hand back one game's content and the string bag.
     *
     * @param string $gameid game slug
     * @return array
     */
    public static function execute(string $gameid): array {
        global $CFG;

        ['gameid' => $gameid] = self::validate_parameters(self::execute_parameters(), ['gameid' => $gameid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/games:play', $context);

        if (!registry::is_live($gameid)) {
            throw new \moodle_exception('errorunknowngame', 'local_games');
        }

        // Every slot travels, and the ones this game does not use come back
        // empty rather than missing: an app can read every field without first
        // checking which game it asked about, and the response shape never
        // changes.
        $out = [
            'lang'         => current_language(),
            'arabicdigits' => content::arabic_digits(),
            // Moodle's string revision. It moves whenever caches are purged -
            // which is when the shared strings may have changed - so an app can
            // keep the strings it already holds until this number does not match.
            // The content itself is not covered by it: content is edited in Game
            // control at any time, so it is fetched with the game.
            'revision'     => (int) ($CFG->langrev ?? 1),
            'shape'        => registry::shape_for($gameid),
            'strings'      => [],
        ];

        // The string bag always travels: it is what the game screens are
        // written in, and no app can draw a round without it.
        foreach (content::strings() as $key => $value) {
            $out['strings'][] = ['key' => $key, 'value' => $value];
        }

        return $out + content::payload($gameid);
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'lang'         => new external_value(PARAM_RAW, 'Language the banks came back in'),
            'arabicdigits' => new external_value(PARAM_BOOL, 'Show numbers in Arabic-Indic digits'),
            'revision'     => new external_value(PARAM_INT, 'Changes when the language pack may have; cache against it'),
            'shape'        => new external_value(PARAM_ALPHA,
                'Which shape this game rows take: questions, words, colours, sumrules, numberrules and so on'),
            'strings'      => new external_multiple_structure(
                new external_single_structure([
                    'key'   => new external_value(PARAM_RAW, 'String key, without the js_ prefix'),
                    'value' => new external_value(PARAM_RAW, 'Text in the current language'),
                ]),
                'Every message the game screens are written in'
            ),
            'words' => new external_multiple_structure(
                new external_single_structure([
                    'word'  => new external_value(PARAM_TEXT, 'The word'),
                    'emoji' => new external_value(PARAM_TEXT, 'Its picture'),
                    'clue'  => new external_value(PARAM_TEXT, 'A one-line clue'),
                ]),
                'Picture words - used by the letters games, memory and the jigsaw'
            ),
            'shopitems' => new external_multiple_structure(
                new external_single_structure([
                    'emoji' => new external_value(PARAM_TEXT, 'Its picture'),
                    'name'  => new external_value(PARAM_TEXT, 'What it is called'),
                ]),
                'The shop shelf, for Math Shop'
            ),
            'wordlist' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A word'),
                'The wider vocabulary Word Builder validates against'
            ),
            'quiz' => new external_multiple_structure(
                new external_single_structure([
                    'topic'    => new external_value(PARAM_TEXT, 'Wheel segment the question belongs to'),
                    'question' => new external_value(PARAM_TEXT, 'The question'),
                    'answer'   => new external_value(PARAM_TEXT, 'The right answer'),
                    'wrong'    => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'A wrong answer'),
                        'One to three wrong answers'
                    ),
                ]),
                'The question bank six games are built on'
            ),
            'truefalse' => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'The statement'),
                    'true' => new external_value(PARAM_BOOL, 'Whether it is true'),
                    'why'  => new external_value(PARAM_TEXT, 'The reason, shown after the answer'),
                ]),
                'Statements for True or False'
            ),
            'whoami' => new external_multiple_structure(
                new external_single_structure([
                    'answer' => new external_value(PARAM_TEXT, 'What is being described'),
                    'emoji'  => new external_value(PARAM_TEXT, 'Its picture, revealed with the answer'),
                    'clues'  => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'A clue'),
                        'Clues in the order they should be given'
                    ),
                ]),
                'Clue sets for Who Am I'
            ),
            'colours' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Colour name in the current language'),
                    'hex'  => new external_value(PARAM_TEXT, 'Its hex value, e.g. #e04b4b'),
                ]),
                'Colours for Colour Challenge'
            ),
            'sumrules' => new external_multiple_structure(
                new external_single_structure([
                    'op'   => new external_value(PARAM_ALPHA, 'plus, minus or times'),
                    'mina' => new external_value(PARAM_INT, 'First number, lower limit'),
                    'maxa' => new external_value(PARAM_INT, 'First number, upper limit'),
                    'minb' => new external_value(PARAM_INT, 'Second number, lower limit'),
                    'maxb' => new external_value(PARAM_INT, 'Second number, upper limit'),
                ]),
                'The arithmetic Math Race may generate. The game makes up a sum inside one of these each question.'
            ),
            'numberrules' => new external_multiple_structure(
                new external_single_structure([
                    'kind' => new external_value(PARAM_ALPHA, 'even, odd, greater, less, divisible or equals'),
                    'minn' => new external_value(PARAM_INT, 'Lower limit of the number in the rule; ignored by even and odd'),
                    'maxn' => new external_value(PARAM_INT, 'Upper limit of the number in the rule'),
                ]),
                'The rules Number Catcher and Balloon Pop may set. The game picks one and draws its number from the range.'
            ),
        ]);
    }
}
