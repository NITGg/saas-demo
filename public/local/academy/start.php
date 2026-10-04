<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. See <http://www.gnu.org/licenses/>.

/**
 * "ابدأ رحلتك" — the home page hero button. Redirects to the right place for
 * whoever clicks it; see \local_academy\start_target.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$PAGE->set_url(new moodle_url('/local/academy/start.php'));
$PAGE->set_context(context_system::instance());

$userid = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;
redirect(\local_academy\start_target::url_for_user($userid));
