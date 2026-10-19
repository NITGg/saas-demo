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

/**
 * What the games are actually made of.
 *
 * Every game owns its own content. Two games built the same way - the five that
 * ask a question and offer answers, the eight that show a word and a picture -
 * still hold separate rows, so editing one game never reaches into another. That
 * is the whole reason this is a table keyed by game rather than a set of shared
 * banks: a shared bank cannot be edited safely, because nobody editing it can
 * see who else is reading it.
 *
 * A row is stored as JSON keyed by the field names of the game's shape
 * ({@see registry::shapes()}), and every display string inside it carries
 * {mlang} markup, so one row serves every language.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {

    /**
     * One game's content, ready for the browser: {mlang} resolved, and rows that
     * were never written in this language dropped.
     *
     * @param string $gameid game slug
     * @param string|null $lang defaults to the current language
     * @return array[] one associative array per row
     */
    public static function for_game(string $gameid, ?string $lang = null): array {
        $lang = $lang ?? current_language();
        $fields = registry::fields_for($gameid);

        $out = [];
        foreach (self::rows($gameid) as $row) {
            $resolved = [];
            $usable = true;

            foreach ($fields as $field => $definition) {
                $value = $row->data[$field] ?? '';

                if (empty($definition['translatable'])) {
                    $resolved[$field] = $value;
                    continue;
                }

                // A required display string that was never written in this
                // language takes the whole row out. The word banks are not
                // translations of each other - the English list teaches English
                // spelling - so an English-only row has to disappear in Arabic
                // rather than fall back and ask for a word in the wrong language.
                if (!empty($definition['required']) && !mlang::has_language($value, $lang)) {
                    $usable = false;
                    break;
                }

                $resolved[$field] = mlang::display($value, $lang);
            }

            if ($usable) {
                $out[] = $resolved;
            }
        }

        return $out;
    }

    /**
     * One game's rows exactly as stored, in display order.
     *
     * @param string $gameid game slug
     * @return \stdClass[] each with ->id, ->sortorder and ->data as an array
     */
    public static function rows(string $gameid): array {
        global $DB;

        $records = $DB->get_records('local_games_content', ['gameid' => $gameid], 'sortorder, id');

        $out = [];
        foreach ($records as $record) {
            $data = json_decode($record->data, true);
            if (!is_array($data)) {
                // A row that will not decode is a row nobody can play or fix.
                // Skipping the value keeps the game running, and the admin screen
                // shows it as blank - which is the signal to delete it.
                $data = [];
            }
            $record->data = $data;
            $out[] = $record;
        }

        return $out;
    }

    /**
     * How many rows one game holds.
     *
     * @param string $gameid game slug
     * @return int
     */
    public static function count_rows(string $gameid): int {
        global $DB;
        return $DB->count_records('local_games_content', ['gameid' => $gameid]);
    }

    // ------------------------------------------------------------------
    // The shape the browser expects.
    //
    // js/shell.js hands each game its material under a fixed name, so the game
    // files did not have to change when the material moved out of the language
    // pack and into a table. Only the slot belonging to this game is filled: a
    // game has one shape, and shipping every bank to every game was 35 KB of
    // config for material that 21 of the 22 games never looked at.
    // ------------------------------------------------------------------

    /**
     * The whole browser-side content payload for one game.
     *
     * @param string $gameid game slug
     * @param string|null $lang defaults to the current language
     * @return array<string, array> slot name => rows
     */
    public static function payload(string $gameid, ?string $lang = null): array {
        $payload = [
            'words'       => [],
            'shopitems'   => [],
            'wordlist'    => [],
            'quiz'        => [],
            'truefalse'   => [],
            'whoami'      => [],
            'colours'     => [],
            'sumrules'    => [],
            'numberrules' => [],
        ];

        $rows = self::for_game($gameid, $lang);

        switch (registry::shape_for($gameid)) {
            case 'words':
                $payload['words'] = array_map(static function (array $row): array {
                    return ['word' => $row['word'], 'emoji' => $row['emoji'], 'clue' => $row['clue']];
                }, $rows);
                break;

            case 'vocabulary':
                $payload['wordlist'] = array_values(array_filter(array_map(static function (array $row): string {
                    return $row['word'];
                }, $rows)));
                break;

            case 'questions':
            case 'topicquestions':
                $payload['quiz'] = array_map(static function (array $row): array {
                    return [
                        'topic'    => $row['topic'] ?? '',
                        'question' => $row['question'],
                        'answer'   => $row['answer'],
                        'wrong'    => array_values(array_filter([
                            $row['wrong1'] ?? '', $row['wrong2'] ?? '', $row['wrong3'] ?? '',
                        ], static function (string $option): bool {
                            return $option !== '';
                        })),
                    ];
                }, $rows);
                break;

            case 'statements':
                $payload['truefalse'] = array_map(static function (array $row): array {
                    return [
                        'text' => $row['statement'],
                        'true' => (string) $row['istrue'] === '1',
                        'why'  => $row['why'],
                    ];
                }, $rows);
                break;

            case 'clues':
                $payload['whoami'] = array_map(static function (array $row): array {
                    return [
                        'answer' => $row['answer'],
                        'emoji'  => $row['emoji'],
                        'clues'  => [$row['clue1'], $row['clue2'], $row['clue3']],
                    ];
                }, $rows);
                break;

            case 'colours':
                $payload['colours'] = array_map(static function (array $row): array {
                    return ['name' => $row['colourname'], 'hex' => $row['hex']];
                }, $rows);
                break;

            case 'shopitems':
                $payload['shopitems'] = array_map(static function (array $row): array {
                    return ['emoji' => $row['emoji'], 'name' => $row['itemname']];
                }, $rows);
                break;

            case 'sumrules':
                $payload['sumrules'] = array_map(static function (array $row): array {
                    return [
                        'op'   => $row['op'],
                        'mina' => (int) $row['mina'], 'maxa' => (int) $row['maxa'],
                        'minb' => (int) $row['minb'], 'maxb' => (int) $row['maxb'],
                    ];
                }, $rows);
                break;

            case 'numberrules':
                $payload['numberrules'] = array_map(static function (array $row): array {
                    return [
                        'kind' => $row['kind'],
                        'minn' => (int) ($row['minn'] ?? 0),
                        'maxn' => (int) ($row['maxn'] ?? 0),
                    ];
                }, $rows);
                break;
        }

        return $payload;
    }

    // ------------------------------------------------------------------
    // Reading the language pack, which is now only ever a source of defaults.
    // ------------------------------------------------------------------

    /**
     * Turn one of the pipe-delimited language-pack tables into rows.
     *
     * These tables are no longer what the games read. They are what a fresh
     * install and "restore the default content" copy from, once, into the rows
     * the game then actually plays from.
     *
     * @param string $key lang string holding the table
     * @param int $columns how many fields each row must have
     * @param int|null $max the most fields to keep; defaults to $columns
     * @param string|null $lang which language to read; defaults to the current one
     * @return array[] one array per row
     */
    public static function parse_table(string $key, int $columns, ?int $max = null, ?string $lang = null): array {
        $rows = [];
        $max = $max ?? $columns;

        foreach (self::shipped_rows($key, $lang) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < $columns) {
                continue;
            }
            $row = array_slice($parts, 0, $max);
            while (count($row) < $max) {
                $row[] = '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The raw lines of one language-pack table.
     *
     * @param string $key lang string holding the table
     * @param string|null $lang defaults to the current language
     * @return string[] one unparsed line per row, blank lines dropped
     */
    public static function shipped_rows(string $key, ?string $lang = null): array {
        // The line break is spelled out rather than written as \R on purpose.
        // Without the /u modifier PCRE walks bytes, and in that mode \R also
        // matches the single byte 0x85 - which is the second byte of the Arabic
        // letter meem (U+0645, D9 85) and of every other Arabic letter ending in
        // 85. The bank came back cut through the middle of its meems, and the
        // resulting half characters made json_encode fail outright, taking every
        // game's config with it. This pattern can only ever match ASCII breaks.
        $lines = preg_split('/\r\n|\r|\n/',
            get_string_manager()->get_string($key, 'local_games', null, $lang));

        return array_values(array_filter(array_map('trim', $lines), static function (string $line): bool {
            return $line !== '';
        }));
    }

    /**
     * Every string the game side needs, in one bag, with the js_ prefix off.
     *
     * The games never build a sentence out of fragments - each message is its
     * own string, so a translator can move the words around freely.
     *
     * @return array<string, string>
     */
    public static function strings(): array {
        $out = [];
        foreach (get_string_manager()->load_component_strings('local_games', current_language()) as $key => $value) {
            if (strpos($key, 'js_') === 0) {
                $out[substr($key, 3)] = $value;
            }
        }
        $out['playagain'] = get_string('playagain', 'local_games');
        $out['backtohub'] = get_string('backtohub', 'local_games');

        return $out;
    }

    /**
     * Whether numbers should be shown in Arabic-Indic digits.
     *
     * The maths itself always runs on real numbers; this is presentation only.
     *
     * @return bool
     */
    public static function arabic_digits(): bool {
        return strpos(current_language(), 'ar') === 0;
    }
}
