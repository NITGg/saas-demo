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

namespace mod_games;

use local_games\content as corner;
use local_games\registry;

/**
 * A Game activity's own content, and which content a round is played from.
 *
 * Rows are stored exactly like the corner's (local_games_content): JSON keyed by
 * the field names of the game's shape, display strings carrying {mlang} markup.
 * That is what lets the corner's form, validation and payload builder serve the
 * activity unchanged.
 *
 * Where a round's content comes from is the activity's `contentmode`:
 *  - MODE_DEFAULT: the corner's content, as edited in Game control;
 *  - MODE_OWN: this activity's rows - or the corner's when it has none usable
 *    in the current language, so a half-finished activity never shows an empty
 *    game;
 *  - MODE_BOTH: this activity's rows followed by the corner's.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {

    /** @var int Play the corner's default content. */
    const MODE_DEFAULT = 0;

    /** @var int Play this activity's content; the default when it has none. */
    const MODE_OWN = 1;

    /** @var int Play this activity's content and the default together. */
    const MODE_BOTH = 2;

    /**
     * Fewest usable rows each game needs to play a proper round. Below this the
     * content page warns the teacher. Games not listed need one row.
     *
     * Taken from how each game builds a round (docs/apis/games-rules-spec.md).
     */
    const MINIMUM = [
        'math-shop'       => 3,
        'word-builder'    => 10,
        'match-connect'   => 4,
        'crossword'       => 5,
        'word-search'     => 6,
        'memory-cards'    => 6,
        'puzzle'          => 4,
        'find-difference' => 2,
        'wheel'           => 4,
        'who-am-i'        => 4,
        'color-challenge' => 4,
    ];

    /**
     * Every content mode, for a menu.
     *
     * @return array<int, string> mode => label
     */
    public static function mode_options(): array {
        return [
            self::MODE_DEFAULT => get_string('contentmode_default', 'mod_games'),
            self::MODE_OWN     => get_string('contentmode_own', 'mod_games'),
            self::MODE_BOTH    => get_string('contentmode_both', 'mod_games'),
        ];
    }

    /**
     * Fewest usable rows one game needs.
     *
     * @param string $gameid game slug
     * @return int
     */
    public static function minimum(string $gameid): int {
        return self::MINIMUM[$gameid] ?? 1;
    }

    // ------------------------------------------------------------------
    // Rows.
    // ------------------------------------------------------------------

    /**
     * One activity's rows exactly as stored, in display order.
     *
     * @param int $gamesid games.id
     * @return \stdClass[] each with ->id, ->sortorder and ->data as an array
     */
    public static function rows(int $gamesid): array {
        global $DB;

        $out = [];
        foreach ($DB->get_records('games_content', ['gamesid' => $gamesid], 'sortorder, id') as $record) {
            $data = json_decode($record->data, true);
            $record->data = is_array($data) ? $data : [];
            $out[] = $record;
        }

        return $out;
    }

    /**
     * How many rows one activity holds.
     *
     * @param int $gamesid games.id
     * @return int
     */
    public static function count_rows(int $gamesid): int {
        global $DB;
        return $DB->count_records('games_content', ['gamesid' => $gamesid]);
    }

    /**
     * One row of one activity, or null.
     *
     * @param int $id row id
     * @param int $gamesid the activity it must belong to
     * @return \stdClass|null the row, with ->data decoded
     */
    public static function get_row(int $id, int $gamesid): ?\stdClass {
        global $DB;

        $record = $DB->get_record('games_content', ['id' => $id, 'gamesid' => $gamesid]);
        if (!$record) {
            return null;
        }

        $data = json_decode($record->data, true);
        $record->data = is_array($data) ? $data : [];

        return $record;
    }

    /**
     * Add a row to an activity, or update one of its rows.
     *
     * Only the fields the game's shape declares are kept.
     *
     * @param int $id 0 to add
     * @param \stdClass $game the games record
     * @param array $data field name => value
     * @return int the row id
     */
    public static function save_row(int $id, \stdClass $game, array $data): int {
        global $DB, $USER;

        $clean = [];
        foreach (array_keys(registry::fields_for($game->gameid)) as $field) {
            $clean[$field] = (string) ($data[$field] ?? '');
        }

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $now = time();

        if ($id > 0 && ($record = $DB->get_record('games_content', ['id' => $id, 'gamesid' => $game->id]))) {
            $DB->update_record('games_content', (object) [
                'id'           => $record->id,
                'data'         => $json,
                'usermodified' => $USER->id,
                'timemodified' => $now,
            ]);
            return (int) $record->id;
        }

        $last = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {games_content} WHERE gamesid = :gamesid',
            ['gamesid' => $game->id]
        );

        return $DB->insert_record('games_content', (object) [
            'gamesid'      => $game->id,
            'sortorder'    => $last + 10,
            'data'         => $json,
            'usermodified' => $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Delete one row of one activity.
     *
     * @param int $id row id
     * @param int $gamesid games.id
     */
    public static function delete_row(int $id, int $gamesid): void {
        global $DB;
        $DB->delete_records('games_content', ['id' => $id, 'gamesid' => $gamesid]);
    }

    /**
     * Delete every row of one activity.
     *
     * @param int $gamesid games.id
     */
    public static function delete_all(int $gamesid): void {
        global $DB;
        $DB->delete_records('games_content', ['gamesid' => $gamesid]);
    }

    /**
     * Move one row up or down among the activity's rows.
     *
     * @param int $id row id
     * @param int $gamesid games.id
     * @param int $direction -1 up, +1 down
     */
    public static function move_row(int $id, int $gamesid, int $direction): void {
        global $DB;

        $rows = array_values($DB->get_records('games_content', ['gamesid' => $gamesid], 'sortorder, id', 'id'));

        $position = null;
        foreach ($rows as $index => $candidate) {
            if ((int) $candidate->id === $id) {
                $position = $index;
                break;
            }
        }

        $target = $position === null ? -1 : $position + ($direction < 0 ? -1 : 1);
        if ($target < 0 || $target >= count($rows)) {
            return;
        }

        [$rows[$position], $rows[$target]] = [$rows[$target], $rows[$position]];

        foreach ($rows as $index => $candidate) {
            $DB->set_field('games_content', 'sortorder', ($index + 1) * 10, ['id' => $candidate->id]);
        }
    }

    /**
     * Copy the corner's current content for this game into the activity.
     *
     * @param \stdClass $game the games record
     * @param bool $replace true to delete the activity's rows first; otherwise the
     *        copies are added after them
     * @return int how many rows were copied
     */
    public static function copy_defaults(\stdClass $game, bool $replace = false): int {
        global $DB, $USER;

        if ($replace) {
            self::delete_all((int) $game->id);
        }

        $order = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {games_content} WHERE gamesid = :gamesid',
            ['gamesid' => $game->id]
        );

        $now = time();
        $records = [];
        foreach (corner::rows($game->gameid) as $row) {
            $order += 10;
            $records[] = (object) [
                'gamesid'      => $game->id,
                'sortorder'    => $order,
                'data'         => json_encode($row->data, JSON_UNESCAPED_UNICODE),
                'usermodified' => $USER->id,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
        }

        if ($records) {
            $DB->insert_records('games_content', $records);
        }

        return count($records);
    }

    /**
     * Keep an activity's rows only if the newly chosen game reads the same shape.
     *
     * A question row means nothing to a colour game; rows of the same shape (Quiz
     * to Tic-Tac-Toe, say) stay, so a teacher switching between question games
     * keeps their questions.
     *
     * @param int $gamesid games.id
     * @param string $oldgameid slug before the change
     * @param string $newgameid slug after it
     * @return bool true when rows were deleted
     */
    public static function game_changed(int $gamesid, string $oldgameid, string $newgameid): bool {
        if ($oldgameid === $newgameid || registry::shape_for($oldgameid) === registry::shape_for($newgameid)) {
            return false;
        }
        if (!self::count_rows($gamesid)) {
            return false;
        }
        self::delete_all($gamesid);
        return true;
    }

    // ------------------------------------------------------------------
    // What a round is played from.
    // ------------------------------------------------------------------

    /**
     * The activity's own rows that can be played in one language.
     *
     * @param \stdClass $game the games record
     * @param string|null $lang defaults to the current language
     * @return array[] resolved rows
     */
    public static function own_resolved(\stdClass $game, ?string $lang = null): array {
        return corner::for_game($game->gameid, $lang, self::rows((int) $game->id));
    }

    /**
     * Whether a round right now would use the activity's own rows.
     *
     * @param \stdClass $game the games record
     * @param string|null $lang defaults to the current language
     * @return bool
     */
    public static function uses_own(\stdClass $game, ?string $lang = null): bool {
        return (int) $game->contentmode !== self::MODE_DEFAULT && self::own_resolved($game, $lang) !== [];
    }

    /**
     * The rows a round of this activity is played from, resolved for one language.
     *
     * @param \stdClass $game the games record
     * @param string|null $lang defaults to the current language
     * @return array[]
     */
    public static function resolved(\stdClass $game, ?string $lang = null): array {
        $mode = (int) ($game->contentmode ?? self::MODE_DEFAULT);
        if ($mode === self::MODE_DEFAULT) {
            return corner::for_game($game->gameid, $lang);
        }

        $own = self::own_resolved($game, $lang);
        if ($mode === self::MODE_BOTH) {
            return array_merge($own, corner::for_game($game->gameid, $lang));
        }

        // MODE_OWN: nothing of the teacher's in this language yet, so the class
        // still gets a playable game rather than an empty one.
        return $own ?: corner::for_game($game->gameid, $lang);
    }

    /**
     * The browser / app payload for a round of this activity.
     *
     * @param \stdClass $game the games record
     * @param string|null $lang defaults to the current language
     * @return array same structure as local_games\content::payload()
     */
    public static function payload(\stdClass $game, ?string $lang = null): array {
        return corner::payload($game->gameid, $lang, self::resolved($game, $lang));
    }
}
