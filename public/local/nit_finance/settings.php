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
 * Admin navigation for local_nit_finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Managers (local/nit_finance:manage) run wallets and codes without being
// site admins; only the split setting needs full site configuration rights.
if ($hassiteconfig || has_capability('local/nit_finance:manage', context_system::instance())) {
    $ADMIN->add('localplugins', new admin_category('local_nit_finance_cat',
        get_string('financecategory', 'local_nit_finance')));

    if ($hassiteconfig) {
        $settings = new admin_settingpage('local_nit_finance_settings', get_string('earningsettings', 'local_nit_finance'));
        $settings->add(new admin_setting_configtext('local_nit_finance/teacher_percent',
            get_string('teacherpercent', 'local_nit_finance'),
            get_string('teacherpercent_desc', 'local_nit_finance'),
            \local_nit_finance\config::DEFAULT_TEACHER_PERCENT, '/^(100|[1-9]?\d)$/', 4));
        $ADMIN->add('local_nit_finance_cat', $settings);
    }

    $ADMIN->add('local_nit_finance_cat', new admin_externalpage(
        'local_nit_finance_wallets',
        get_string('walletsadmin', 'local_nit_finance'),
        new moodle_url('/local/nit_finance/wallets.php'),
        'local/nit_finance:manage'
    ));
    $ADMIN->add('local_nit_finance_cat', new admin_externalpage(
        'local_nit_finance_codes',
        get_string('codesadmin', 'local_nit_finance'),
        new moodle_url('/local/nit_finance/codes.php'),
        'local/nit_finance:manage'
    ));
    $ADMIN->add('local_nit_finance_cat', new admin_externalpage(
        'local_nit_finance_reports',
        get_string('financialreports', 'local_nit_finance'),
        new moodle_url('/local/nit_finance/manage_withdrawals.php'),
        'local/nit_finance:manage'
    ));
}
