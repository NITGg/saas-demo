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
 * The server-side rules for student data — the same rules the registration form
 * checks in the browser (register.php validateStep / isValidPhone), so a request
 * that skips the browser (curl, the mobile API, an old app) is held to them too.
 *
 * Used by the registration (web + API), the web profile page and the API profile
 * update. A student's own phone and national ID belong to one account only.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_rules {

    /** Country code added to local numbers when phones are compared. */
    const COUNTRY_CODE = '20';

    /**
     * What is wrong with a person's name part: required, no digits, 2+ characters.
     *
     * @param string $value trimmed value
     * @return string local_academy string id of the error, '' when valid
     */
    public static function name_error(string $value): string {
        if ($value === '') {
            return 'reg_required';
        }
        if (preg_match('/\d/u', $value)) {
            return 'err_namedigits';
        }
        return \core_text::strlen($value) < 2 ? 'err_nameshort' : '';
    }

    /**
     * What is wrong with a free-text answer such as the school or guardian job.
     *
     * @param string $value trimmed value
     * @return string local_academy string id of the error, '' when valid
     */
    public static function text_error(string $value): string {
        if ($value === '') {
            return 'reg_required';
        }
        return \core_text::strlen($value) < 2 ? 'err_textshort' : '';
    }

    /**
     * Whether a phone number is valid — the registration form's rule: an Egyptian
     * mobile (010/011/012/015 + 8 digits, optionally with +20 / 0020 / 20), an
     * international number (+ and 8–15 digits) or another 8–15 digit number that
     * is not a broken 01… mobile. Spaces, dashes and brackets are ignored.
     *
     * @param string $phone
     * @return bool
     */
    public static function valid_phone(string $phone): bool {
        $clean = preg_replace('/[\s\-()]/u', '', self::ascii_digits(trim($phone)));
        return (bool) preg_match('/^01[0125][0-9]{8}$/', $clean)
            || (bool) preg_match('/^(?:\+20|0020|20)0?1[0125][0-9]{8}$/', $clean)
            || (bool) preg_match('/^\+[1-9][0-9]{7,14}$/', $clean)
            || ((bool) preg_match('/^[0-9]{8,15}$/', $clean) && strpos($clean, '01') !== 0);
    }

    /**
     * Whether a national ID is valid: 14 digits.
     *
     * @param string $nationalid
     * @return bool
     */
    public static function valid_nationalid(string $nationalid): bool {
        return (bool) preg_match('/^[0-9]{14}$/', $nationalid);
    }

    /**
     * Arabic-Indic digits → 0-9.
     *
     * @param string $value
     * @return string
     */
    public static function ascii_digits(string $value): string {
        return strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    /**
     * A phone as digits with the country code, so 010…, +2010…, 0020 10… and
     * "010 1234 5678" compare equal ('' when there are no digits).
     *
     * @param string $phone
     * @return string
     */
    public static function normalize_phone(string $phone): string {
        $d = (string) preg_replace('/\D+/', '', self::ascii_digits($phone));
        if (strpos($d, '00') === 0) {
            $d = substr($d, 2);
        }
        if (strpos($d, self::COUNTRY_CODE) === 0 && strlen($d) >= strlen(self::COUNTRY_CODE) + 9) {
            return $d;
        }
        $d = ltrim($d, '0');
        return $d === '' ? '' : self::COUNTRY_CODE . $d;
    }

    /**
     * Whether another (not deleted) account already uses this phone as its own
     * phone number, whatever spaces or prefix either was typed with.
     *
     * @param string $phone
     * @param int $exceptuserid the account being saved (0 for a new one)
     * @return bool
     */
    public static function phone_taken(string $phone, int $exceptuserid = 0): bool {
        global $DB;
        $wanted = self::normalize_phone($phone);
        if ($wanted === '') {
            return false;
        }
        // Candidates end with the same 8 digits (whatever they were saved with); exact compare below.
        $stripped = 'phone1';
        foreach ([' ', '-', '(', ')', '+'] as $char) {
            $stripped = "REPLACE($stripped, '$char', '')";
        }
        $phones = $DB->get_fieldset_sql(
            "SELECT phone1 FROM {user}
              WHERE deleted = 0 AND id <> :userid AND " . $DB->sql_like($stripped, ':tail'),
            ['userid' => $exceptuserid, 'tail' => '%' . substr($wanted, -8)]);
        foreach ($phones as $other) {
            if (self::normalize_phone((string) $other) === $wanted) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether another (not deleted) account already has this national ID.
     *
     * @param string $nationalid
     * @param int $exceptuserid the account being saved (0 for a new one)
     * @return bool
     */
    public static function nationalid_taken(string $nationalid, int $exceptuserid = 0): bool {
        global $DB;
        if ($nationalid === '') {
            return false;
        }
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {user_info_data} d
               JOIN {user_info_field} f ON f.id = d.fieldid AND f.shortname = 'nationalid'
               JOIN {user} u ON u.id = d.userid AND u.deleted = 0
              WHERE d.userid <> :userid AND " . $DB->sql_compare_text('d.data', 255) . ' = ' .
                $DB->sql_compare_text(':nid', 255),
            ['userid' => $exceptuserid, 'nid' => $nationalid]);
    }

    /**
     * Hook: a user saving their own account anywhere else — Moodle's own Edit
     * profile page (/user/edit.php) included — cannot set an invalid phone or
     * another account's phone. Staff who may edit users are not limited.
     *
     * @param \core_user\hook\before_user_updated $hook
     * @throws \moodle_exception err_invalidphone / err_phonetaken
     */
    public static function before_user_updated(\core_user\hook\before_user_updated $hook): void {
        global $USER;
        $user = $hook->user;
        if (!isset($user->phone1) || empty($hook->currentuserdata) || empty($USER->id)
                || (int) $user->id !== (int) $USER->id
                || has_capability('moodle/user:update', \context_system::instance())) {
            return;
        }
        $errors = self::profile_errors((int) $user->id, ['phone' => trim((string) $user->phone1)],
            ['phone' => (string) $hook->currentuserdata->phone1]);
        if ($errors) {
            throw new \moodle_exception(reset($errors), 'local_academy');
        }
    }

    /**
     * Check the values a student changes on their profile (web page or app).
     * A value is checked only when it differs from the saved one, so an older
     * account whose saved data predates these rules can still save other changes.
     *
     * @param int $userid
     * @param array $changes any of firstname, lastname, phone (Moodle's phone1) and
     *        the profile fields secondname, thirdname, nationalid, school, guardianjob,
     *        fatherphone, motherphone, parentphone — trimmed values
     * @param array $current the saved values under the same keys
     * @return array<string, string> key => local_academy string id of the error (empty = valid)
     */
    public static function profile_errors(int $userid, array $changes, array $current): array {
        $errors = [];
        foreach ($changes as $key => $value) {
            $value = (string) $value;
            if ($value === (string) ($current[$key] ?? '')) {
                continue;
            }
            $error = '';
            if (in_array($key, ['firstname', 'lastname', 'secondname', 'thirdname'], true)) {
                // Moodle needs a first and last name; the other two may be left empty.
                $error = ($value === '' && !in_array($key, ['firstname', 'lastname'], true)) ? '' : self::name_error($value);
            } else if (in_array($key, ['school', 'guardianjob'], true)) {
                $error = $value === '' ? '' : self::text_error($value);
            } else if (in_array($key, ['phone', 'fatherphone', 'motherphone', 'parentphone'], true)) {
                if ($value !== '' && !self::valid_phone($value)) {
                    $error = 'err_invalidphone';
                } else if ($key === 'phone' && $value !== '' && self::phone_taken($value, $userid)) {
                    $error = 'err_phonetaken';
                }
            } else if ($key === 'nationalid' && $value !== '') {
                if (!self::valid_nationalid($value)) {
                    $error = 'err_invalidnationalid';
                } else if (self::nationalid_taken($value, $userid)) {
                    $error = 'err_nationalidtaken';
                }
            }
            if ($error !== '') {
                $errors[$key] = $error;
            }
        }
        return $errors;
    }
}
