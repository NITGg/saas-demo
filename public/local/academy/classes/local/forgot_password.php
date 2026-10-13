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

namespace local_academy\local;

/**
 * The website's "Forgotten password" request answers in the same time for every
 * email (BUG-020).
 *
 * Moodle's /login/forgot_password.php sends the reset (or the "confirm your
 * account") email while the visitor waits, but only for an existing account, so
 * a slow answer meant "this email is registered". A valid request is answered at
 * once here and the email goes out from a background task
 * (\local_academy\task\password_reset_email), whatever was typed. The page itself
 * and everything else in the reset flow (the link, the new-password form) stay
 * Moodle's.
 *
 * Moodle handles the request as before when it says whether an account exists
 * anyway ("Protect usernames" off), when the form has a captcha or a plugin
 * extends it, and for anything the form would refuse.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forgot_password {

    /**
     * Hook: answer a valid reset request on /login/forgot_password.php.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG, $SCRIPT, $SESSION;
        if (during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || ($SCRIPT ?? '') !== '/login/forgot_password.php'
                || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['_qf__login_forgot_password_form'])
                || optional_param('token', '', PARAM_ALPHANUM) !== '' || !empty($SESSION->password_reset_token)
                || !empty($CFG->forgottenpasswordurl) || (isloggedin() && !isguestuser())) {
            return;
        }
        $request = self::request(optional_param('username', '', PARAM_RAW), optional_param('email', '', PARAM_RAW));
        if ($request === null || !confirm_sesskey()) {
            return; // Moodle shows the form with its error.
        }
        \local_academy\task\password_reset_email::queue('link', $request[0], $request[1]);
        redirect(new \moodle_url('/login/index.php'), get_string('emailpasswordconfirmmaybesent'), null,
            \core\output\notification::NOTIFY_INFO);
    }

    /**
     * The request to queue, or null when Moodle should handle the form itself.
     *
     * @param string $username as typed
     * @param string $email as typed
     * @return array|null [username, email] — one of them empty
     */
    public static function request(string $username, string $email): ?array {
        global $CFG;
        require_once($CFG->libdir . '/authlib.php');
        if (empty($CFG->protectusernames) || forgotpassword_captcha_enabled()
                || get_plugins_with_function('extend_forgot_password_form', 'lib.php')
                || get_plugins_with_function('validate_extend_forgot_password_form', 'lib.php')) {
            return null;
        }
        $username = trim($username);
        $email = trim($email);
        // The form's own rule: a username or an email, not both; a valid email.
        if (($username === '') === ($email === '') || ($email !== '' && !validate_email($email))) {
            return null;
        }
        return [$username, $email];
    }
}
