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
 * Approve a transcript, which is what makes the assistant visible to students.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/nit_ai:manage', $context);

$returnurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

$describe = \local_nit_ai\source::describe($cm);
$status = \local_nit_ai\api::status($cmid, $describe['ref'], $describe['length']);

if ($status === null) {
    redirect($returnurl, get_string('notranscript', 'local_nit_ai'), null,
        \core\output\notification::NOTIFY_WARNING);
}

// A transcript for a video that has since been replaced must never be approved:
// the assistant would answer confidently about content nobody is watching.
if ($status['stale']) {
    redirect($returnurl, get_string('check_stale', 'local_nit_ai'), null,
        \core\output\notification::NOTIFY_ERROR);
}

\local_nit_ai\api::approve($cmid);

redirect($returnurl, get_string('approved', 'local_nit_ai'), null,
    \core\output\notification::NOTIFY_SUCCESS);
