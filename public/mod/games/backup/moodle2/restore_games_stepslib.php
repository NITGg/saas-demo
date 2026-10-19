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
 * Restore steps for mod_games.
 *
 * @package    mod_games
 * @subpackage backup-moodle2
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Read one Game activity back out of games.xml.
 */
class restore_games_activity_structure_step extends restore_activity_structure_step {

    /**
     * The paths this step handles.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('games', '/activity/games');
        if ($userinfo) {
            $paths[] = new restore_path_element('games_play', '/activity/games/plays/play');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the instance.
     *
     * @param array $data
     */
    protected function process_games($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('games', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one learner's tally.
     *
     * A round belongs to a person, so a row whose user did not come with the
     * backup is dropped rather than being attached to whoever happens to hold
     * that id on this site.
     *
     * @param array $data
     */
    protected function process_games_play($data) {
        global $DB;

        $data = (object) $data;
        $data->gamesid = $this->get_new_parentid('games');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if (empty($data->userid)) {
            return;
        }

        unset($data->id);
        $DB->insert_record('games_play', $data);
    }

    /**
     * Re-attach the intro's files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_games', 'intro', null);
    }
}
