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
 * Login-page preview for the homepage editor's "Auth pages" panel.
 *
 * A logged-in owner cannot open /login/index.php (it redirects), so this renders
 * the real login layout + form (same templates, same brand panel) in the editor's
 * preview frame. Editors only; the form is inert (no action).
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
if (!\theme_nit\local\editor::can_edit()) {
    throw new required_capability_exception(context_system::instance(), 'moodle/site:manageblocks', 'nopermissions', '');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/nit/authpreview.php'));
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('login'));
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();
$form = new \core_auth\output\login(get_enabled_auth_plugins(), '');
echo $OUTPUT->render($form);
// Inert: no form may submit from the preview.
echo '<script>document.querySelectorAll("form").forEach(function(f){f.addEventListener("submit",function(e){e.preventDefault();});});'
    . 'document.querySelectorAll("a").forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();});});</script>';
echo $OUTPUT->footer();
