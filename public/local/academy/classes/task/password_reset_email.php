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

namespace local_academy\task;

/**
 * Sends a password-reset email in the background (BUG-020).
 *
 * Sending the email while the visitor waits made the answer slower only for
 * emails that have an account, which told anyone which emails are registered.
 * Every request — known account or not — now just queues this task, and the
 * task decides whether there is anyone to email.
 *
 * Custom data: kind = "link" (the website's reset link, Moodle's own flow, with
 * username or email as typed) or "otp" (the app's 6-digit code, with email).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class password_reset_email extends \core\task\adhoc_task {

    /**
     * Queue a reset email for what the visitor typed.
     *
     * @param string $kind "link" or "otp"
     * @param string $username
     * @param string $email
     */
    public static function queue(string $kind, string $username, string $email): void {
        $task = new self();
        $task->set_custom_data(['kind' => $kind, 'username' => $username, 'email' => $email]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Send the email, when the account exists.
     */
    public function execute() {
        global $CFG;
        $data = (array) $this->get_custom_data();
        if (($data['kind'] ?? '') === 'otp') {
            \local_academy\password_reset_manager::send_otp((string) ($data['email'] ?? ''));
            return;
        }
        require_once($CFG->dirroot . '/login/lib.php');
        $username = (string) ($data['username'] ?? '');
        $email = (string) ($data['email'] ?? '');
        if ($username === '' && $email === '') {
            return;
        }
        // As Moodle's form does: an account that is not confirmed yet gets the confirmation email again.
        try {
            $user = $username !== '' ? get_complete_user_data('username', \core_text::strtolower($username))
                : get_complete_user_data('email', $email, null, true);
        } catch (\dml_exception $e) {
            $user = false; // Several accounts share this email: Moodle sends nothing either.
        }
        if ($user && empty($user->confirmed)) {
            send_confirmation_email($user);
            return;
        }
        core_login_process_password_reset($username, $email);
    }
}
