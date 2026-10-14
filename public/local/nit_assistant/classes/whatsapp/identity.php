<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\whatsapp;

use local_nit_assistant\chat\access;
use local_nit_assistant\runtime\rate_limit;

/**
 * Linking a WhatsApp number to an account. A phone number alone never
 * identifies anyone: the user gives their account email, gets a 6-digit code by
 * email and types it in WhatsApp. The code is checked before the model — it never
 * reaches the model, the chat history or the logs — and only its hash is kept
 * while it is pending. The answer is the same whether the email has an account or
 * not, so nobody can probe for accounts.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class identity {
    /** A link lasts this long (days). */
    public const LINK_DAYS = 90;

    /** A code is valid for this long (seconds). */
    public const PENDING = 15 * MINSECS;

    /** Codes one conversation may ask for per hour. */
    public const STARTS_PER_HOUR = 3;

    /** Wrong codes allowed before the pending code is dropped. */
    public const MAX_ATTEMPTS = 5;

    /**
     * The account this number is linked to, if the link is still valid.
     *
     * @param string $phone E.164
     * @param int|null $now
     * @return int|null user id
     */
    public static function active(string $phone, ?int $now = null): ?int {
        global $DB;
        $now ??= time();
        $row = $DB->get_record('local_nit_asst_waid', ['phone' => $phone]);
        if (!$row || (int) $row->expiresat <= $now) {
            return null;
        }
        $user = \core_user::get_user((int) $row->userid);
        return $user && !$user->deleted && !$user->suspended ? (int) $user->id : null;
    }

    /**
     * Send a code to the account with this email (if there is one) and remember it, hashed.
     *
     * @param \stdClass $conv the WhatsApp conversation
     * @param string $email
     * @param int|null $now
     * @return string sent | rate_limited
     */
    public static function start(\stdClass $conv, string $email, ?int $now = null): string {
        global $DB;
        $now ??= time();
        if (!rate_limit::hit("wa:verify:{$conv->id}", self::STARTS_PER_HOUR, HOURSECS, $now)['allowed']) {
            return 'rate_limited';
        }
        $email = \core_text::strtolower(trim($email));
        $user = validate_email($email)
            ? $DB->get_record_select(
                'user',
                'LOWER(email) = ? AND deleted = 0 AND suspended = 0 AND confirmed = 1',
                [$email],
                '*',
                IGNORE_MULTIPLE
            )
            : null;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if ($user) {
            $lang = str_starts_with((string) $user->lang, 'ar') ? 'ar' : 'en';
            $sm = get_string_manager();
            $a = (object) ['code' => $code, 'site' => format_string(get_site()->fullname), 'minutes' => self::PENDING / MINSECS];
            email_to_user(
                $user,
                \core_user::get_noreply_user(),
                $sm->get_string('wa_code_subject', 'local_nit_assistant', $a, $lang),
                $sm->get_string('wa_code_body', 'local_nit_assistant', $a, $lang)
            );
        }
        $DB->set_field('local_nit_asst_conv', 'verification', json_encode([
            'userid' => $user ? (int) $user->id : 0,
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'at' => $now,
            'attempts' => 0,
        ]), ['id' => $conv->id]);
        return 'sent';
    }

    /**
     * The conversation's pending verification, if still valid.
     *
     * @param \stdClass $conv
     * @param int|null $now
     * @return array|null
     */
    public static function pending(\stdClass $conv, ?int $now = null): ?array {
        $now ??= time();
        $p = json_decode((string) ($conv->verification ?? ''), true);
        return is_array($p) && !empty($p['hash']) && $now - (int) $p['at'] <= self::PENDING ? $p : null;
    }

    /**
     * A message that is just a 6-digit code (spaces, dashes and Arabic digits allowed), else null.
     *
     * @param string $text
     * @return string|null
     */
    public static function extract_code(string $text): ?string {
        $digits = strtr(preg_replace('/[\s\-]+/u', '', $text), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        return preg_match('/^\d{6}$/', $digits) ? $digits : null;
    }

    /**
     * Check a typed code; links the number on success.
     *
     * @param \stdClass $conv
     * @param array $pending
     * @param string $code
     * @param int|null $now
     * @return string verified | wrong_code | expired
     */
    public static function confirm(\stdClass $conv, array $pending, string $code, ?int $now = null): string {
        global $DB;
        $now ??= time();
        $pending['attempts'] = (int) $pending['attempts'] + 1;
        if ($pending['attempts'] > self::MAX_ATTEMPTS) {
            $DB->set_field('local_nit_asst_conv', 'verification', null, ['id' => $conv->id]);
            return 'expired';
        }
        $userid = (int) $pending['userid'];
        if ($userid <= 0 || !password_verify($code, $pending['hash'])) {
            $DB->set_field('local_nit_asst_conv', 'verification', json_encode($pending), ['id' => $conv->id]);
            return 'wrong_code';
        }
        $link = ['phone' => $conv->phone, 'userid' => $userid, 'verifiedat' => $now,
            'expiresat' => $now + self::LINK_DAYS * DAYSECS];
        $existing = $DB->get_record('local_nit_asst_waid', ['phone' => $conv->phone], 'id');
        if ($existing) {
            $DB->update_record('local_nit_asst_waid', (object) (['id' => $existing->id] + $link));
        } else {
            $DB->insert_record('local_nit_asst_waid', (object) $link);
        }
        $DB->update_record('local_nit_asst_conv', (object) ['id' => $conv->id, 'verification' => null, 'userid' => $userid,
            'mode' => access::mode($userid), 'timemodified' => $now]);
        return 'verified';
    }

    /**
     * Forget a number's link (e.g. the user asks, or staff unlink it).
     *
     * @param string $phone
     * @return void
     */
    public static function unlink(string $phone): void {
        global $DB;
        $DB->delete_records('local_nit_asst_waid', ['phone' => $phone]);
    }
}
