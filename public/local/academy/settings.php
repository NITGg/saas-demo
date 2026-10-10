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

    // Student registration (/local/academy/register.php + the app's register_student).
    $settings->add(new admin_setting_heading('local_academy/registrationheading',
        get_string('registration_heading', 'local_academy'), ''));
    $settings->add(new admin_setting_configcheckbox('local_academy/registration',
        get_string('registration_enabled', 'local_academy'), get_string('registration_enabled_desc', 'local_academy'), 1));

    // Years, study systems and their divisions — the options of the course and
    // student dropdown fields (see \local_academy\local\academic_structure).
    $academic = new admin_settingpage('local_academy_academic', get_string('academic_page', 'local_academy'));
    if ($ADMIN->fulltree) {
        $academic->add(new \local_academy\admin_setting_academicstructure(
            'local_academy/' . \local_academy\local\academic_structure::CONFIG,
            get_string('academic_structure', 'local_academy'),
            get_string('academic_structure_desc', 'local_academy'),
            json_encode(\local_academy\local\academic_structure::defaults(), JSON_UNESCAPED_UNICODE)
        ));
    }
    $ADMIN->add('localplugins', $academic);

    // "Prevent messaging between students" sits on core's Messaging settings page,
    // next to "Allow site-wide messaging" (see \local_academy\local\student_messaging).
    $messagespage = $ADMIN->locate('messages');
    if ($messagespage instanceof admin_settingpage) {
        $messagespage->add(new admin_setting_configcheckbox('local_academy/' . \local_academy\local\student_messaging::CONFIG,
            get_string('studentmessaging', 'local_academy'), get_string('studentmessaging_desc', 'local_academy'), 0));
    }

    // "Site pages manager": the content of the Bassthalk-style pages that are
    // not HTML blocks (the footer), one tab each. The settings belong to theme_nit.
    if (class_exists('\theme_nit\local\sitepages_settings')) {
        $ADMIN->add('localplugins', \theme_nit\local\sitepages_settings::page($ADMIN->fulltree));
    }
}

// Site administration → Local plugins: keep our pages in the agreed order.
if (class_exists('\local_academy\local\admin_order')) {
    \local_academy\local\admin_order::apply($ADMIN);
}
