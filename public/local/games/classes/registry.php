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

namespace local_games;

use local_games\admin\manager;

/**
 * The catalogue of games in the corner.
 *
 * The whole 22-game plan from "docs/Games Doc/games.md" lives here from day one,
 * so the hub page shows the full shape of the corner while only the finished
 * games are playable. Shipping a game = writing js/<slug>.js and flipping its
 * status to STATUS_LIVE.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {

    /** @var string Playable now. */
    const STATUS_LIVE = 'live';

    /** @var string On the plan, not built yet - the card renders as "coming soon". */
    const STATUS_SOON = 'soon';

    /**
     * Bump whenever a game's rules change - a setting below, a scoring
     * formula, how content becomes a round. Native clients compare it with the
     * version they were built against (see docs/apis/games-rules-spec.md).
     */
    const RULES_VERSION = 1;

    /**
     * The sections of the hub page, in display order.
     *
     * @return array<string, string> category key => emoji
     */
    public static function get_categories(): array {
        return [
            'numbers' => '🔢',
            'letters' => '🔤',
            'quiz'    => '❓',
            'memory'  => '🧩',
            'motion'  => '🏃',
        ];
    }

    /**
     * Every game in the corner, keyed by slug.
     *
     * Each entry carries:
     *  - num       the number it has in the design doc (keeps the two in sync)
     *  - emoji     the card face; no image assets to manage
     *  - category  which hub section it sits in
     *  - level     1..3, rendered as one to three stars
     *  - status    STATUS_LIVE or STATUS_SOON
     *  - badges    badge key => rule, checked server-side in progress::submit()
     *
     * A badge rule is a set of thresholds ANDed together:
     *  streak   longest run of correct answers in the round must be >= n
     *  correct  correct answers in the round must be >= n
     *  maxwrong wrong answers in the round must be <= n
     *  goal     the game's own goal, however it counts it, must be >= n
     *
     * @return array<string, array>
     */
    public static function get_games(): array {
        return self::apply_overrides(self::get_defaults());
    }

    /**
     * The catalogue exactly as this file declares it, before "Game control" has
     * had its say.
     *
     * The admin screen needs it to show what a setting would fall back to, and
     * "reset this game" needs it to have something to reset to.
     *
     * @return array<string, array>
     */
    public static function get_defaults(): array {
        return [
            // -- Numbers ------------------------------------------------------
            'math-race' => [
                'num' => 1, 'emoji' => '🔢', 'category' => 'numbers',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "sharp calculator" - 10 correct in a row.
                    'fast-calculator' => ['streak' => 10],
                ],
            ],
            'math-catcher' => [
                'num' => 2, 'emoji' => '🧮', 'category' => 'numbers',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "skilled hunter" - 20 correct with no mistake.
                    'sharp-hunter' => ['correct' => 20, 'maxwrong' => 0],
                ],
            ],
            'math-shop' => [
                'num' => 3, 'emoji' => '💰', 'category' => 'numbers',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "smart shopper" - 5 purchases, both sums right each time.
                    'smart-shopper' => ['correct' => 10, 'maxwrong' => 0],
                ],
            ],

            // -- Letters and words --------------------------------------------
            'letter-order' => [
                'num' => 4, 'emoji' => '🔤', 'category' => 'letters',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "letter king" - 15 words spelled right.
                    'letter-king' => ['correct' => 15],
                ],
            ],
            'word-builder' => [
                'num' => 5, 'emoji' => '🐝', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "word builder" - 10 words in one round. The rule counts
                    // words, not points: a long word scores more but still
                    // reports one correct event.
                    'word-builder' => ['correct' => 10],
                ],
            ],
            'match-connect' => [
                'num' => 6, 'emoji' => '🧩', 'category' => 'letters',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "sharp eye" - every board of the round with no mistake.
                    'sharp-eye' => ['correct' => 16, 'maxwrong' => 0],
                ],
            ],
            'crossword' => [
                'num' => 7, 'emoji' => '🔡', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "puzzle solver" - the whole grid with no wrong word.
                    'puzzle-solver' => ['correct' => 5, 'maxwrong' => 0],
                ],
            ],
            'word-search' => [
                'num' => 8, 'emoji' => '🔎', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "falcon eye" - every hidden word found.
                    'falcon-eye' => ['correct' => 6],
                ],
            ],
            'speak-words' => [
                'num' => 9, 'emoji' => '🎤', 'category' => 'letters',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => [
                    // "clear voice" - 10 words said correctly.
                    'clear-voice' => ['correct' => 10],
                ],
            ],

            // -- Quiz ----------------------------------------------------------
            // Every game in this section is built on the same question bank,
            // which is why the doc calls game 10 the important one.
            'quiz' => [
                'num' => 10, 'emoji' => '🧠', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['know-it-all' => ['correct' => 20]],
            ],
            'true-false' => [
                'num' => 11, 'emoji' => '✅', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['focused' => ['streak' => 15]],
            ],
            'xo-quiz' => [
                'num' => 12, 'emoji' => '⭕', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // Three matches won - the round is three matches long.
                'badges' => ['xo-champion' => ['goal' => 3]],
            ],
            'target-answer' => [
                'num' => 13, 'emoji' => '🎯', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['sharp-shot' => ['streak' => 10]],
            ],
            'balloon-pop' => [
                'num' => 14, 'emoji' => '🎈', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                'badges' => ['pop-master' => ['correct' => 25]],
            ],
            'wheel' => [
                'num' => 15, 'emoji' => '🏆', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // One right answer from each of the wheel's four topics.
                'badges' => ['encyclopedia' => ['goal' => 4]],
            ],
            'space-quiz' => [
                'num' => 16, 'emoji' => '🚀', 'category' => 'quiz',
                'level' => 2, 'status' => self::STATUS_LIVE,
                // Reaching the last planet.
                'badges' => ['astronaut' => ['goal' => 1]],
            ],
            'who-am-i' => [
                'num' => 17, 'emoji' => '🐶', 'category' => 'quiz',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // Five answers guessed from the first clue alone.
                'badges' => ['good-detective' => ['goal' => 5]],
            ],

            // -- Memory and thinking -------------------------------------------
            'memory-cards' => [
                'num' => 18, 'emoji' => '🃏', 'category' => 'memory',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // A full board cleared in under twenty flips.
                'badges' => ['strong-memory' => ['goal' => 1]],
            ],
            'puzzle' => [
                'num' => 19, 'emoji' => '🧱', 'category' => 'memory',
                'level' => 2, 'status' => self::STATUS_LIVE,
                // Finishing the sixteen-piece board.
                'badges' => ['picture-builder' => ['goal' => 1]],
            ],
            'find-difference' => [
                'num' => 20, 'emoji' => '🔍', 'category' => 'memory',
                'level' => 2, 'status' => self::STATUS_LIVE,
                'badges' => ['detective-eye' => ['correct' => 15, 'maxwrong' => 0]],
            ],
            'color-challenge' => [
                'num' => 21, 'emoji' => '🎨', 'category' => 'memory',
                'level' => 1, 'status' => self::STATUS_LIVE,
                // Every colour on the board answered correctly.
                'badges' => ['little-artist' => ['goal' => 1]],
            ],

            // -- Motion ---------------------------------------------------------
            'runner' => [
                'num' => 22, 'emoji' => '🏃', 'category' => 'motion',
                'level' => 2, 'status' => self::STATUS_LIVE,
                // A whole stage without losing a heart.
                'badges' => ['fast-runner' => ['goal' => 1]],
            ],

        ];
    }

    /**
     * Fold what an admin changed in "Game control" into the shipped catalogue.
     *
     * Three things can be overridden, and each one is expressed so that
     * "no opinion" is the value a fresh row would carry anyway: a level of 0
     * means keep the shipped level, a sortorder of 0 means keep the shipped
     * order. Only `enabled` is a real flag, and a disabled game keeps its entry
     * so the admin can find it again - it just stops being live.
     *
     * @param array<string, array> $games the shipped catalogue
     * @return array<string, array>
     */
    protected static function apply_overrides(array $games): array {
        $overrides = manager::get_overrides();
        if (!$overrides) {
            return $games;
        }

        foreach ($games as $id => $game) {
            if (!isset($overrides[$id])) {
                continue;
            }
            $override = $overrides[$id];

            if (!$override->enabled) {
                $games[$id]['status'] = self::STATUS_SOON;
                $games[$id]['disabled'] = true;
            }
            if ($override->level >= 1 && $override->level <= 3) {
                $games[$id]['level'] = (int) $override->level;
            }
        }

        // The order is this file's, and only this file's. It groups the games
        // the way the design doc numbers them, which is the order a child meets
        // them in; there is nothing an administrator gains by changing it.
        return $games;
    }

    /**
     * One game, or null when the slug is unknown.
     *
     * @param string $id game slug
     * @return array|null
     */
    public static function get_game(string $id): ?array {
        $games = self::get_games();
        if (!isset($games[$id])) {
            return null;
        }
        $game = $games[$id];
        $game['id'] = $id;
        return $game;
    }

    /**
     * Whether this slug is a finished, playable game an admin has not switched
     * off.
     *
     * @param string $id game slug
     * @return bool
     */
    public static function is_live(string $id): bool {
        $game = self::get_game($id);
        return $game !== null && $game['status'] === self::STATUS_LIVE;
    }

    /**
     * What this game is called.
     *
     * The admin's name when they have set one, otherwise the language pack's.
     * An admin-set name is a single value carrying {mlang} markup - the site's
     * standard way of holding one string in several languages - so it is
     * returned raw here and resolved by format_string() at the point of
     * display, exactly like a course or activity name.
     *
     * @param string $id game slug
     * @return string may contain {mlang} markup
     */
    public static function name(string $id): string {
        $override = manager::get_overrides()[$id] ?? null;

        if ($override !== null && trim((string) $override->name) !== '') {
            return $override->name;
        }

        return get_string('game_' . self::key($id), 'local_games');
    }

    /**
     * The one-line description under the hub card, admin override first.
     *
     * @param string $id game slug
     * @return string may contain {mlang} markup
     */
    public static function description(string $id): string {
        $override = manager::get_overrides()[$id] ?? null;

        if ($override !== null && trim((string) $override->description) !== '') {
            return $override->description;
        }

        return get_string('gamedesc_' . self::key($id), 'local_games');
    }

    /**
     * The name the corner ships with, composed across every installed language.
     *
     * This is what the name field is filled with when an admin has not set one:
     * the form shows the value that is actually in use rather than an empty box
     * with the real name printed underneath it.
     *
     * @param string $id game slug
     * @return string {mlang} markup
     */
    public static function shipped_name(string $id): string {
        return self::shipped_string('game_' . self::key($id));
    }

    /**
     * The description the corner ships with, composed across every language.
     *
     * @param string $id game slug
     * @return string {mlang} markup
     */
    public static function shipped_description(string $id): string {
        return self::shipped_string('gamedesc_' . self::key($id));
    }

    /**
     * One language string read in every installed language and composed into a
     * single multilingual value.
     *
     * @param string $key language string key
     * @return string {mlang} markup
     */
    protected static function shipped_string(string $key): string {
        $values = [];
        foreach (array_keys(mlang::languages()) as $lang) {
            $values[$lang] = get_string_manager()->get_string($key, 'local_games', null, $lang);
        }

        return mlang::build($values);
    }

    /**
     * The shapes a game's content can take: what one row of it is made of.
     *
     * A shape describes a row, not a bank. Two games with the same shape - the
     * five games that ask a question and offer answers, say - still own separate
     * rows: editing Space Trip's questions must not touch Tic-Tac-Toe's. The
     * shape is only how the row is spelled and how the form draws it.
     *
     * Each field carries:
     *  - type          how the form draws it and how the value is validated
     *  - translatable  whether it is a display string; those are held as one
     *                  value with {mlang} markup and drawn by local_nit_mlang as
     *                  one input per installed language
     *  - required      whether a row is rejected without it
     *
     * @return array<string, array>
     */
    public static function shapes(): array {
        $answers = [
            'question' => ['type' => 'text', 'translatable' => true, 'required' => true],
            'answer'   => ['type' => 'text', 'translatable' => true, 'required' => true],
            'wrong1'   => ['type' => 'text', 'translatable' => true, 'required' => true],
            'wrong2'   => ['type' => 'text', 'translatable' => true],
            'wrong3'   => ['type' => 'text', 'translatable' => true],
        ];

        return [
            // A question and the answers offered under it.
            'questions' => ['fields' => $answers],

            // The same, plus which segment of the wheel it belongs to. Only the
            // Question Wheel needs it, because only the wheel asks for a topic.
            'topicquestions' => ['fields' => ['topic' => [
                'type' => 'select', 'options' => 'topics', 'required' => true,
            ]] + $answers],

            // A word, a picture for it and a one-line clue.
            'words' => ['fields' => [
                'word'  => ['type' => 'text', 'translatable' => true, 'required' => true],
                'emoji' => ['type' => 'emoji', 'required' => true],
                'clue'  => ['type' => 'text', 'translatable' => true, 'required' => true],
            ]],

            // Just words. Word Builder checks what the child spells against
            // these; they are never shown, so they need no picture and no clue.
            'vocabulary' => ['fields' => [
                'word' => ['type' => 'text', 'translatable' => true, 'required' => true],
            ]],

            // A sentence, whether it is true, and why.
            'statements' => ['fields' => [
                'statement' => ['type' => 'text', 'translatable' => true, 'required' => true],
                'istrue'    => ['type' => 'bool', 'required' => true],
                'why'       => ['type' => 'text', 'translatable' => true, 'required' => true],
            ]],

            // An answer and the three clues that lead to it, in reveal order.
            'clues' => ['fields' => [
                'answer' => ['type' => 'text', 'translatable' => true, 'required' => true],
                'emoji'  => ['type' => 'emoji', 'required' => true],
                'clue1'  => ['type' => 'text', 'translatable' => true, 'required' => true],
                'clue2'  => ['type' => 'text', 'translatable' => true, 'required' => true],
                'clue3'  => ['type' => 'text', 'translatable' => true, 'required' => true],
            ]],

            'colours' => ['fields' => [
                'colourname' => ['type' => 'text', 'translatable' => true, 'required' => true],
                'hex'        => ['type' => 'hex', 'required' => true],
            ]],

            'shopitems' => ['fields' => [
                'emoji'    => ['type' => 'emoji', 'required' => true],
                'itemname' => ['type' => 'text', 'translatable' => true, 'required' => true],
            ]],

            // -- The three games that make up their own material ---------------
            //
            // Math Race invents a sum every question and the two catching games
            // invent a rule every few catches. Handing the admin a fixed list of
            // sums instead would make the game finite and repetitive, so what
            // they edit is the recipe: each row is one kind of question the game
            // is allowed to ask, and the game still generates endlessly inside
            // the rows it is given.

            'sumrules' => ['fields' => [
                'op'   => ['type' => 'select', 'options' => 'ops', 'required' => true],
                'mina' => ['type' => 'int', 'required' => true, 'default' => 2],
                'maxa' => ['type' => 'int', 'required' => true, 'default' => 10],
                'minb' => ['type' => 'int', 'required' => true, 'default' => 2],
                'maxb' => ['type' => 'int', 'required' => true, 'default' => 9],
            ]],

            'numberrules' => ['fields' => [
                'kind' => ['type' => 'select', 'options' => 'kinds', 'required' => true],
                'minn' => ['type' => 'int', 'default' => 0],
                'maxn' => ['type' => 'int', 'default' => 0],
            ]],
        ];
    }

    /**
     * Which shape one game's content takes.
     *
     * Every game has exactly one, so every game has something for an admin to
     * edit and no game shares a row with another.
     *
     * @param string $id game slug
     * @return string shape key
     */
    public static function shape_for(string $id): string {
        $map = [
            'math-race'       => 'sumrules',
            'math-catcher'    => 'numberrules',
            'balloon-pop'     => 'numberrules',
            'math-shop'       => 'shopitems',

            'letter-order'    => 'words',
            'match-connect'   => 'words',
            'crossword'       => 'words',
            'word-search'     => 'words',
            'speak-words'     => 'words',
            'memory-cards'    => 'words',
            'puzzle'          => 'words',
            'find-difference' => 'words',
            'word-builder'    => 'vocabulary',

            'quiz'            => 'questions',
            'xo-quiz'         => 'questions',
            'target-answer'   => 'questions',
            'space-quiz'      => 'questions',
            'runner'          => 'questions',
            'wheel'           => 'topicquestions',

            'true-false'      => 'statements',
            'who-am-i'        => 'clues',
            'color-challenge' => 'colours',
        ];

        return $map[$id] ?? 'questions';
    }

    /**
     * The numbers one game's round is played with.
     *
     * One source for both players: the web shell hands these to the game file
     * (api.setting) and local_games_get_content hands them to the mobile app,
     * so the two cannot drift. Each game file keeps the same value as its
     * fallback. Times are seconds unless the key ends in "ms"; speeds are as
     * the spec for that game describes them.
     *
     * @param string $id game slug
     * @return array<string, int|float> setting key => value; empty for an unknown slug
     */
    public static function settings_for(string $id): array {
        $settings = [
            'math-race'       => ['target' => 12, 'maxattempts' => 26, 'timestart' => 13, 'timefloor' => 7],
            'math-catcher'    => ['lives' => 3, 'target' => 25, 'ruleevery' => 6, 'fallstart' => 95,
                'fallmax' => 200, 'spawnstartms' => 1150, 'spawnminms' => 620, 'basketspeed' => 460],
            'math-shop'       => ['trips' => 5],
            'letter-order'    => ['words' => 15],
            'word-builder'    => ['sets' => 3, 'size' => 7, 'minwords' => 4, 'maxtargets' => 6],
            'match-connect'   => ['pairs' => 4, 'boards' => 4, 'dragthreshold' => 8],
            'crossword'       => ['size' => 11, 'target' => 5, 'pad' => 16],
            'word-search'     => ['size' => 9, 'words' => 6],
            'speak-words'     => ['words' => 10],
            'quiz'            => ['questions' => 20],
            'true-false'      => ['statements' => 18],
            'xo-quiz'         => ['matches' => 3],
            'target-answer'   => ['questions' => 12, 'crossslow' => 15, 'crossfast' => 10, 'readingpause' => 2.2],
            'balloon-pop'     => ['target' => 25, 'ruleevery' => 8, 'riseslow' => 8, 'risefast' => 4.5,
                'spawnstartms' => 900, 'spawnminms' => 480, 'columns' => 5],
            'wheel'           => ['spins' => 10, 'friction' => 0.972, 'minflick' => 240, 'maxflick' => 1800],
            'space-quiz'      => ['stage1steps' => 5, 'stage2steps' => 7, 'stage3steps' => 9],
            'who-am-i'        => ['riddles' => 8, 'worth1' => 10, 'worth2' => 6, 'worth3' => 3],
            'memory-cards'    => ['pairs' => 6, 'flipbudget' => 20],
            'puzzle'          => ['board1' => 2, 'board2' => 3, 'board3' => 4],
            'find-difference' => ['cols' => 4, 'rows' => 4, 'differences' => 5, 'boards' => 3],
            'color-challenge' => ['choices' => 4],
            'runner'          => ['hearts' => 3, 'target' => 10, 'lanes' => 3, 'travelslow' => 4.2,
                'travelfast' => 2.6, 'wavestartms' => 4600, 'waveminms' => 3000],
        ];

        return $settings[$id] ?? [];
    }

    /**
     * The field definitions of one game's content rows.
     *
     * @param string $id game slug
     * @return array<string, array> field name => definition
     */
    public static function fields_for(string $id): array {
        $shape = self::shape_for($id);
        return self::shapes()[$shape]['fields'];
    }

    /**
     * The fixed option lists a `select` field can draw from.
     *
     * They are keys rather than words: the label is a language string, so the
     * value stored in the database stays the same in every language.
     *
     * @param string $set option-set name
     * @return string[]
     */
    public static function options(string $set): array {
        $sets = [
            // The Question Wheel's four segments, drawn in js/wheel.js.
            'topics' => ['math', 'science', 'language', 'animals'],
            // The arithmetic Math Race is allowed to ask.
            'ops'    => ['plus', 'minus', 'times'],
            // The rules the two catching games can set the child.
            'kinds'  => ['even', 'odd', 'greater', 'less', 'divisible', 'equals'],
        ];

        return $sets[$set] ?? [];
    }
    /**
     * Lang-string suffix for a slug: dashes are not valid in string keys.
     *
     * @param string $id game slug, e.g. math-race
     * @return string e.g. math_race
     */
    public static function key(string $id): string {
        return str_replace('-', '_', $id);
    }
}
