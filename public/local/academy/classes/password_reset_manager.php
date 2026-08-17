<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * OTP-based password reset for the mobile app, plus change-password for
 * logged-in users.
 *
 * Flow (forgot password):
 *   1. request_otp(email)      -> emails a 6-digit code, stores its hash.
 *   2. verify_otp(email, otp)  -> checks the code, returns a single-use reset token.
 *   3. reset_password(token, newpassword) -> sets the new password.
 *
 * change_password(userid, current, new) is for an already-logged-in user.
 *
 * Only internal-auth accounts (email/manual) can be reset here — OAuth2/Google
 * users have no local password and should sign in with Google.
 */
class password_reset_manager {

    /** OTP lifetime (seconds). */
    const OTP_TTL = 600;            // 10 minutes
    /** Max verify attempts before the code is locked. */
    const OTP_MAX_ATTEMPTS = 5;
    /** Rate-limit window for requesting codes. */
    const REQUEST_WINDOW = 900;     // 15 minutes
    /** Max codes requested per email within the window. */
    const REQUEST_MAX = 3;
    /** Lifetime of the reset token after a successful verify (seconds). */
    const RESET_TTL = 600;          // 10 minutes

    /** Auth methods whose password we can reset. */
    private static function resettable_auth(string $auth): bool {
        return in_array($auth, ['email', 'manual'], true);
    }

    /**
     * Step 1 — request an OTP. Always returns a generic success (it never reveals
     * whether the email belongs to an account), but only actually emails a code
     * when the account exists and uses a resettable auth method.
     */
    public static function request_otp(string $email): array {
        global $DB, $CFG;

        $email = \core_text::strtolower(trim($email));
        if ($email === '' || !validate_email($email)) {
            throw new \moodle_exception('err_invalidemail', 'local_academy');
        }

        // Rate limit per email.
        $recent = $DB->count_records_select('academy_password_otps',
            'email = :email AND timecreated > :since',
            ['email' => $email, 'since' => time() - self::REQUEST_WINDOW]);
        if ($recent >= self::REQUEST_MAX) {
            throw new \moodle_exception('err_toomanyrequests', 'local_academy');
        }

        $user = $DB->get_record('user', [
            'email' => $email, 'deleted' => 0, 'suspended' => 0,
            'mnethostid' => $CFG->mnet_localhost_id,
        ]);

        if ($user && !isguestuser($user) && self::resettable_auth($user->auth)) {
            // Drop any previous unverified codes for this user.
            $DB->delete_records_select('academy_password_otps',
                'userid = :uid AND verified = 0', ['uid' => $user->id]);

            $otp = self::generate_code();
            $DB->insert_record('academy_password_otps', (object) [
                'userid'      => $user->id,
                'email'       => $email,
                'otphash'     => password_hash($otp, PASSWORD_DEFAULT),
                'resettoken'  => null,
                'verified'    => 0,
                'attempts'    => 0,
                'expires'     => time() + self::OTP_TTL,
                'timecreated' => time(),
            ]);
            self::email_otp($user, $otp);
        }

        return ['sent' => true, 'expiresin' => self::OTP_TTL];
    }

    /**
     * Step 2 — verify the OTP. On success returns a single-use reset token.
     */
    public static function verify_otp(string $email, string $otp): array {
        global $DB;

        $email = \core_text::strtolower(trim($email));
        $otp = trim($otp);

        $rows = $DB->get_records_select('academy_password_otps',
            'email = :email AND verified = 0', ['email' => $email],
            'timecreated DESC', '*', 0, 1);
        $rec = $rows ? reset($rows) : null;

        if (!$rec || $rec->expires < time()) {
            throw new \moodle_exception('err_otpexpired', 'local_academy');
        }
        if ($rec->attempts >= self::OTP_MAX_ATTEMPTS) {
            throw new \moodle_exception('err_otplocked', 'local_academy');
        }
        if (!password_verify($otp, $rec->otphash)) {
            $DB->set_field('academy_password_otps', 'attempts', $rec->attempts + 1, ['id' => $rec->id]);
            throw new \moodle_exception('err_otpinvalid', 'local_academy');
        }

        $resettoken = bin2hex(random_bytes(24));
        $DB->update_record('academy_password_otps', (object) [
            'id'         => $rec->id,
            'verified'   => 1,
            'resettoken' => $resettoken,
            'expires'    => time() + self::RESET_TTL,
        ]);

        return ['resettoken' => $resettoken, 'expiresin' => self::RESET_TTL];
    }

    /**
     * Step 3 — set the new password using the verified reset token.
     */
    public static function reset_password(string $resettoken, string $newpassword): array {
        global $DB;

        $rec = $DB->get_record('academy_password_otps', ['resettoken' => $resettoken, 'verified' => 1]);
        if (!$rec || $rec->expires < time()) {
            throw new \moodle_exception('err_resetexpired', 'local_academy');
        }

        $user = $DB->get_record('user', ['id' => $rec->userid, 'deleted' => 0], '*', MUST_EXIST);

        $errmsg = '';
        if (!check_password_policy($newpassword, $errmsg, $user)) {
            throw new \moodle_exception('err_weakpassword', 'local_academy', '', null, $errmsg);
        }

        update_internal_user_password($user, $newpassword);

        // Invalidate every code/token for this user.
        $DB->delete_records('academy_password_otps', ['userid' => $user->id]);

        return ['reset' => true];
    }

    /**
     * Change password for an already-logged-in user (requires the current one).
     */
    public static function change_password(int $userid, string $current, string $newpassword): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        if (!self::resettable_auth($user->auth)) {
            throw new \moodle_exception('err_authnochange', 'local_academy');
        }
        if (!validate_internal_user_password($user, $current)) {
            throw new \moodle_exception('err_wrongpassword', 'local_academy');
        }

        $errmsg = '';
        if (!check_password_policy($newpassword, $errmsg, $user)) {
            throw new \moodle_exception('err_weakpassword', 'local_academy', '', null, $errmsg);
        }

        update_internal_user_password($user, $newpassword);

        return ['changed' => true];
    }

    // ── helpers ──

    /** A zero-padded 6-digit numeric code. */
    private static function generate_code(): string {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Email the OTP to the user. */
    private static function email_otp(\stdClass $user, string $otp): void {
        $sitename = format_string(get_site()->fullname);
        $subject = get_string('otp_subject', 'local_academy', $sitename);
        $body = get_string('otp_body', 'local_academy', [
            'name' => fullname($user),
            'code' => $otp,
            'mins' => (int) (self::OTP_TTL / 60),
            'site' => $sitename,
        ]);
        email_to_user($user, \core_user::get_noreply_user(), $subject, $body,
            '<p>' . nl2br(s($body)) . '</p>');
    }
}
