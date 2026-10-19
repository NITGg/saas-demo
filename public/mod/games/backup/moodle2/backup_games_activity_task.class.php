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
 * Backup task for mod_games.
 *
 * @package    mod_games
 * @subpackage backup-moodle2
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/games/backup/moodle2/backup_games_stepslib.php');

/**
 * The steps of one complete backup of a Game activity.
 */
class backup_games_activity_task extends backup_activity_task {

    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * The one step: write the instance into games.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_games_activity_structure_step('games_structure', 'games.xml'));
    }

    /**
     * Encode links to this module's scripts so they survive a restore.
     *
     * @param string $content HTML that may contain links to this activity
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $content = preg_replace(
            '/(' . $base . '\/mod\/games\/index.php\?id\=)([0-9]+)/',
            '$@GAMESINDEX*$2@$', $content);

        $content = preg_replace(
            '/(' . $base . '\/mod\/games\/view.php\?id\=)([0-9]+)/',
            '$@GAMESVIEWBYID*$2@$', $content);

        return $content;
    }
}
