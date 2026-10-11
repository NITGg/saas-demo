<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * The device limit's ONE page (Site administration → Plugins → Local plugins →
 * Device limit): its settings, every account's devices, and the video protection
 * check. Whoever has local/nit_devices:manage sees the devices and removes one
 * device or all of them (reset); the settings and the video check are for site
 * admins. A student opening it sees their own devices only (read only: removing
 * would let an account be passed around).
 *
 *   /local/nit_devices/devices.php              the page (managers) / my devices
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
    admin_externalpage_setup('local_nit_devices', '', null, $url);
} else {
    $PAGE->set_pagelayout('standard');
}
$PAGE->set_title(get_string($canmanage ? 'pluginname' : 'devices', 'local_nit_devices'));
$PAGE->set_heading(get_string($canmanage ? 'pluginname' : 'devices', 'local_nit_devices'));

// The settings section (site admins).
$cansettings = !$userid && has_capability('moodle/site:config', $system);
if ($cansettings) {
    $settingsform = new \local_nit_devices\form\settings_form(new moodle_url($url, [], 'settings'));
    $settingsform->set_data(\local_nit_devices\form\settings_form::current());
    if ($data = $settingsform->get_data()) {
        \local_nit_devices\form\settings_form::save($data);
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

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

// No account chosen: the overview of every account's devices (managers).
if (!$userid) {
    $onlyfull = optional_param('full', 0, PARAM_BOOL);
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = 30;
    $max = manager::max_devices();
    $dateformat = get_string('strftimedatetimeshort', 'langconfig');

    echo $OUTPUT->heading(get_string('pluginname', 'local_nit_devices'));

    // The rule in force.
    $rule = manager::enabled()
        ? get_string('limitinfo', 'local_nit_devices', (object) ['max' => $max,
            'policy' => get_string(manager::policy() === manager::POLICY_REPLACE ? 'policyreplace' : 'policyblock', 'local_nit_devices')])
        : get_string('limitoffhere', 'local_nit_devices');
    echo $OUTPUT->notification($rule, manager::enabled() ? 'info' : 'warning', false);

    // 1) Settings.
    if ($cansettings) {
        echo $OUTPUT->heading(get_string('settings'), 3, 'mt-4', 'settings');
        $settingsform->display();
    }

    // 2) Every account's devices.
    echo $OUTPUT->heading(get_string('devices', 'local_nit_devices'), 3, 'mt-4', 'devices');

    // Totals.
    $totals = $DB->get_record_sql(
        "SELECT COUNT(1) AS devices, COUNT(DISTINCT userid) AS accounts,
                COALESCE(SUM(CASE WHEN kind = :web THEN 1 ELSE 0 END), 0) AS web
           FROM {local_nit_devices}", ['web' => manager::WEB]);
    $fullaccounts = $DB->get_fieldset_sql(
        "SELECT userid FROM {local_nit_devices} GROUP BY userid HAVING COUNT(1) >= :max", ['max' => $max]);
    $fullcount = count(array_filter($fullaccounts, static fn($id) => manager::applies_to((int) $id)));
    $tiles = [
        get_string('stat_accounts', 'local_nit_devices') => (int) $totals->accounts,
        get_string('stat_devices', 'local_nit_devices') => (int) $totals->devices,
        get_string('kind_web', 'local_nit_devices') => (int) $totals->web,
        get_string('kind_mobile', 'local_nit_devices') => (int) $totals->devices - (int) $totals->web,
        get_string('stat_full', 'local_nit_devices') => $fullcount,
    ];
    echo html_writer::start_div('d-flex flex-wrap gap-2 mb-3');
    foreach ($tiles as $label => $value) {
        echo html_writer::div(html_writer::div($value, 'fs-3 fw-bold') . html_writer::div($label, 'small text-muted'),
            'border rounded px-3 py-2 text-center', ['style' => 'min-width:120px']);
    }
    echo html_writer::end_div();

    // Filters: search any account (also those without devices) / only full accounts.
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring() . '#devices',
        'class' => 'd-flex flex-wrap align-items-center gap-2 mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $query, 'class' => 'form-control w-auto flex-grow-1',
        'placeholder' => get_string('searchplaceholder', 'local_nit_devices'), 'aria-label' => get_string('searchuser', 'local_nit_devices')]);
    echo html_writer::tag('label', html_writer::checkbox('full', 1, $onlyfull, '', ['class' => 'form-check-input me-1'])
        . get_string('onlyfull', 'local_nit_devices'), ['class' => 'form-check-label']);
    echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');

    // One row per account: its devices summed up.
    $params = ['guest' => $CFG->siteguest, 'web' => manager::WEB, 'mobile' => manager::MOBILE];
    $where = 'u.deleted = 0 AND u.id <> :guest';
    if ($query !== '') {
        $like = '%' . $DB->sql_like_escape($query) . '%';
        $where .= ' AND (' . $DB->sql_like('u.email', ':q1', false) . ' OR ' . $DB->sql_like('u.username', ':q2', false)
            . ' OR ' . $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':q3', false)
            . ' OR ' . $DB->sql_like('u.phone1', ':q4', false) . ')';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
    }
    if ($onlyfull) {
        $where .= ' AND d.total >= :max';
        $params['max'] = $max;
    }
    // A search also finds accounts with no device yet; the plain overview lists accounts with devices.
    $join = $query !== '' && !$onlyfull ? 'LEFT JOIN' : 'JOIN';
    $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $from = "FROM {user} u
        $join (SELECT userid, COUNT(1) AS total,
                      SUM(CASE WHEN kind = :web THEN 1 ELSE 0 END) AS web,
                      SUM(CASE WHEN kind = :mobile THEN 1 ELSE 0 END) AS mobile,
                      MIN(timecreated) AS firstseen, MAX(lastseen) AS lastseen
                 FROM {local_nit_devices} GROUP BY userid) d ON d.userid = u.id
        WHERE $where";
    $count = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
    $rows = $DB->get_records_sql("SELECT u.id, u.email, $namefields, d.total, d.web, d.mobile, d.firstseen, d.lastseen
        $from ORDER BY d.lastseen DESC, u.lastname, u.firstname", $params, $page * $perpage, $perpage);

    if (!$rows) {
        echo $OUTPUT->notification(get_string($query !== '' ? 'nousersfound' : 'nodevicesyet', 'local_nit_devices'), 'info');
    } else {
        $table = new html_table();
        $table->head = [get_string('fullname'), get_string('email'), get_string('devices', 'local_nit_devices'),
            get_string('kind_web', 'local_nit_devices'), get_string('kind_mobile', 'local_nit_devices'),
            get_string('firstseen', 'local_nit_devices'), get_string('lastseen', 'local_nit_devices'), ''];
        foreach ($rows as $row) {
            $total = (int) $row->total;
            if (!manager::applies_to((int) $row->id)) {
                $status = html_writer::span(get_string('exempt', 'local_nit_devices'), 'badge bg-secondary');
                $devices = $total . ' ' . $status;
            } else {
                $devices = $total . ' / ' . $max;
                if ($total >= $max) {
                    $devices .= ' ' . html_writer::span(get_string('full', 'local_nit_devices'), 'badge bg-danger');
                }
            }
            $details = new moodle_url('/local/nit_devices/devices.php', ['id' => $row->id]);
            $table->data[] = [
                html_writer::link($details, fullname($row)),
                s($row->email),
                $devices,
                (int) $row->web,
                (int) $row->mobile,
                $row->firstseen ? userdate($row->firstseen, $dateformat) : '-',
                $row->lastseen ? userdate($row->lastseen, $dateformat) : '-',
                html_writer::link($details, get_string('details', 'local_nit_devices')),
            ];
        }
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($count, $page, $perpage, new moodle_url($url, ['q' => $query, 'full' => (int) $onlyfull], 'devices'));
    }

    // 3) Video protection check (site admins).
    if ($cansettings) {
        echo $OUTPUT->heading(get_string('videocheck', 'local_nit_devices'), 3, 'mt-4', 'videos');
        echo html_writer::tag('p', get_string('videocheck_desc', 'local_nit_devices'));
        $courses = \local_nit_devices\video_check::paid_courses_on_vimeo();
        if (!$courses) {
            echo $OUTPUT->notification(get_string('videocheck_none', 'local_nit_devices'), \core\output\notification::NOTIFY_SUCCESS);
        } else {
            echo $OUTPUT->notification(get_string('videocheck_found', 'local_nit_devices', count($courses)),
                \core\output\notification::NOTIFY_WARNING);
            $table = new html_table();
            $table->head = [get_string('videocheck_course', 'local_nit_devices'), get_string('videocheck_vimeo', 'local_nit_devices'),
                get_string('videocheck_vdocipher', 'local_nit_devices')];
            foreach ($courses as $row) {
                $course = get_course($row['courseid']);
                $table->data[] = [
                    html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
                        format_string($course->fullname, true, ['context' => context_course::instance($course->id)])),
                    $row['vimeo'],
                    $row['vdocipher'],
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
        get_string('backtooverview', 'local_nit_devices')), ['class' => 'mt-3']);
}

echo $OUTPUT->footer();
