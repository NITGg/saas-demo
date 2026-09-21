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
 * Course player options (local_academy).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_academy', get_string('pluginname', 'local_academy'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_academy/playerheading',
        get_string('player_heading', 'local_academy'), get_string('player_heading_desc', 'local_academy')));

    $settings->add(new admin_setting_configcheckbox('local_academy/player_markcomplete',
        get_string('player_markcomplete', 'local_academy'), get_string('player_markcomplete_desc', 'local_academy'), 1));

    $settings->add(new admin_setting_configcheckbox('local_academy/player_lockorder',
        get_string('player_lockorder', 'local_academy'), get_string('player_lockorder_desc', 'local_academy'), 0));
}
