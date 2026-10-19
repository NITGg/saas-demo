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

namespace local_games\admin;

use local_games\content;
use local_games\mlang;
use local_games\registry;

/**
 * The content a game starts life with.
 *
 * Everything a game plays now lives in local_games_content, but the corner still
 * ships with material worth having on day one, and an admin who has edited a
 * game into a corner needs a way back. Both come from here: this class turns the
 * language pack's tables into that game's own rows, once, and can do it again on
 * request.
 *
 * Composing the {mlang} value is the interesting part. The English and Arabic
 * tables are two separate lists, not a translated pair, so they cannot simply be
 * zipped together by position:
 *
 *  - the picture words join on their emoji, which is the same in both languages
 *    and is what makes 🐟/fish and 🐟/سمكة one row rather than two;
 *  - the colours join on their hex value, for the same reason;
 *  - the question, statement, clue and shop tables are written as parallel
 *    translations and are joined by position, which is checked to be safe by
 *    the two lists being the same length;
 *  - the Word Builder vocabularies are joined not at all - an English word list
 *    and an Arabic word list teach different spelling, so each language's words
 *    become rows of their own, tagged only with that language, and simply do not
 *    appear when the other language is being played.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defaults {

    /**
     * The default rows for one game, ready to insert.
     *
     * @param string $gameid game slug
     * @return array[] one associative array per row, keyed by the shape's fields
     */
    public static function for_game(string $gameid): array {
        switch (registry::shape_for($gameid)) {
            case 'words':
                return self::words();
            case 'vocabulary':
                return self::vocabulary();
            case 'questions':
                return self::questions(false);
            case 'topicquestions':
                return self::questions(true);
            case 'statements':
                return self::statements();
            case 'clues':
                return self::clues();
            case 'colours':
                return self::colours();
            case 'shopitems':
                return self::shopitems();
            case 'sumrules':
                return self::sumrules();
            case 'numberrules':
                return self::numberrules();
        }

        return [];
    }

    /**
     * The languages the language pack is read in, site default first.
     *
     * @return string[]
     */
    protected static function langs(): array {
        return array_keys(mlang::languages());
    }

    /**
     * Read one language-pack table in every installed language.
     *
     * @param string $key lang string holding the table
     * @param int $columns fields a row must have
     * @param int|null $max fields to keep
     * @return array<string, array[]> language code => rows
     */
    protected static function tables(string $key, int $columns, ?int $max = null): array {
        $out = [];
        foreach (self::langs() as $lang) {
            $out[$lang] = content::parse_table($key, $columns, $max, $lang);
        }
        return $out;
    }

    /**
     * Picture words, joined across languages on their emoji.
     *
     * @return array[]
     */
    protected static function words(): array {
        $tables = self::tables('wordbank', 3);

        // emoji => [lang => row]
        $byemoji = [];
        $order = [];
        foreach ($tables as $lang => $rows) {
            foreach ($rows as $row) {
                $emoji = $row[1];
                if ($emoji === '') {
                    continue;
                }
                if (!isset($byemoji[$emoji])) {
                    $byemoji[$emoji] = [];
                    $order[] = $emoji;
                }
                $byemoji[$emoji][$lang] = $row;
            }
        }

        $out = [];
        foreach ($order as $emoji) {
            $word = [];
            $clue = [];
            foreach ($byemoji[$emoji] as $lang => $row) {
                $word[$lang] = $row[0];
                $clue[$lang] = $row[2];
            }
            $out[] = [
                'word'  => mlang::build($word),
                'emoji' => $emoji,
                'clue'  => mlang::build($clue),
            ];
        }

        return $out;
    }

    /**
     * Word Builder's vocabulary: every language's own list, kept apart.
     *
     * @return array[]
     */
    protected static function vocabulary(): array {
        $out = [];
        $seen = [];

        foreach (self::langs() as $lang) {
            // The wider list, plus the words the picture bank already teaches -
            // the game accepts both, so both have to be in what it checks against.
            $words = preg_split('/\s+/', implode(' ', content::shipped_rows('wordlist', $lang)));
            foreach (content::parse_table('wordbank', 3, 3, $lang) as $row) {
                $words[] = $row[0];
            }

            foreach ($words as $word) {
                $word = trim($word);
                if ($word === '' || isset($seen[$lang . '|' . $word])) {
                    continue;
                }
                $seen[$lang . '|' . $word] = true;
                // Tagged with one language only: an English word must not be
                // offered to a child spelling in Arabic.
                $out[] = ['word' => mlang::build([$lang => $word])];
            }
        }

        return $out;
    }

    /**
     * The question bank, joined by position.
     *
     * @param bool $withtopic keep the topic column (the Question Wheel's segments)
     * @return array[]
     */
    protected static function questions(bool $withtopic): array {
        $tables = self::tables('quizbank', 4, 6);

        return self::zip($tables, static function (array $byrow, array $langs) use ($withtopic): array {
            $row = [
                'question' => mlang::build(self::column($byrow, 1)),
                'answer'   => mlang::build(self::column($byrow, 2)),
                'wrong1'   => mlang::build(self::column($byrow, 3)),
                'wrong2'   => mlang::build(self::column($byrow, 4)),
                'wrong3'   => mlang::build(self::column($byrow, 5)),
            ];
            if ($withtopic) {
                // The topic is a key, not a word: the same in every language.
                $first = reset($byrow);
                $row = ['topic' => $first[0]] + $row;
            }
            return $row;
        });
    }

    /**
     * True-or-false statements, joined by position.
     *
     * @return array[]
     */
    protected static function statements(): array {
        $tables = self::tables('tfbank', 3);

        return self::zip($tables, static function (array $byrow): array {
            $first = reset($byrow);
            return [
                'statement' => mlang::build(self::column($byrow, 0)),
                'istrue'    => $first[1] === '1' ? '1' : '0',
                'why'       => mlang::build(self::column($byrow, 2)),
            ];
        });
    }

    /**
     * Who Am I, joined by position.
     *
     * @return array[]
     */
    protected static function clues(): array {
        $tables = self::tables('whoami', 5);

        return self::zip($tables, static function (array $byrow): array {
            $first = reset($byrow);
            return [
                'answer' => mlang::build(self::column($byrow, 0)),
                'emoji'  => $first[1],
                'clue1'  => mlang::build(self::column($byrow, 2)),
                'clue2'  => mlang::build(self::column($byrow, 3)),
                'clue3'  => mlang::build(self::column($byrow, 4)),
            ];
        });
    }

    /**
     * Colours, joined on the hex value.
     *
     * @return array[]
     */
    protected static function colours(): array {
        $tables = self::tables('colourbank', 2);

        $byhex = [];
        $order = [];
        foreach ($tables as $lang => $rows) {
            foreach ($rows as $row) {
                $hex = strtolower($row[1]);
                if ($hex === '') {
                    continue;
                }
                if (!isset($byhex[$hex])) {
                    $byhex[$hex] = [];
                    $order[] = $hex;
                }
                $byhex[$hex][$lang] = $row[0];
            }
        }

        $out = [];
        foreach ($order as $hex) {
            $out[] = ['colourname' => mlang::build($byhex[$hex]), 'hex' => $hex];
        }

        return $out;
    }

    /**
     * The shop shelf, joined on the emoji.
     *
     * @return array[]
     */
    protected static function shopitems(): array {
        $tables = self::tables('shopitems', 2);

        $byemoji = [];
        $order = [];
        foreach ($tables as $lang => $rows) {
            foreach ($rows as $row) {
                $emoji = $row[0];
                if ($emoji === '') {
                    continue;
                }
                if (!isset($byemoji[$emoji])) {
                    $byemoji[$emoji] = [];
                    $order[] = $emoji;
                }
                $byemoji[$emoji][$lang] = $row[1];
            }
        }

        $out = [];
        foreach ($order as $emoji) {
            $out[] = ['emoji' => $emoji, 'itemname' => mlang::build($byemoji[$emoji])];
        }

        return $out;
    }

    /**
     * Math Race's sums.
     *
     * These reproduce what js/math-race.js used to hard-code: easy addition and
     * subtraction to start, wider ranges after, and small multiplication. They
     * are rows now, so a teacher who wants the class on times tables only can
     * delete the other two.
     *
     * @return array[]
     */
    protected static function sumrules(): array {
        return [
            ['op' => 'plus',  'mina' => 2, 'maxa' => 10, 'minb' => 2, 'maxb' => 9],
            ['op' => 'plus',  'mina' => 2, 'maxa' => 25, 'minb' => 2, 'maxb' => 20],
            ['op' => 'minus', 'mina' => 5, 'maxa' => 18, 'minb' => 1, 'maxb' => 9],
            ['op' => 'minus', 'mina' => 5, 'maxa' => 40, 'minb' => 1, 'maxb' => 20],
            ['op' => 'times', 'mina' => 2, 'maxa' => 6,  'minb' => 2, 'maxb' => 6],
            ['op' => 'times', 'mina' => 2, 'maxa' => 10, 'minb' => 2, 'maxb' => 10],
        ];
    }

    /**
     * The rules the two catching games set the child.
     *
     * Same reasoning as the sums: this is what the two game files used to invent
     * for themselves, written down so it can be changed. `minn` and `maxn` are
     * the range the number in the rule is drawn from, and are ignored by the two
     * rules that carry no number.
     *
     * @return array[]
     */
    protected static function numberrules(): array {
        return [
            ['kind' => 'even',      'minn' => 0,  'maxn' => 0],
            ['kind' => 'odd',       'minn' => 0,  'maxn' => 0],
            ['kind' => 'greater',   'minn' => 10, 'maxn' => 30],
            ['kind' => 'less',      'minn' => 10, 'maxn' => 30],
            ['kind' => 'divisible', 'minn' => 2,  'maxn' => 5],
            ['kind' => 'equals',    'minn' => 8,  'maxn' => 20],
        ];
    }

    // ------------------------------------------------------------------
    // Helpers.
    // ------------------------------------------------------------------

    /**
     * Join tables that are parallel translations, row by row.
     *
     * Lists of different lengths are joined as far as the shortest and the extra
     * rows are then added on their own, tagged with the one language they were
     * written in - so nothing is thrown away and nothing is mispaired.
     *
     * @param array<string, array[]> $tables language code => rows
     * @param callable $build receives [lang => row] and the language list, returns one row
     * @return array[]
     */
    protected static function zip(array $tables, callable $build): array {
        $langs = array_keys($tables);
        $lengths = array_map('count', $tables);
        $shared = $lengths ? min($lengths) : 0;

        $out = [];
        for ($i = 0; $i < $shared; $i++) {
            $byrow = [];
            foreach ($tables as $lang => $rows) {
                $byrow[$lang] = $rows[$i];
            }
            $out[] = $build($byrow, $langs);
        }

        foreach ($tables as $lang => $rows) {
            for ($i = $shared; $i < count($rows); $i++) {
                $out[] = $build([$lang => $rows[$i]], [$lang]);
            }
        }

        return $out;
    }

    /**
     * One column of a joined row, as language code => value.
     *
     * @param array<string, array> $byrow language code => the row in that language
     * @param int $index which field
     * @return array<string, string>
     */
    protected static function column(array $byrow, int $index): array {
        $out = [];
        foreach ($byrow as $lang => $row) {
            $value = $row[$index] ?? '';
            if ($value !== '') {
                $out[$lang] = $value;
            }
        }
        return $out;
    }
}
