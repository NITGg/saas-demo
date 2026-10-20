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

use local_games\registry;

/**
 * Everything "Game control" changes, and the one place that reads it back.
 *
 * Two kinds of change live here. What a game is called and how it is presented
 * is an override: no row in local_games_game means the entry behaves exactly as
 * registry.php declares it, and resetting a game is a DELETE rather than a copy
 * of the shipped values back over the top.
 *
 * A game's content is not an override. It is the content: every game owns its
 * rows in local_games_content, seeded once from the language pack on install, and
 * from then on the database is the only thing the games read. That is why there
 * is no "this bank still belongs to the language pack" state to explain, and no
 * button to take one over.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string Cache key holding every local_games_game row. */
    const CACHE_GAMES = 'games';

    /**
     * The plugin's override cache.
     *
     * @return \core_cache\cache_application|\core_cache\cache_session|\core_cache\cache_store
     */
    protected static function cache() {
        return \core_cache\cache::make('local_games', 'overrides');
    }

    /**
     * Throw away everything the cache holds.
     *
     * Called after every write to the catalogue. Content is not cached: it is
     * read once per game per page, and caching a table an admin is actively
     * editing buys a stale screen rather than a faster one.
     */
    public static function purge(): void {
        self::cache()->purge();
    }

    // ------------------------------------------------------------------
    // The catalogue.
    // ------------------------------------------------------------------

    /**
     * Every catalogue override, keyed by game slug.
     *
     * @return array<string, \stdClass> gameid => {name, description, enabled, level}
     */
    public static function get_overrides(): array {
        global $DB;

        $cache = self::cache();
        $cached = $cache->get(self::CACHE_GAMES);
        if ($cached !== false) {
            return $cached;
        }

        $rows = $DB->get_records('local_games_game', null, '',
            'gameid, name, description, enabled, level');
        $cache->set(self::CACHE_GAMES, $rows);

        return $rows;
    }

    /**
     * Save what an admin changed about one game.
     *
     * `name` and `description` arrive already carrying {mlang} markup, composed
     * by local_nit_mlang from the one input per language it draws over the field.
     * An empty one is stored as empty, which means "use the language pack".
     *
     * @param string $gameid registry slug
     * @param array $fields name, description, enabled, level
     */
    public static function save_game(string $gameid, array $fields): void {
        global $DB, $USER;

        $record = $DB->get_record('local_games_game', ['gameid' => $gameid]);

        $level = (int) ($fields['level'] ?? 0);
        $values = [
            'name'         => trim((string) ($fields['name'] ?? '')),
            'description'  => trim((string) ($fields['description'] ?? '')),
            'enabled'      => empty($fields['enabled']) ? 0 : 1,
            'level'        => ($level >= 1 && $level <= 3) ? $level : 0,
            'usermodified' => $USER->id,
            'timemodified' => time(),
        ];

        // A row that says nothing - the shipped name, the shipped level, still
        // enabled - is deleted rather than kept. It is how an admin puts a game
        // back: clear the name and save, and the card follows the language pack
        // again with no leftover row claiming otherwise.
        $says = $values['name'] !== '' || $values['description'] !== ''
            || $values['level'] > 0 || $values['enabled'] === 0;

        if ($record && !$says) {
            $DB->delete_records('local_games_game', ['id' => $record->id]);
        } else if ($record) {
            $DB->update_record('local_games_game', (object) (['id' => $record->id] + $values));
        } else if ($says) {
            $DB->insert_record('local_games_game', (object) (['gameid' => $gameid] + $values));
        }

        self::purge();
    }

    /**
     * Whether an admin has changed how this game presents itself.
     *
     * A row exists only when something was actually changed, so its presence is
     * the answer.
     *
     * @param string $gameid registry slug
     * @return bool
     */
    public static function is_customised(string $gameid): bool {
        return isset(self::get_overrides()[$gameid]);
    }

    /**
     * Switch one game on or off without touching anything else about it.
     *
     * @param string $gameid registry slug
     * @param bool $enabled
     */
    public static function set_enabled(string $gameid, bool $enabled): void {
        $current = self::get_overrides()[$gameid] ?? null;

        self::save_game($gameid, [
            'name'        => $current->name ?? '',
            'description' => $current->description ?? '',
            'enabled'     => $enabled ? 1 : 0,
            'level'       => $current->level ?? 0,
        ]);
    }

    // ------------------------------------------------------------------
    // Content.
    // ------------------------------------------------------------------

    /**
     * One content row, or null.
     *
     * @param int $id row id
     * @return \stdClass|null the row, with ->data decoded to an array
     */
    public static function get_row(int $id): ?\stdClass {
        global $DB;

        $record = $DB->get_record('local_games_content', ['id' => $id]);
        if (!$record) {
            return null;
        }

        $data = json_decode($record->data, true);
        $record->data = is_array($data) ? $data : [];

        return $record;
    }

    /**
     * Add a content row to a game, or update one.
     *
     * Only the fields the game's shape declares are stored, so a renamed or
     * removed field cannot leave a value behind that nothing reads and nobody
     * can see.
     *
     * @param int $id 0 to add
     * @param string $gameid game slug
     * @param array $data field name => value
     * @return int the row id
     */
    public static function save_row(int $id, string $gameid, array $data): int {
        global $DB, $USER;

        $clean = [];
        foreach (array_keys(registry::fields_for($gameid)) as $field) {
            $clean[$field] = (string) ($data[$field] ?? '');
        }

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $now = time();

        if ($id > 0 && ($record = $DB->get_record('local_games_content', ['id' => $id, 'gameid' => $gameid]))) {
            $DB->update_record('local_games_content', (object) [
                'id'           => $record->id,
                'data'         => $json,
                'usermodified' => $USER->id,
                'timemodified' => $now,
            ]);
            return (int) $record->id;
        }

        $last = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_games_content} WHERE gameid = :gameid',
            ['gameid' => $gameid]
        );

        return $DB->insert_record('local_games_content', (object) [
            'gameid'       => $gameid,
            'sortorder'    => $last + 10,
            'data'         => $json,
            'usermodified' => $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Delete one content row.
     *
     * @param int $id row id
     */
    public static function delete_row(int $id): void {
        global $DB;

        $DB->delete_records('local_games_content', ['id' => $id]);
    }

    /**
     * Delete several content rows of one game.
     *
     * Rows of any other game are left alone, so a hand-edited request cannot
     * reach past the game the admin is looking at.
     *
     * @param int[] $ids row ids
     * @param string $gameid game slug
     * @return int how many rows were deleted
     */
    public static function delete_rows(array $ids, string $gameid): int {
        global $DB;

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['gameid'] = $gameid;
        $where = "id $insql AND gameid = :gameid";

        $count = $DB->count_records_select('local_games_content', $where, $params);
        $DB->delete_records_select('local_games_content', $where, $params);

        return $count;
    }

    /**
     * Move one content row up or down among its neighbours.
     *
     * Sort orders are renumbered from scratch rather than swapped in place: rows
     * seeded in one batch and rows added later cannot be relied on to be evenly
     * spaced, and a renumber is a handful of updates on a table that holds a few
     * hundred rows at most.
     *
     * @param int $id row id
     * @param int $direction -1 up, +1 down
     */
    public static function move_row(int $id, int $direction): void {
        global $DB;

        $row = $DB->get_record('local_games_content', ['id' => $id], 'id, gameid');
        if (!$row) {
            return;
        }

        $rows = array_values($DB->get_records('local_games_content',
            ['gameid' => $row->gameid], 'sortorder, id', 'id'));

        $position = null;
        foreach ($rows as $index => $candidate) {
            if ((int) $candidate->id === $id) {
                $position = $index;
                break;
            }
        }

        if ($position === null) {
            return;
        }

        $target = $position + ($direction < 0 ? -1 : 1);
        if ($target < 0 || $target >= count($rows)) {
            return;
        }

        $moved = $rows[$position];
        $rows[$position] = $rows[$target];
        $rows[$target] = $moved;

        foreach ($rows as $index => $candidate) {
            $DB->set_field('local_games_content', 'sortorder', ($index + 1) * 10, ['id' => $candidate->id]);
        }
    }

    /**
     * Fill a game with the content it ships with.
     *
     * @param string $gameid game slug
     * @param bool $replace true to clear what is there first
     * @return int how many rows were written
     */
    public static function seed(string $gameid, bool $replace = false): int {
        global $DB, $USER;

        if ($replace) {
            $DB->delete_records('local_games_content', ['gameid' => $gameid]);
        } else if ($DB->record_exists('local_games_content', ['gameid' => $gameid])) {
            return 0;
        }

        $now = time();
        $order = 0;
        $records = [];

        foreach (defaults::for_game($gameid) as $row) {
            $records[] = (object) [
                'gameid'       => $gameid,
                'sortorder'    => ++$order * 10,
                'data'         => json_encode($row, JSON_UNESCAPED_UNICODE),
                'usermodified' => $USER->id ?? 0,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
        }

        if ($records) {
            $DB->insert_records('local_games_content', $records);
        }

        return count($records);
    }

    /**
     * Fill every game that has no content yet.
     *
     * Run on install and on upgrade. Games that already hold rows are left
     * alone, so an upgrade never overwrites what an admin has written.
     *
     * @return int how many rows were written in total
     */
    public static function seed_all(): int {
        $written = 0;

        foreach (array_keys(registry::get_defaults()) as $gameid) {
            $written += self::seed($gameid);
        }

        return $written;
    }

    // ------------------------------------------------------------------
    // Reporting.
    // ------------------------------------------------------------------

    /**
     * How much each game has actually been played, keyed by slug.
     *
     * @return array<string, \stdClass> gameid => {players, plays, points, bestscore}
     */
    public static function get_play_stats(): array {
        global $DB;

        return $DB->get_records_sql('
                SELECT gameid,
                       COUNT(DISTINCT userid) AS players,
                       COALESCE(SUM(plays), 0) AS plays,
                       COALESCE(SUM(points), 0) AS points,
                       COALESCE(MAX(bestscore), 0) AS bestscore
                  FROM {local_games_progress}
              GROUP BY gameid');
    }

    /**
     * How many content rows each game holds, keyed by slug.
     *
     * @return array<string, int>
     */
    public static function get_row_counts(): array {
        global $DB;

        $counts = $DB->get_records_sql(
            'SELECT gameid, COUNT(id) AS numrows FROM {local_games_content} GROUP BY gameid');

        return array_map(static function (\stdClass $row): int {
            return (int) $row->numrows;
        }, $counts);
    }
}
