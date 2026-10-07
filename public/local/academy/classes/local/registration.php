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

defined('MOODLE_INTERNAL') || die();

/**
 * Student self-registration (the Bassthalk 3-step form) — one implementation for
 * the web page /local/academy/register.php and the mobile API (register_student).
 *
 * Like Moodle's email self-registration, the account waits until the student
 * opens the link in the confirmation email ({@see confirmation_required()}; when
 * that is off it is active at once). The student signs in with their email
 * (site setting authloginviaemail; the username is the email too). Every registration answer is saved in the academy profile
 * fields (see user_fields).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration {

    /** Form field => academy profile field it is saved in. */
    const PROFILE_MAP = [
        'secondname'  => 'secondname',
        'thirdname'   => 'thirdname',
        'national'    => 'nationalid',
        'grade'       => academic_structure::USER_YEAR,
        'studysystem' => academic_structure::USER_SYSTEM,
        'division'    => academic_structure::USER_DIVISION,
        'fatherphone' => 'fatherphone',
        'motherphone' => 'motherphone',
        'school'      => 'school',
        'guardianjob' => 'guardianjob',
        'governorate' => 'governorate',
        'religion'    => 'religion',
        'gender'      => 'gender',
    ];

    /** Every field of the form, in form order (all required). */
    const FIELDS = ['firstname', 'secondname', 'thirdname', 'lastname', 'phone', 'grade', 'national',
        'fatherphone', 'motherphone', 'school', 'guardianjob', 'studysystem', 'governorate', 'division',
        'religion', 'gender', 'email', 'password'];

    /**
     * Whether the academy registration is open: our own setting
     * local_academy/registration (on by default). It does NOT depend on Moodle's
     * "Self registration" (registerauth), which also opens core /login/signup.php.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('local_academy', 'registration');
        return $value === false || $value === null || $value === '' || (bool) $value;
    }

    /**
     * The auth method new students get: Moodle's self-registration method when it
     * is set and enabled, else "email" when enabled, else "manual" (always on).
     * All of them sign in with email + password.
     *
     * @return string
     */
    public static function auth_method(): string {
        $registerauth = (string) get_config('core', 'registerauth');
        foreach ([$registerauth, 'email'] as $auth) {
            if ($auth !== '' && is_enabled_auth($auth)) {
                return $auth;
            }
        }
        return 'manual';
    }

    /**
     * Whether a new account waits for its email to be confirmed — Moodle's own
     * flow: Self registration = "Email-based self-registration" (set by
     * site_defaults::email_confirmation()). The student gets Moodle's
     * confirmation email; /login/confirm.php activates the account, and an
     * unconfirmed sign-in offers to send the email again.
     *
     * @return bool
     */
    public static function confirmation_required(): bool {
        return get_config('core', 'registerauth') === 'email' && is_enabled_auth('email');
    }

    /**
     * Hook: Moodle's own sign-up form (/login/signup.php, open while Self
     * registration is on) → the academy form, so every account gets the same fields.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;
        if (during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || ($SCRIPT ?? '') !== '/login/signup.php') {
            return;
        }
        redirect(new \moodle_url('/local/academy/register.php'));
    }

    /**
     * Where the confirmation link sends the student once confirmed: the home page.
     *
     * @return \moodle_url
     */
    public static function confirmation_url(): \moodle_url {
        return new \moodle_url('/login/confirm.php', ['redirect' => (new \moodle_url('/'))->out(false)]);
    }

    /**
     * The registration form for the app: fields in order with their step, type and
     * (for dropdowns) options; the password rules and the terms link.
     *
     * @return array
     */
    public static function form(): array {
        $label = static fn(string $s): string => format_string($s, true,
            ['context' => \context_system::instance(), 'escape' => false]);
        $menu = static fn(array $values): array => array_map(
            static fn(string $v): array => ['value' => $v, 'label' => $label($v)], $values);

        $structure = academic_structure::get();
        $divisions = [];
        foreach (academic_structure::map($structure) as $system => $list) {
            foreach ($list as $division) {
                $divisions[$division]['value'] = $division;
                $divisions[$division]['label'] = $label($division);
                $divisions[$division]['systems'][] = $system;
            }
        }
        $options = [
            'grade' => array_map(static fn(array $y): array => ['value' => $y['key'], 'label' => $y['name'],
                'categoryid' => $y['id']], academic_structure::years()),
            'studysystem' => $menu(array_column($structure['systems'], 'name')),
            'division' => array_values($divisions),
            'governorate' => $menu(user_fields::menu_options('governorate')),
            'religion' => $menu(user_fields::menu_options('religion')),
            'gender' => $menu(user_fields::menu_options('gender')),
        ];
        $steps = [
            1 => ['firstname', 'secondname', 'thirdname', 'lastname', 'phone', 'grade', 'national'],
            2 => ['fatherphone', 'motherphone', 'school', 'guardianjob', 'studysystem', 'governorate', 'division'],
            3 => ['religion', 'gender', 'email', 'password'],
        ];
        $types = ['phone' => 'phone', 'fatherphone' => 'phone', 'motherphone' => 'phone', 'national' => 'nationalid',
            'email' => 'email', 'password' => 'password'];

        $fields = [];
        foreach ($steps as $step => $names) {
            foreach ($names as $name) {
                $fields[] = [
                    'name'     => $name,
                    'label'    => get_string('reg_' . $name, 'local_academy'),
                    'step'     => $step,
                    'type'     => isset($options[$name]) ? 'menu' : ($types[$name] ?? 'text'),
                    'required' => true,
                    'options'  => $options[$name] ?? [],
                ];
            }
        }
        return [
            'enabled'        => self::enabled(),
            'fields'         => $fields,
            'passwordpolicy' => self::password_policy_text(),
            'termsurl'       => (new \moodle_url('/local/nit_pages/page.php',
                ['p' => 'terms', 'embedded' => 1, 'lang' => current_language()]))->out(false),
        ];
    }

    /**
     * The site's password rules as one readable sentence ('' when there is no policy).
     *
     * @return string
     */
    public static function password_policy_text(): string {
        global $CFG;
        return empty($CFG->passwordpolicy) ? '' : print_password_policy();
    }

    /**
     * Clean the submitted values (trimmed strings, '' when missing). Digits typed
     * with Arabic-Indic numerals are converted to 0-9.
     *
     * @param array $raw
     * @return array<string, string>
     */
    public static function clean(array $raw): array {
        $out = [];
        foreach (self::FIELDS as $name) {
            $value = (string) ($raw[$name] ?? '');
            if ($name === 'password') {
                $out[$name] = $value; // Never altered.
                continue;
            }
            $value = trim(clean_param($value, PARAM_TEXT));
            if (in_array($name, ['phone', 'fatherphone', 'motherphone', 'national'], true)) {
                $value = strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
            }
            $out[$name] = $value;
        }
        $out['email'] = \core_text::strtolower($out['email']);
        return $out;
    }

    /**
     * Check a registration. Returns field => error message (empty array = valid).
     *
     * @param array $data cleaned values (see clean())
     * @param bool $agreed whether the terms were accepted
     * @return array<string, string>
     */
    public static function validate(array $data, bool $agreed): array {
        global $DB;
        $errors = [];
        foreach (self::FIELDS as $name) {
            if (($data[$name] ?? '') === '') {
                $errors[$name] = get_string('reg_required', 'local_academy');
            }
        }
        foreach (['phone', 'fatherphone', 'motherphone'] as $name) {
            if (!isset($errors[$name]) && !\local_academy\profile_manager::valid_phone($data[$name])) {
                $errors[$name] = get_string('err_invalidphone', 'local_academy');
            }
        }
        if (!isset($errors['national']) && !preg_match('/^[0-9]{14}$/', $data['national'])) {
            $errors['national'] = get_string('err_invalidnationalid', 'local_academy');
        }

        // Dropdowns accept only their own options.
        $menus = [
            'grade' => array_column(academic_structure::years(), 'key'),
            'studysystem' => array_column(academic_structure::get()['systems'], 'name'),
            'governorate' => user_fields::menu_options('governorate'),
            'religion' => user_fields::menu_options('religion'),
            'gender' => user_fields::menu_options('gender'),
        ];
        foreach ($menus as $name => $allowed) {
            if (!isset($errors[$name]) && !in_array($data[$name], $allowed, true)) {
                $errors[$name] = get_string('reg_invalidchoice', 'local_academy');
            }
        }
        if (!isset($errors['division'])) {
            $map = academic_structure::map();
            if (!in_array($data['division'], $map[$data['studysystem']] ?? [], true)) {
                $errors['division'] = get_string('err_divisionmismatch', 'local_academy');
            }
        }

        // Email: valid and not used by another account.
        if (!isset($errors['email'])) {
            if (!validate_email($data['email'])) {
                $errors['email'] = get_string('err_invalidemail', 'local_academy');
            } else if ($DB->record_exists_select('user', 'LOWER(email) = :email AND deleted = 0',
                    ['email' => $data['email']])) {
                $errors['email'] = get_string('reg_emailtaken', 'local_academy');
            } else if (($emailerror = email_is_not_allowed($data['email']))) {
                $errors['email'] = $emailerror;
            }
        }

        if (!isset($errors['password'])) {
            $policyerror = '';
            if (!check_password_policy($data['password'], $policyerror)) {
                // Moodle returns one <div> per broken rule: make it one plain-text line.
                $policyerror = trim(strip_tags(preg_replace('~</div>\s*<div>~', ' ', (string) $policyerror)));
                $errors['password'] = $policyerror ?: get_string('err_weakpassword', 'local_academy');
            }
        }
        if (!$agreed) {
            $errors['agree'] = get_string('reg_mustagree', 'local_academy');
        }
        return $errors;
    }

    /**
     * Create the account. The user is active straight away (confirmed) and signs
     * in with their email and password.
     *
     * @param array $raw submitted values (form names, see FIELDS)
     * @param bool $agreed whether the terms were accepted
     * @return \stdClass the new user record (confirmed = 0 while it waits for the email)
     * @throws invalid_registration with the field errors when the data is not valid
     * @throws \moodle_exception registrationdisabled when self-registration is off
     */
    public static function register(array $raw, bool $agreed): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (!self::enabled()) {
            throw new \moodle_exception('err_registrationdisabled', 'local_academy');
        }
        $data = self::clean($raw);
        $errors = self::validate($data, $agreed);
        if ($errors) {
            throw new invalid_registration($errors);
        }
        self::throttle();

        $confirm = self::confirmation_required();
        $user = (object) [
            'auth'        => self::auth_method(),
            // Waits for the email to be confirmed (Moodle's email self-registration), else active at once.
            'confirmed'   => $confirm ? 0 : 1,
            'secret'      => $confirm ? random_string(15) : '',
            'mnethostid'  => $CFG->mnet_localhost_id,
            'username'    => self::unique_username($data['email']),
            'password'    => $data['password'],
            'email'       => $data['email'],
            'firstname'   => $data['firstname'],
            'lastname'    => $data['lastname'],
            'phone1'      => $data['phone'],
            'lang'        => current_language(),
            'calendartype' => $CFG->calendartype ?? 'gregorian',
        ];
        $user->id = user_create_user($user, true, true);

        $custom = [];
        foreach (self::PROFILE_MAP as $formname => $shortname) {
            $custom[$shortname] = $data[$formname];
        }
        profile_save_custom_fields($user->id, $custom);

        $created = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        if ($confirm && !send_confirmation_email($created, self::confirmation_url())) {
            // The account stays: signing in shows Moodle's "confirm your account" page with a resend button.
            debugging('local_academy: the confirmation email to user ' . $created->id . ' could not be sent.');
        }
        return $created;
    }

    /** Accounts one network address (IP) may create per hour. */
    const MAX_PER_HOUR = 10;

    /**
     * Count an account creation for the caller's IP; refuse past MAX_PER_HOUR
     * (stops scripted mass sign-ups through the public form / the shared token).
     *
     * @throws \moodle_exception toomanyregistrations
     */
    public static function throttle(): void {
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'local_academy', 'registrations');
        $key = sha1((string) getremoteaddr());
        $now = time();
        $hits = array_filter((array) ($cache->get($key) ?: []), static fn(int $t): bool => $t > $now - HOURSECS);
        if (count($hits) >= self::MAX_PER_HOUR) {
            throw new \moodle_exception('err_toomanyregistrations', 'local_academy');
        }
        $hits[] = $now;
        $cache->set($key, array_values($hits));
    }

    /**
     * A free username built from the email (students sign in with the email itself).
     *
     * @param string $email lower-case email
     * @return string
     */
    public static function unique_username(string $email): string {
        global $DB, $CFG;
        $base = clean_param($email, PARAM_USERNAME);
        if ($base === '') {
            $base = 'student';
        }
        $username = $base;
        $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . '_' . (++$i);
        }
        return $username;
    }
}
