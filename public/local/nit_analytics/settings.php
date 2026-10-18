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
 * Admin settings: tracker ids, and the sign-ups-by-source report.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_analytics', get_string('pluginname', 'local_nit_analytics'));
    if ($ADMIN->fulltree) {
        foreach (['ga4_id', 'gtm_id', 'meta_pixel_id'] as $key) {
            $settings->add(new admin_setting_configtext('local_nit_analytics/' . $key,
                get_string($key, 'local_nit_analytics'), get_string($key . '_desc', 'local_nit_analytics'),
                '', PARAM_ALPHANUMEXT, 24));
        }
        $settings->add(new admin_setting_configcheckbox('local_nit_analytics/requireconsent',
            get_string('requireconsent', 'local_nit_analytics'), get_string('requireconsent_desc', 'local_nit_analytics'), 1));
        $settings->add(new admin_setting_configcheckbox('local_nit_analytics/trackadmins',
            get_string('trackadmins', 'local_nit_analytics'), get_string('trackadmins_desc', 'local_nit_analytics'), 0));
    }
    $ADMIN->add('localplugins', $settings);
}

$ADMIN->add('reports', new admin_externalpage(
    'local_nit_analytics_report',
    get_string('funnelreport', 'local_nit_analytics'),
    new moodle_url('/local/nit_analytics/report.php'),
    'local/nit_analytics:viewreport'
));
