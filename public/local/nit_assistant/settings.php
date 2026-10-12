<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin settings (phase 0: on/off, key, budget, models). The rest of the
 * assistant's settings arrive with their features.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_nit_assistant',
        get_string('settings', 'local_nit_assistant'),
        'local/nit_assistant:manage'
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_nit_assistant/enabled',
        get_string('enabled', 'local_nit_assistant'),
        get_string('enabled_desc', 'local_nit_assistant'),
        0
    ));

    $settings->add(new \local_nit_assistant\admin\setting_secret(
        'local_nit_assistant/apikey',
        get_string('apikey', 'local_nit_assistant'),
        get_string('apikey_desc', 'local_nit_assistant'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/dailybudget',
        get_string('dailybudget', 'local_nit_assistant'),
        get_string('dailybudget_desc', 'local_nit_assistant'),
        '0',
        PARAM_FLOAT,
        8
    ));

    $profiles = [];
    foreach (\local_nit_assistant\llm\profiles::NAMES as $p) {
        $profiles[$p] = get_string('profile_' . $p, 'local_nit_assistant');
    }
    $settings->add(new admin_setting_configselect(
        'local_nit_assistant/chatprofile',
        get_string('chatprofile', 'local_nit_assistant'),
        get_string('chatprofile_desc', 'local_nit_assistant'),
        'standard',
        $profiles
    ));
    $settings->add(new admin_setting_configselect(
        'local_nit_assistant/summaryprofile',
        get_string('summaryprofile', 'local_nit_assistant'),
        get_string('summaryprofile_desc', 'local_nit_assistant'),
        'fast',
        $profiles
    ));

    $ADMIN->add('localplugins', $settings);
}
