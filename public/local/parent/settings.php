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
 * Admin settings for local_parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_parent', get_string('settingsheading', 'local_parent'));
    $ADMIN->add('localplugins', $settings);

    // Country code prefixed to a bare local number so 010… and +2010… match.
    $settings->add(new admin_setting_configtext(
        'local_parent/countrycode',
        get_string('defaultcountrycode', 'local_parent'),
        get_string('defaultcountrycode_desc', 'local_parent'),
        '20',
        PARAM_ALPHANUM
    ));

    // Which student custom profile field holds the parent's phone number.
    $settings->add(new admin_setting_configtext(
        'local_parent/parentphonefield',
        get_string('parentphonefield', 'local_parent'),
        get_string('parentphonefield_desc', 'local_parent'),
        'parentphone',
        PARAM_ALPHANUMEXT
    ));
}
