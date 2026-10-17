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
 * Admin tree entries for local_opspanel: a viewer page + a settings page.
 *
 * @package    local_opspanel
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // The viewer page (shows the embedded ops panel), under Site administration ▸ Server.
    $ADMIN->add('server', new admin_externalpage(
        'local_opspanel',
        get_string('pluginname', 'local_opspanel'),
        new moodle_url('/local/opspanel/index.php'),
        'moodle/site:config'
    ));

    // Settings: the ops panel URL (blank = this site + /ops/).
    $settings = new admin_settingpage('local_opspanel_settings', get_string('settings', 'local_opspanel'));
    $settings->add(new admin_setting_configtext(
        'local_opspanel/opsurl',
        get_string('opsurl', 'local_opspanel'),
        get_string('opsurl_desc', 'local_opspanel'),
        '',
        PARAM_URL
    ));
    $ADMIN->add('localplugins', $settings);
}
