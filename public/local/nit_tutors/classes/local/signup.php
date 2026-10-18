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

namespace local_nit_tutors\local;

use local_academy\local\registration;

/**
 * "Join as a teacher" account creation (/local/nit_tutors/apply.php).
 *
 * The student sign-up (/local/academy/register.php) asks school questions — grade, parents'
 * phones, study system — that mean nothing for a teacher. This creates the same kind of account
 * (same auth method, email confirmation, per-IP throttle, terms) with only name, email, phone and
 * password, then opens a draft application. Until it is approved the account is an ordinary
 * learner account.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signup {

    /** Submitted fields. */
    const FIELDS = ['firstname', 'lastname', 'email', 'phone', 'password'];

    /**
     * Is "join as a teacher" open? It follows the academy's registration switch.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return !class_exists(registration::class) || registration::enabled();
    }

    /**
     * Field errors of the submitted values: [field => message]. Empty = valid.
     *
     * @param array $data cleaned values
     * @param bool $agreed
     * @return string[]
     */
    public static function validate(array $data, bool $agreed): array {
        global $DB, $CFG;
        $errors = [];
        foreach (['firstname', 'lastname'] as $f) {
            if ($data[$f] === '') {
                $errors[$f] = get_string('required');
            }
        }
        if ($data['email'] === '' || !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        } else if ($DB->record_exists_select('user', 'LOWER(email) = ? AND deleted = 0 AND mnethostid = ?',
                [$data['email'], $CFG->mnet_localhost_id])) {
            $errors['email'] = get_string('emailexists');
        }
        if (!preg_match('/^\+?[0-9]{7,15}$/', $data['phone'])) {
            $errors['phone'] = get_string('err_phone', 'local_nit_tutors');
        }
        $policyerror = '';
        if ($data['password'] === '') {
            $errors['password'] = get_string('required');
        } else if (!check_password_policy($data['password'], $policyerror)) {
            $errors['password'] = $policyerror;
        }
        if (!$agreed) {
            $errors['agree'] = get_string('err_agree', 'local_nit_tutors');
        }
        return $errors;
    }

    /**
     * Trim the values; Arabic-Indic digits in the phone become 0-9; the email is lower case.
     *
     * @param array $raw
     * @return array
     */
    public static function clean(array $raw): array {
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = $f === 'password' ? (string) ($raw[$f] ?? '') : trim((string) ($raw[$f] ?? ''));
        }
        $out['email'] = \core_text::strtolower($out['email']);
        $out['phone'] = preg_replace('/[\s\-()]/', '', strtr($out['phone'],
            ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        $out['firstname'] = clean_param($out['firstname'], PARAM_TEXT);
        $out['lastname'] = clean_param($out['lastname'], PARAM_TEXT);
        return $out;
    }

    /**
     * Create the account and the draft application.
     *
     * @param array $raw
     * @param bool $agreed
     * @return \stdClass the new user (confirmed = 0 while it waits for the email)
     * @throws \moodle_exception registrationdisabled; invalid_signup with field errors
     */
    public static function register(array $raw, bool $agreed): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        if (!self::enabled()) {
            throw new \moodle_exception('err_registrationdisabled', 'local_academy');
        }
        $data = self::clean($raw);
        if ($errors = self::validate($data, $agreed)) {
            throw new invalid_signup($errors);
        }
        registration::throttle();
        $confirm = registration::confirmation_required();
        $user = (object) [
            'auth' => registration::auth_method(),
            'confirmed' => $confirm ? 0 : 1,
            'secret' => $confirm ? random_string(15) : '',
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => registration::unique_username($data['email']),
            'password' => $data['password'],
            'email' => $data['email'],
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'phone1' => $data['phone'],
            'lang' => current_language(),
            'calendartype' => $CFG->calendartype ?? 'gregorian',
        ];
        $user->id = user_create_user($user, true, true);
        application::ensure((int) $user->id);
        $created = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        if ($confirm && !send_confirmation_email($created, self::confirmation_url())) {
            debugging('local_nit_tutors: the confirmation email to user ' . $created->id . ' could not be sent.');
        }
        return $created;
    }

    /**
     * The step-by-step teacher sign-up: the account AND the whole application (about, subjects,
     * intro video, qualifications, the admin's fields) checked together, then the account is
     * created, the data saved and the application sent for review — nothing is left for later.
     *
     * @param array $raw account values (FIELDS)
     * @param string $password2 the repeated password
     * @param bool $agreed
     * @param array $in teacher_input::from_request()
     * @param array $files teacher_input::files_from_request()
     * @return \stdClass the new user
     * @throws invalid_signup with every field error (account and application) — nothing is created then
     */
    public static function register_teacher(array $raw, string $password2, bool $agreed, array $in, array $files): \stdClass {
        $errors = self::validate(self::clean($raw), $agreed) + teacher_input::validate($in, $files, 0, true);
        if ($password2 !== (string) ($raw['password'] ?? '')) {
            $errors['password2'] = get_string('reg_passwordmismatch', 'local_academy');
        }
        if ($errors) {
            throw new invalid_signup($errors);
        }
        $user = self::register($raw, $agreed);
        teacher_input::save((int) $user->id, $in, $files);
        try {
            application::submit((int) $user->id);
        } catch (\moodle_exception $e) {
            // Cannot happen after the checks above; the draft stays and the teacher sends it later.
            debugging('local_nit_tutors: sign-up application not sent: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $user;
    }

    /**
     * Where the confirmation link sends the new teacher: their application.
     *
     * @return \moodle_url
     */
    public static function confirmation_url(): \moodle_url {
        return new \moodle_url('/login/confirm.php', ['redirect' => (new \moodle_url('/local/nit_tutors/application.php'))->out(false)]);
    }
}
