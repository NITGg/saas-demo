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
 * Admin tree: Site administration → Users → Teacher applications (applications, application
 * fields, settings).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$systemcontext = context_system::instance();
if ($hassiteconfig || has_capability('local/nit_tutors:review', $systemcontext)) {
    $ADMIN->add('users', new admin_category('local_nit_tutors', get_string('admincategory', 'local_nit_tutors')));
    $ADMIN->add('local_nit_tutors', new admin_externalpage('local_nit_tutors_applications',
        get_string('applications', 'local_nit_tutors'),
        new moodle_url('/local/nit_tutors/admin/applications.php'), 'local/nit_tutors:review'));
    $ADMIN->add('local_nit_tutors', new admin_externalpage('local_nit_tutors_fields',
        get_string('applicationfields', 'local_nit_tutors'),
        new moodle_url('/local/nit_tutors/admin/fields.php'), 'local/nit_tutors:manage'));
}

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_tutors_settings', get_string('settings', 'local_nit_tutors'));
    $ADMIN->add('local_nit_tutors', $settings);

    if ($ADMIN->fulltree) {
        $p = 'local_nit_tutors/';
        $settings->add(new admin_setting_heading($p . 'h_apply', get_string('set_h_apply', 'local_nit_tutors'), ''));
        $settings->add(new admin_setting_configcheckbox($p . 'requireapproval',
            get_string('set_requireapproval', 'local_nit_tutors'), get_string('set_requireapproval_desc', 'local_nit_tutors'), 1));
        $settings->add(new admin_setting_configcheckbox($p . 'requirevideo',
            get_string('set_requirevideo', 'local_nit_tutors'), '', 1));
        $settings->add(new admin_setting_configtext($p . 'minqualifications',
            get_string('set_minqualifications', 'local_nit_tutors'), '', 1, PARAM_INT));
        $settings->add(new admin_setting_configtext($p . 'videomaxmb',
            get_string('set_videomaxmb', 'local_nit_tutors'), get_string('set_videomaxmb_desc', 'local_nit_tutors'), 200, PARAM_INT));
        $settings->add(new admin_setting_configcheckbox($p . 'allowreapply',
            get_string('set_allowreapply', 'local_nit_tutors'), '', 1));

        $settings->add(new admin_setting_heading($p . 'h_badge', get_string('set_h_badge', 'local_nit_tutors'),
            get_string('set_h_badge_desc', 'local_nit_tutors')));
        $settings->add(new admin_setting_configtext($p . 'badge_minrating',
            get_string('set_badge_minrating', 'local_nit_tutors'), '', '4.8', PARAM_FLOAT));
        $settings->add(new admin_setting_configtext($p . 'badge_minreviews',
            get_string('set_badge_minreviews', 'local_nit_tutors'), '', 10, PARAM_INT));
        $settings->add(new admin_setting_configtext($p . 'badge_minlessons',
            get_string('set_badge_minlessons', 'local_nit_tutors'), '', 30, PARAM_INT));
        $settings->add(new admin_setting_configtext($p . 'badge_maxabsence',
            get_string('set_badge_maxabsence', 'local_nit_tutors'), '', '2', PARAM_FLOAT));
    }
}
