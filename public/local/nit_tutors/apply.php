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
 * "Join as a teacher" — step 1, the account. A signed-in user goes straight to step 2, the
 * application (/local/nit_tutors/application.php). Linked from the student sign-up page.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_tutors\local\application;
use local_nit_tutors\local\signup;

$url = new moodle_url('/local/nit_tutors/apply.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('apply_title', 'local_nit_tutors'));
$PAGE->set_heading(get_string('apply_title', 'local_nit_tutors'));

$next = new moodle_url('/local/nit_tutors/application.php');
if (isloggedin() && !isguestuser()) {
    application::ensure((int) $USER->id);
    redirect($next);
}
if (!signup::enabled()) {
    throw new moodle_exception('err_registrationdisabled', 'local_academy');
}

$form = new \local_nit_tutors\form\apply_form($url);
if ($data = $form->get_data()) {
    try {
        $user = signup::register((array) $data, !empty($data->agree));
        if (empty($user->confirmed)) {
            $PAGE->set_title(get_string('emailconfirm'));
            echo $OUTPUT->header();
            notice(get_string('emailconfirmsent', '', $user->email), new moodle_url('/'));
        }
        complete_user_login($user);
        redirect($next);
    } catch (\local_nit_tutors\local\invalid_signup $e) {
        \core\notification::error($e->getMessage());
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_tutors/apply_intro', [
    'loginurl' => (new moodle_url('/login/index.php'))->out(false),
    'studenturl' => (new moodle_url('/local/academy/register.php'))->out(false),
]);
$form->display();
echo $OUTPUT->footer();
