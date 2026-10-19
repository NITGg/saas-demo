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

/**
 * Schema upgrades for the Games Corner.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run the upgrade steps a site needs to reach the current version.
 *
 * @param int $oldversion the version the site is upgrading from
 * @return bool
 */
function xmldb_local_games_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082902) {

        // The catalogue overrides. Content tables from this step are replaced by
        // the next one, so only this table is created here.

        $table = new xmldb_table('local_games_game');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('gameid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('level', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('gameid', XMLDB_INDEX_UNIQUE, ['gameid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082902, 'local', 'games');
    }

    if ($oldversion < 2026082903) {

        // Content becomes the game's own, and multilingual the way the rest of
        // the site is.
        //
        // What went: local_games_item held one row per bank per language, so the
        // six games built on the question bank shared its rows and every screen
        // needed a language switch to say which copy was being edited. Both were
        // wrong. Editing a shared row changes games the admin cannot see from
        // where they are standing, and the site already has one way to hold a
        // value in several languages - {mlang} markup, with local_nit_mlang
        // drawing an input per language over the field.
        //
        // What replaced it: one row per game, one value per field, {mlang} inside
        // it. local_games_gamestr goes the same way for the same reason, its two
        // columns folding into local_games_game.
        //
        // Nothing is migrated across. The old tables only ever held a copy of the
        // language pack plus whatever an admin had edited in the days since this
        // shipped, and the new rows are seeded from that same language pack - so
        // a site upgrading here loses nothing it did not have before, and gains
        // the same material as its own, per game.

        $table = new xmldb_table('local_games_game');

        $field = new xmldb_field('name', XMLDB_TYPE_TEXT, null, null, null, null, null, 'gameid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Carry over the site default language's name and description, which is
        // the one an admin is most likely to have written.
        $old = new xmldb_table('local_games_gamestr');
        if ($dbman->table_exists($old)) {
            $default = get_config('core', 'lang') ?: 'en';
            $strings = $DB->get_records('local_games_gamestr', ['lang' => $default]);
            foreach ($strings as $string) {
                $row = $DB->get_record('local_games_game', ['gameid' => $string->gameid]);
                if (!$row) {
                    continue;
                }
                $row->name = $string->name;
                $row->description = $string->description;
                $DB->update_record('local_games_game', $row);
            }
            $dbman->drop_table($old);
        }

        $old = new xmldb_table('local_games_item');
        if ($dbman->table_exists($old)) {
            $dbman->drop_table($old);
        }

        $table = new xmldb_table('local_games_content');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('gameid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('gameidsortorder', XMLDB_INDEX_NOTUNIQUE, ['gameid', 'sortorder']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Give every game the material it ships with, as its own.
        \local_games\admin\manager::seed_all();
        \local_games\admin\manager::purge();

        upgrade_plugin_savepoint(true, 2026082903, 'local', 'games');
    }

    if ($oldversion < 2026082904) {

        // Drop the catalogue's display position.
        //
        // It was briefly editable, first as a number on the game's form and then
        // as arrows on the catalogue, and neither earned its place: the order in
        // registry.php is the order the design doc numbers the games in, which is
        // the order a child meets them in. A screen for changing it was a control
        // nobody needed and a column that had to be kept true.

        $table = new xmldb_table('local_games_game');
        $field = new xmldb_field('sortorder');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Reordering wrote a row for every game so it had somewhere to put a
        // position. Those rows now say nothing at all, and a row that says
        // nothing is what marks a game as changed - so they go.
        $DB->delete_records_select('local_games_game',
            "COALESCE(name, '') = '' AND COALESCE(description, '') = '' AND enabled = 1 AND level = 0");

        \local_games\admin\manager::purge();

        upgrade_plugin_savepoint(true, 2026082904, 'local', 'games');
    }

    return true;
}
