<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * An account's devices. Whoever has local/nit_devices:manage finds any account
 * here and removes one device or all of them (reset); a student sees their own
 * list only (removing would let an account be passed around).
 *
 *   /local/nit_devices/devices.php              search (managers) / my devices
 *   /local/nit_devices/devices.php?id=<userid>  that account's devices
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_devices\manager;

require_login(null, false);
$system = context_system::instance();
$canmanage = has_capability('local/nit_devices:manage', $system);

$userid = optional_param('id', $canmanage ? 0 : (int) $USER->id, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$deviceid = optional_param('device', 0, PARAM_INT);
$query = trim(optional_param('q', '', PARAM_NOTAGS));

if ($userid !== (int) $USER->id) {
    require_capability('local/nit_devices:manage', $system);
}

$url = new moodle_url('/local/nit_devices/devices.php', $userid ? ['id' => $userid] : []);
$PAGE->set_context($system);
$PAGE->set_url($url);
if ($canmanage) {
    admin_externalpage_setup('local_nit_devices_devices', '', null, $url);
} else {
    $PAGE->set_pagelayout('standard');
}
$PAGE->set_title(get_string('devices', 'local_nit_devices'));
$PAGE->set_heading(get_string('devices', 'local_nit_devices'));

// Remove one device / all of them (managers only).
if ($action !== '' && $userid) {
    require_capability('local/nit_devices:manage', $system);
    require_sesskey();
    if ($action === 'remove') {
        $device = $DB->get_record('local_nit_devices', ['id' => $deviceid, 'userid' => $userid], '*', MUST_EXIST);
        manager::revoke($device);
        redirect($url, get_string('removed', 'local_nit_devices'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'reset') {
        $count = manager::reset($userid);
        redirect($url, get_string('resetdone', 'local_nit_devices', $count), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// No account chosen yet: the search (managers).
if (!$userid) {
    echo $OUTPUT->heading(get_string('devices', 'local_nit_devices'));
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring(), 'class' => 'd-flex mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $query, 'class' => 'form-control me-2',
        'placeholder' => get_string('searchplaceholder', 'local_nit_devices'), 'aria-label' => get_string('searchuser', 'local_nit_devices')]);
    echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');

    if ($query !== '') {
        $like = '%' . $DB->sql_like_escape($query) . '%';
        $users = $DB->get_records_select('user',
            'deleted = 0 AND id <> :guest AND (' . $DB->sql_like('email', ':q1', false) . ' OR '
                . $DB->sql_like('username', ':q2', false) . ' OR '
                . $DB->sql_like($DB->sql_fullname(), ':q3', false) . ' OR ' . $DB->sql_like('phone1', ':q4', false) . ')',
            ['guest' => $CFG->siteguest, 'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like],
            'lastname, firstname', '*', 0, 25);
        if (!$users) {
            echo $OUTPUT->notification(get_string('nousersfound', 'local_nit_devices'), 'info');
        } else {
            $table = new html_table();
            $table->head = [get_string('fullname'), get_string('email'), get_string('devices', 'local_nit_devices')];
            foreach ($users as $user) {
                $table->data[] = [
                    html_writer::link(new moodle_url($url, ['id' => $user->id]), fullname($user)),
                    s($user->email),
                    $DB->count_records('local_nit_devices', ['userid' => $user->id]),
                ];
            }
            echo html_writer::table($table);
        }
    }
    echo $OUTPUT->footer();
    die;
}

$user = core_user::get_user($userid, '*', MUST_EXIST);
echo $OUTPUT->heading($userid === (int) $USER->id ? get_string('mydevices', 'local_nit_devices')
    : get_string('userdevices', 'local_nit_devices', fullname($user)));

if (!manager::enabled()) {
    echo $OUTPUT->notification(get_string('limitoff', 'local_nit_devices'), 'info');
} else if (!manager::applies_to($userid)) {
    echo $OUTPUT->notification(get_string('exemptinfo', 'local_nit_devices'), 'info');
} else {
    echo $OUTPUT->notification(get_string('limitinfo', 'local_nit_devices', (object) [
        'max' => manager::max_devices(),
        'policy' => get_string(manager::policy() === manager::POLICY_REPLACE ? 'policyreplace' : 'policyblock', 'local_nit_devices'),
    ]), 'info');
}

$devices = manager::devices($userid);
if (!$devices) {
    echo html_writer::tag('p', get_string('nodevices', 'local_nit_devices'));
} else {
    $table = new html_table();
    $table->head = [get_string('device', 'local_nit_devices'), get_string('kind', 'local_nit_devices'),
        get_string('firstseen', 'local_nit_devices'), get_string('lastseen', 'local_nit_devices'), get_string('ip', 'local_nit_devices')];
    if ($canmanage) {
        $table->head[] = '';
    }
    foreach ($devices as $device) {
        $row = [
            s($device->name !== '' ? $device->name : $device->platform),
            get_string('kind_' . $device->kind, 'local_nit_devices'),
            userdate($device->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            userdate($device->lastseen, get_string('strftimedatetimeshort', 'langconfig')),
            s($device->ip),
        ];
        if ($canmanage) {
            $row[] = $OUTPUT->single_button(new moodle_url($url, ['action' => 'remove', 'device' => $device->id, 'sesskey' => sesskey()]),
                get_string('remove', 'local_nit_devices'), 'post', [
                    'type' => single_button::BUTTON_DANGER,
                    'data-modal' => 'confirmation',
                    'data-modal-content-str' => json_encode(['removeconfirm', 'local_nit_devices']),
                    'data-modal-yes-button-str' => json_encode(['remove', 'local_nit_devices']),
                ]);
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);

    if ($canmanage) {
        echo $OUTPUT->single_button(new moodle_url($url, ['action' => 'reset', 'sesskey' => sesskey()]),
            get_string('resetall', 'local_nit_devices'), 'post', [
                'type' => single_button::BUTTON_DANGER,
                'data-modal' => 'confirmation',
                'data-modal-content-str' => json_encode(['resetconfirm', 'local_nit_devices']),
                'data-modal-yes-button-str' => json_encode(['resetall', 'local_nit_devices']),
            ]);
    }
}
if ($canmanage) {
    echo html_writer::tag('p', html_writer::link(new moodle_url('/local/nit_devices/devices.php'),
        get_string('searchuser', 'local_nit_devices')), ['class' => 'mt-3']);
}

echo $OUTPUT->footer();
