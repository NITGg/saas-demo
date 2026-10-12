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

/**
 * Encrypted text profile field.
 *
 * Stores its value as application-layer ciphertext in mdl_user_info_data.data
 * (via \local_nit_core\helper\crypto) and transparently decrypts it for the
 * edit form and profile display. Behaves like a single-line text input.
 *
 * @package    profilefield_encrypted
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_nit_core\helper\crypto;

/**
 * Class profile_field_encrypted
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_encrypted extends profile_field_base {

    /**
     * Context string bound to the ciphertext. Must be identical on encrypt and
     * decrypt or authentication fails. See \local_nit_core\helper\crypto.
     */
    const CRYPTO_CONTEXT = 'profilefield_encrypted';

    /**
     * Overwrite the base class to display the (already decrypted) data.
     *
     * @return string
     */
    public function display_data() {
        // $this->data has already been decrypted in set_user_data().
        return format_string($this->data);
    }

    /**
     * Add fields for editing an encrypted text profile field.
     *
     * Renders as a plain single-line text box; the value shown/edited is
     * plaintext because set_user_data() decrypts it on load.
     *
     * @param moodleform $mform
     */
    public function edit_field_add($mform) {
        $size = $this->field->param1;
        $maxlength = $this->field->param2;

        // Create the form field.
        $mform->addElement('text', $this->inputname, format_string($this->field->name),
                    'maxlength="'.$maxlength.'" size="'.$size.'" ');
        $mform->setType($this->inputname, PARAM_TEXT);
    }

    /**
     * Process the data before it gets saved in database: encrypt it.
     *
     * The base edit_save_data() stores the return value of this method into
     * user_info_data.data, so returning ciphertext is what lands on disk.
     *
     * @param string|null $data
     * @param stdClass $datarecord
     * @return string|null
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        if ($data === null) {
            return null;
        }

        // Respect the configured maximum length on the plaintext value.
        $data = core_text::substr((string) $data, 0, $this->field->param2);

        // Nothing to protect for an empty value; keep it as plaintext empty so
        // is_empty() and default-data handling behave normally.
        if ($data === '') {
            return '';
        }

        // Only encrypt when a key is configured; otherwise store as-is. The
        // helper's decrypt() passes such legacy/plaintext values through
        // unchanged, so the field keeps working and can be migrated later.
        if (crypto::is_configured()) {
            return crypto::encrypt($data, self::CRYPTO_CONTEXT);
        }
        return $data;
    }

    /**
     * Convert external data (e.g. CSV import) for later processing by
     * edit_save_data_preprocess().
     *
     * @param string $data
     * @return string|null
     */
    public function convert_external_data($data) {
        if (core_text::strlen($data) > $this->field->param2) {
            return null;
        }
        return $data;
    }

    /**
     * Set user data, decrypting the stored value first.
     *
     * This is the single load hook used by BOTH code paths:
     *  - the constructor when field+user data is pre-fetched
     *    (profile_get_user_fields_with_data()), and
     *  - load_data() when the field is instantiated by id.
     * As a result $this->data always holds plaintext after load, so the edit
     * form (edit_load_user_data) and display_data() show plaintext.
     *
     * decrypt() is null-safe and passes non-ciphertext values through
     * unchanged, so legacy plaintext rows keep working.
     *
     * @param mixed $data
     * @param int $dataformat
     */
    public function set_user_data($data, $dataformat) {
        if (is_string($data) && $data !== '') {
            $data = crypto::decrypt($data, self::CRYPTO_CONTEXT);
        }
        parent::set_user_data($data, $dataformat);
    }

    /**
     * Return the field type and null properties.
     * This will be used for validating the data submitted by a user.
     *
     * @return array the param type and null property
     */
    public function get_field_properties() {
        return array(PARAM_TEXT, NULL_NOT_ALLOWED);
    }
}
