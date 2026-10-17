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
 * Viewer page: embeds the moodle-ops backup panel (/ops/mini) and links to the full one.
 *
 * @package    local_opspanel
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php'); // for admin_externalpage_setup()

require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('local_opspanel');

$opsurl = get_config('local_opspanel', 'opsurl');
if (empty($opsurl)) {
    $opsurl = $CFG->wwwroot . '/ops/';
}
$opsurl = rtrim($opsurl, '/') . '/';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_opspanel'));
echo html_writer::tag('p', get_string('intro', 'local_opspanel'));
echo html_writer::div(
    html_writer::link($opsurl, get_string('openfull', 'local_opspanel'),
        ['class' => 'btn btn-primary', 'target' => '_blank', 'rel' => 'noopener']),
    'mb-2'
);
echo html_writer::tag('p', get_string('signedout', 'local_opspanel'), ['class' => 'text-muted small']);
echo html_writer::tag('iframe', '', [
    'src' => $opsurl . 'mini',
    'style' => 'width:100%;max-width:640px;height:300px;border:0;border-radius:12px',
    'title' => get_string('pluginname', 'local_opspanel'),
    'loading' => 'lazy',
]);
echo $OUTPUT->footer();
