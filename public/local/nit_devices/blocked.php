<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Where a web sign-in lands when the account already uses all its devices.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_devices/blocked.php'));
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('blockedtitle', 'local_nit_devices'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('blockedtitle', 'local_nit_devices'));
echo $OUTPUT->notification(get_string('devicelimit', 'local_nit_devices', \local_nit_devices\manager::max_devices()),
    \core\output\notification::NOTIFY_WARNING, false);
echo html_writer::tag('p', get_string('blockedbody', 'local_nit_devices'));
echo $OUTPUT->single_button(get_login_url(), get_string('backtologin', 'local_nit_devices'), 'get');
echo $OUTPUT->footer();
