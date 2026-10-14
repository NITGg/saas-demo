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

namespace local_nit_core\admin;

use local_nit_core\helper\crypto;

defined('MOODLE_INTERNAL') || die();

/**
 * A password/unmask admin setting that is stored ENCRYPTED in the DB but shows
 * the REAL secret in the UI.
 *
 * Behaviour vs the stock admin_setting_configpasswordunmask:
 *  - On read (rendering the form): the stored ciphertext is DECRYPTED, so the
 *    admin sees the real secret when they press the "eye"/unmask button —
 *    instead of the raw "ENC1:…" blob.
 *  - On write (saving the form): the entered secret is ENCRYPTED before it hits
 *    the DB. So editing a secret via the UI keeps it encrypted at rest — there
 *    is no longer any need to re-run cli/encrypt_config_secrets.php afterwards.
 *
 * The DB therefore always holds ciphertext (a dump / stolen password / DBA still
 * cannot read it), while authorised admins on the web see the plaintext.
 *
 * Uses the SAME (empty) AAD context as crypto::config_secret(), so values already
 * backfilled by cli/encrypt_config_secrets.php decrypt here unchanged, and values
 * saved here are read back fine by the consuming code.
 *
 * Fails safe: with no key configured, or a legacy-plaintext / corrupt value, the
 * raw stored value is shown rather than throwing — the settings page never dies.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_encryptedpasswordunmask extends \admin_setting_configpasswordunmask {

    /**
     * Decrypt the stored value so the UI (and the unmask "eye") shows the real secret.
     *
     * @param string $name
     * @return mixed the decrypted value, or the raw value if it cannot be decrypted
     */
    public function config_read($name) {
        $value = parent::config_read($name);
        if ($value === null || $value === '' || !is_string($value)) {
            return $value;
        }
        if (!crypto::is_configured() || !crypto::is_encrypted($value)) {
            // No key, or a legacy plaintext value — show as-is, don't break the page.
            return $value;
        }
        try {
            return crypto::decrypt($value);
        } catch (\Throwable $e) {
            // Wrong key / corrupt ciphertext: surface the raw value rather than a fatal.
            return $value;
        }
    }

    /**
     * Encrypt the secret before storing it, so UI edits stay encrypted at rest.
     *
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function config_write($name, $value) {
        if (is_string($value) && $value !== '' && crypto::is_configured()
                && !crypto::is_encrypted($value)) {
            $value = crypto::encrypt($value);
        }
        return parent::config_write($name, $value);
    }
}
