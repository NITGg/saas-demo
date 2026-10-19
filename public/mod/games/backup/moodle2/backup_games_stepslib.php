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
 * Backup steps for mod_games.
 *
 * @package    mod_games
 * @subpackage backup-moodle2
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Write one Game activity into games.xml.
 */
class backup_games_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup file.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // `gameid` travels as the registry slug it is, not as a mapped id: the
        // catalogue is code, so the same slug means the same game on any site
        // that has the corner installed. A site that does not have that game
        // gets an activity that says so - see view.php - which is a better
        // restore than one that silently points at a different game.
        $games = new backup_nested_element('games', ['id'], [
            'name', 'intro', 'introformat', 'gameid', 'showhublink',
            'completionplays', 'completionscore', 'timecreated', 'timemodified',
        ]);

        $plays = new backup_nested_element('plays');

        $play = new backup_nested_element('play', ['id'], [
            'userid', 'plays', 'points', 'bestscore', 'beststreak',
            'timecreated', 'timemodified',
        ]);

        $games->add_child($plays);
        $plays->add_child($play);

        $games->set_source_table('games', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $play->set_source_table('games_play', ['gamesid' => backup::VAR_PARENTID]);
        }

        $play->annotate_ids('user', 'userid');

        $games->annotate_files('mod_games', 'intro', null);

        return $this->prepare_activity_structure($games);
    }
}
