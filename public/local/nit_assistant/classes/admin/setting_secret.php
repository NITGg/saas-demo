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

namespace local_nit_assistant\admin;

use local_nit_core\helper\crypto;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * A secret setting that is always stored encrypted (local_nit_core crypto) and
 * never shown again. Saving the form with the placeholder keeps the stored value,
 * so re-saving the settings page can't write the secret back in plain text.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_secret extends \admin_setting_configpasswordunmask {
    /** Shown in the field when a value is saved; submitting it unchanged keeps the value. */
    public const PLACEHOLDER = '••••••••';

    /**
     * What the form shows: the placeholder when a value is saved, never the value.
     *
     * @return string|null
     */
    public function get_setting() {
        $raw = $this->config_read($this->name);
        if ($raw === null) {
            return null;
        }
        return $raw === '' ? '' : self::PLACEHOLDER;
    }

    /**
     * Encrypt and store; the placeholder keeps the saved value; empty clears it.
     *
     * @param string $data
     * @return string '' on success, an error message otherwise
     */
    public function write_setting($data) {
        $data = trim((string) $data);
        if ($data === self::PLACEHOLDER) {
            return '';
        }
        if ($data === '') {
            return $this->config_write($this->name, '') ? '' : get_string('errorsetting', 'admin');
        }
        if (!crypto::is_configured()) {
            return get_string('secretnocrypto', 'local_nit_assistant');
        }
        $stored = crypto::encrypt($data);
        return $this->config_write($this->name, $stored) ? '' : get_string('errorsetting', 'admin');
    }
}
