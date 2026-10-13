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
 * Admin settings, and the staff pages (AI Inbox, leads, usage) in the admin tree.
 * Staff with local/nit_assistant:viewinbox see the inbox without site settings rights.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('localplugins', new admin_category('local_nit_assistant_cat', get_string('pluginname', 'local_nit_assistant')));
$ADMIN->add('local_nit_assistant_cat', new admin_externalpage(
    'local_nit_assistant_inbox',
    get_string('inbox', 'local_nit_assistant'),
    new moodle_url('/local/nit_assistant/index.php'),
    'local/nit_assistant:viewinbox'
));
$ADMIN->add('local_nit_assistant_cat', new admin_externalpage(
    'local_nit_assistant_leads',
    get_string('leads', 'local_nit_assistant'),
    new moodle_url('/local/nit_assistant/leads.php'),
    'local/nit_assistant:viewinbox'
));
$ADMIN->add('local_nit_assistant_cat', new admin_externalpage(
    'local_nit_assistant_tickets',
    get_string('tickets', 'local_nit_assistant'),
    new moodle_url('/local/nit_assistant/tickets.php'),
    'local/nit_assistant:viewinbox'
));
$ADMIN->add('local_nit_assistant_cat', new admin_externalpage(
    'local_nit_assistant_followups',
    get_string('followups', 'local_nit_assistant'),
    new moodle_url('/local/nit_assistant/followups.php'),
    'local/nit_assistant:viewinbox'
));
$ADMIN->add('local_nit_assistant_cat', new admin_externalpage(
    'local_nit_assistant_whatsapp',
    get_string('wa_heading', 'local_nit_assistant'),
    new moodle_url('/local/nit_assistant/whatsapp.php'),
    'local/nit_assistant:manage'
));
$ADMIN->add('local_nit_assistant_cat', new admin_externalpage(
    'local_nit_assistant_usage',
    get_string('usage', 'local_nit_assistant'),
    new moodle_url('/local/nit_assistant/usage.php'),
    'local/nit_assistant:manage'
));

if ($hassiteconfig || has_capability('local/nit_assistant:manage', context_system::instance())) {
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

    $settings->add(new admin_setting_heading(
        'local_nit_assistant/chatheading',
        get_string('chatheading', 'local_nit_assistant'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nit_assistant/visitors',
        get_string('visitors', 'local_nit_assistant'),
        get_string('visitors_desc', 'local_nit_assistant'),
        1
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_nit_assistant/greeting',
        get_string('greeting', 'local_nit_assistant'),
        get_string('greeting_desc', 'local_nit_assistant'),
        '',
        PARAM_TEXT,
        60,
        3
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_nit_assistant/notes',
        get_string('notes', 'local_nit_assistant'),
        get_string('notes_desc', 'local_nit_assistant'),
        '',
        PARAM_TEXT,
        60,
        10
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/workinghours',
        get_string('workinghours', 'local_nit_assistant'),
        get_string('workinghours_desc', 'local_nit_assistant'),
        '',
        PARAM_TEXT,
        60
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/hourlylimit',
        get_string('hourlylimit', 'local_nit_assistant'),
        get_string('hourlylimit_desc', 'local_nit_assistant'),
        '30',
        PARAM_INT,
        6
    ));

    $settings->add(new admin_setting_heading(
        'local_nit_assistant/followupsheading',
        get_string('followups', 'local_nit_assistant'),
        get_string('followupsheading_desc', 'local_nit_assistant')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nit_assistant/followups',
        get_string('followupson', 'local_nit_assistant'),
        get_string('followupson_desc', 'local_nit_assistant'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/followupcooldown',
        get_string('followupcooldown', 'local_nit_assistant'),
        get_string('followupcooldown_desc', 'local_nit_assistant'),
        '7',
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_heading(
        'local_nit_assistant/waheading',
        get_string('wa_heading', 'local_nit_assistant'),
        get_string('wa_heading_desc', 'local_nit_assistant', (new moodle_url('/local/nit_assistant/whatsapp.php'))->out(false))
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nit_assistant/wa_ai',
        get_string('wa_ai', 'local_nit_assistant'),
        get_string('wa_ai_desc', 'local_nit_assistant'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/wa_phonenumberid',
        get_string('wa_phonenumberid', 'local_nit_assistant'),
        get_string('wa_phonenumberid_desc', 'local_nit_assistant'),
        '',
        PARAM_ALPHANUM,
        24
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/wa_wabaid',
        get_string('wa_wabaid', 'local_nit_assistant'),
        get_string('wa_wabaid_desc', 'local_nit_assistant'),
        '',
        PARAM_ALPHANUM,
        24
    ));
    $settings->add(new \local_nit_assistant\admin\setting_secret(
        'local_nit_assistant/wa_token',
        get_string('wa_token', 'local_nit_assistant'),
        get_string('wa_token_desc', 'local_nit_assistant'),
        ''
    ));
    $settings->add(new \local_nit_assistant\admin\setting_secret(
        'local_nit_assistant/wa_appsecret',
        get_string('wa_appsecret', 'local_nit_assistant'),
        get_string('wa_appsecret_desc', 'local_nit_assistant'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/wa_verifytoken',
        get_string('wa_verifytoken', 'local_nit_assistant'),
        get_string('wa_verifytoken_desc', 'local_nit_assistant'),
        '',
        PARAM_ALPHANUMEXT,
        40
    ));
    $settings->add(new admin_setting_configtext(
        'local_nit_assistant/wa_graphversion',
        get_string('wa_graphversion', 'local_nit_assistant'),
        get_string('wa_graphversion_desc', 'local_nit_assistant'),
        \local_nit_assistant\whatsapp\cloud::DEFAULT_VERSION,
        PARAM_ALPHANUMEXT,
        10
    ));

    $ADMIN->add('local_nit_assistant_cat', $settings);
}
