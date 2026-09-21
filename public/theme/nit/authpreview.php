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
 * Login / signup page preview for the homepage editor's "Auth pages" panel.
 *
 * A logged-in owner cannot open /login/index.php (it redirects), so this renders
 * the real login layout + form — or, with ?signup=1, the real signup form — in
 * the editor's preview frame. Editors only; every control is inert.
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
$signup = optional_param('signup', 0, PARAM_BOOL);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/nit/authpreview.php', $signup ? ['signup' => 1] : []));
$PAGE->set_pagelayout('login');
$PAGE->set_title($signup ? get_string('newaccount') : get_string('login'));
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();
try {
    if ($signup) {
        require_once($CFG->dirroot . '/login/signup_form.php');
        $mform = new login_signup_form(new moodle_url('/theme/nit/authpreview.php', ['signup' => 1]));
        // Same template the real signup page renders through (theme override).
        echo $OUTPUT->render_from_template('core/signup_form_layout', ['formhtml' => $mform->render()]);
    } else {
        $form = new \core_auth\output\login(get_enabled_auth_plugins(), '');
        echo $OUTPUT->render_from_template('core/loginform', $form->export_for_template($OUTPUT));
    }
} catch (\Throwable $e) {
    // Never a fatal page inside the preview frame: show the reason instead.
    echo html_writer::div(s($e->getMessage()), 'alert alert-warning');
}
// Inert: nothing may submit or navigate from the preview.
echo '<script>document.querySelectorAll("form").forEach(function(f){f.addEventListener("submit",function(e){e.preventDefault();});});'
    . 'document.querySelectorAll("a").forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();});});</script>';
echo $OUTPUT->footer();
