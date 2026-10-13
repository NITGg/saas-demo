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

namespace local_nit_core\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * App-layer encryption helper for sensitive values stored in the database.
 *
 * Why this exists: MariaDB TDE / disk encryption only protects data at rest on
 * disk — the live database still returns plaintext to anyone with the password,
 * and a `mysqldump` is plaintext. Values passed through this helper are stored as
 * ciphertext INSIDE the database, so a DB dump, a stolen password, SQL injection,
 * or a curious DBA cannot read them.
 *
 * Use it ONLY for small secrets you do not need to search / sort / index on
 * (payment card data, provider secrets, webhook signature keys, tokens). Never
 * for fields core Moodle searches (email, idnumber, …), and never for password
 * hashes (those are already one-way).
 *
 * The master key lives in config.php as $CFG->nit_crypto_key and is NEVER stored
 * in the database. See docs/crypto.md.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crypto {

    /** Marker + format version, so we can detect encrypted values and rotate algorithms later. */
    private const PREFIX = 'ENC1:';

    /** AAD/context bound to encrypted core user email values (deterministic). */
    public const USER_EMAIL_CONTEXT = 'core_user.email';

    /** @var string|null Cached raw 32-byte key. */
    private static $key = null;

    /**
     * Encrypt a string. Null in → null out. Output is "ENC1:<algo>.<base64>".
     *
     * @param string|null $plaintext Value to encrypt.
     * @param string $context Optional associated data (e.g. 'local_payments.card_info').
     *                        Binds the ciphertext to that context — decryption must pass the same.
     * @return string|null
     */
    public static function encrypt(?string $plaintext, string $context = ''): ?string {
        if ($plaintext === null) {
            return null;
        }
        $key = self::key();

        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $context, $nonce, $key);
            if (function_exists('sodium_memzero')) {
                sodium_memzero($plaintext);
            }
            return self::PREFIX . 's.' . base64_encode($nonce . $cipher);
        }

        // OpenSSL AES-256-GCM fallback (both ship in the project's PHP 8.3 image).
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context);
        if ($cipher === false) {
            throw new \RuntimeException('local_nit_core crypto: encryption failed');
        }
        return self::PREFIX . 'o.' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Deterministic encryption: the SAME plaintext (for the same context) always
     * produces the SAME ciphertext, so encrypted values can still be matched with
     * `WHERE col = :val` (exact equality) — unlike {@see encrypt()}, which uses a
     * random nonce and so produces a different ciphertext every time.
     *
     * Use it for fields that must stay searchable by exact value (e.g. user email,
     * so forgot-password / duplicate checks keep working) while being unreadable in
     * a DB dump. Trade-off: equal plaintexts are visibly equal in the DB (equality
     * leakage) and it does NOT enable partial / LIKE / sorted search. Never use it
     * where equality leakage matters.
     *
     * Construction: AES-256-GCM with a SYNTHETIC IV derived as a PRF of the
     * plaintext+context (HMAC-SHA256 under a separate sub-key). Because the IV is a
     * function of the plaintext, identical inputs reuse the same IV (same
     * ciphertext) and distinct inputs get distinct IVs — so GCM's "never reuse an
     * IV for two different messages" rule still holds. Output: "ENC1:d.<base64>".
     *
     * @param string|null $plaintext
     * @param string $context Associated data; MUST match on decrypt.
     * @return string|null
     */
    public static function encrypt_deterministic(?string $plaintext, string $context = ''): ?string {
        if ($plaintext === null) {
            return null;
        }
        $key = self::key();
        // Separate sub-key for the synthetic IV, so the IV PRF and the cipher don't share the raw key.
        $ivkey = hash_hmac('sha256', 'nit-crypto-det-iv-v1', $key, true);
        $iv = substr(hash_hmac('sha256', $context . "\0" . $plaintext, $ivkey, true), 0, 12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context);
        if ($cipher === false) {
            throw new \RuntimeException('local_nit_core crypto: deterministic encryption failed');
        }
        return self::PREFIX . 'd.' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt a value produced by {@see encrypt()}.
     *
     * If the value is NOT in our format (i.e. a legacy plaintext row written
     * before encryption was switched on), it is returned unchanged — so existing
     * data keeps working and can be migrated gradually.
     *
     * @param string|null $value
     * @param string $context Must match the context used at encryption time.
     * @return string|null
     */
    public static function decrypt(?string $value, string $context = ''): ?string {
        if ($value === null || !self::is_encrypted($value)) {
            return $value;
        }
        $key = self::key();
        $body = substr($value, strlen(self::PREFIX));
        $algo = substr($body, 0, 2);
        $raw = base64_decode(substr($body, 2), true);
        if ($raw === false) {
            throw new \RuntimeException('local_nit_core crypto: corrupt ciphertext (base64)');
        }

        if ($algo === 's.') {
            $nn = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($raw, $nn), $context, substr($raw, 0, $nn), $key);
        } else if ($algo === 'o.' || $algo === 'd.') {
            // 'o.' = random-IV GCM, 'd.' = deterministic (synthetic-IV) GCM — same on-disk layout.
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context);
        } else {
            throw new \RuntimeException('local_nit_core crypto: unknown algorithm');
        }

        if ($plain === false) {
            // Wrong key, wrong context, or tampered data — authentication failed.
            throw new \RuntimeException('local_nit_core crypto: decryption/authentication failed');
        }
        return $plain;
    }

    /**
     * Is this value one of our ciphertexts (vs legacy plaintext)?
     *
     * @param string $value
     * @return bool
     */
    public static function is_encrypted(string $value): bool {
        return strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    /**
     * Is a usable key configured? Lets callers degrade gracefully.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        try {
            self::key();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Encrypt a user email for storage/lookup.
     *
     * Lowercases + trims first (core treats email case-insensitively), then
     * encrypts DETERMINISTICALLY so the stored ciphertext can still be matched by
     * exact equality (login-by-email / forgot-password / dedupe keep working).
     *
     * Fail-safe: null/empty, an already-encrypted value, or no configured key →
     * returned unchanged, so nothing breaks before the key/backfill are in place.
     *
     * @param string|null $email
     * @return string|null
     */
    public static function encrypt_user_email(?string $email): ?string {
        if ($email === null || $email === '' || self::is_encrypted($email) || !self::is_configured()) {
            return $email;
        }
        $normalised = \core_text::strtolower(trim($email));
        return self::encrypt_deterministic($normalised, self::USER_EMAIL_CONTEXT);
    }

    /**
     * Decrypt a stored user email for display/use.
     *
     * Fail-safe: legacy plaintext, empty, no key, or an unreadable value → returned
     * unchanged rather than throwing, so pages never fatal on a bad/absent key.
     *
     * @param string|null $value
     * @return string|null
     */
    public static function decrypt_user_email(?string $value): ?string {
        if ($value === null || $value === '' || !self::is_encrypted($value) || !self::is_configured()) {
            return $value;
        }
        try {
            return self::decrypt($value, self::USER_EMAIL_CONTEXT);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Decrypt the 'email' property of a user record (object or array) in place, if
     * present and encrypted. Returns the same record for convenience; null/false
     * pass through. Used at core read choke points.
     *
     * @param mixed $record
     * @return mixed
     */
    public static function decrypt_user_email_in($record) {
        if (is_object($record) && isset($record->email)) {
            $record->email = self::decrypt_user_email((string) $record->email);
        } else if (is_array($record) && isset($record['email'])) {
            $record['email'] = self::decrypt_user_email((string) $record['email']);
        }
        return $record;
    }

    /**
     * Read a plugin secret uniformly: prefer a config.php forced setting (kept
     * OUT of the DB entirely), else the DB setting — decrypting it if it was
     * stored encrypted. One call works whether a project keeps the secret in
     * config.php or encrypted in the DB.
     *
     * @param string $plugin e.g. 'vdocipher'
     * @param string $name   e.g. 'apisecret'
     * @return string|null
     */
    public static function config_secret(string $plugin, string $name): ?string {
        global $CFG;
        if (isset($CFG->forced_plugin_settings[$plugin][$name])) {
            return (string) $CFG->forced_plugin_settings[$plugin][$name];
        }
        $val = get_config($plugin, $name);
        if ($val === false || $val === null || $val === '') {
            return null;
        }
        return self::decrypt((string) $val);
    }

    /**
     * Decode and cache the 32-byte master key from $CFG->nit_crypto_key.
     * Accepts "base64:<...>", 64 hex chars, or 32 raw bytes.
     *
     * @return string
     */
    private static function key(): string {
        global $CFG;
        if (self::$key !== null) {
            return self::$key;
        }
        $raw = isset($CFG->nit_crypto_key) ? (string) $CFG->nit_crypto_key : '';
        if ($raw === '') {
            throw new \coding_exception(
                'local_nit_core crypto: $CFG->nit_crypto_key is not set. '
                . 'Add a 32-byte key in config.php (see local/nit_core/docs/crypto.md).');
        }
        if (strpos($raw, 'base64:') === 0) {
            $key = base64_decode(substr($raw, 7), true);
        } else if (strlen($raw) === 64 && ctype_xdigit($raw)) {
            $key = hex2bin($raw);
        } else {
            $key = $raw;
        }
        if ($key === false || strlen($key) !== 32) {
            throw new \coding_exception(
                'local_nit_core crypto: $CFG->nit_crypto_key must decode to exactly 32 bytes.');
        }
        return self::$key = $key;
    }
}
