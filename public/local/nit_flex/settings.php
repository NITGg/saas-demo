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
 * Admin navigation for local_nit_flex.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// One category for packages and live lessons (local_nit_lessons adds its pages to it).
if ($hassiteconfig || has_capability('local/nit_flex:managepackages', context_system::instance())) {
    $ADMIN->add('localplugins', new admin_category('local_nit_flex_cat',
        get_string('admincategory', 'local_nit_flex')));

    $ADMIN->add('local_nit_flex_cat', new admin_externalpage(
        'local_nit_flex_packages',
        get_string('managepackages', 'local_nit_flex'),
        new moodle_url('/local/nit_flex/manage_packages.php'),
        'local/nit_flex:managepackages'
    ));

    if ($hassiteconfig) {
        $settings = new admin_settingpage('local_nit_flex_settings', get_string('packagesettings', 'local_nit_flex'));
        $settings->add(new admin_setting_configtext('local_nit_flex/expiry_reminder_days',
            get_string('expiryreminderdays', 'local_nit_flex'),
            get_string('expiryreminderdays_desc', 'local_nit_flex'), 3, PARAM_INT, 4));
        $ADMIN->add('local_nit_flex_cat', $settings);
    }
}
