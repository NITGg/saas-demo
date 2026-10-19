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
 * Restore task for mod_games.
 *
 * @package    mod_games
 * @subpackage backup-moodle2
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/games/backup/moodle2/restore_games_stepslib.php');

/**
 * The steps of one complete restore of a Game activity.
 */
class restore_games_activity_task extends restore_activity_task {

    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * The one step: read the instance back out of games.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_games_activity_structure_step('games_structure', 'games.xml'));
    }

    /**
     * File areas this module owns.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('games', ['intro'], 'games'),
        ];
    }

    /**
     * Turn the encoded links back into real ones.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('GAMESVIEWBYID', '/mod/games/view.php?id=$1', 'course_module'),
            new restore_decode_rule('GAMESINDEX', '/mod/games/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log entries this module writes, for the restore's log mapping.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('games', 'add', 'view.php?id={course_module}', '{games}'),
            new restore_log_rule('games', 'update', 'view.php?id={course_module}', '{games}'),
            new restore_log_rule('games', 'view', 'view.php?id={course_module}', '{games}'),
        ];
    }
}
