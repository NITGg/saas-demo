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

namespace mod_jitsi;

use local_nit_core\helper\crypto;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

/**
 * record_notify.php's key check: the Jibri finalize script sends the plain key, the site
 * may keep it encrypted.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::jitsi_notify_key_valid
 */
final class record_notify_test extends \advanced_testcase {

    public function test_plain_key(): void {
        $this->resetAfterTest();
        set_config('jibri_notify_key', 'the-key', 'local_academysessions');
        $this->assertTrue(jitsi_notify_key_valid('the-key'));
        $this->assertFalse(jitsi_notify_key_valid('another-key'));
        $this->assertFalse(jitsi_notify_key_valid(null));
    }

    public function test_encrypted_key(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->nit_crypto_key = 'base64:' . base64_encode(random_bytes(32));
        // As local/nit_core/cli/encrypt_config_secrets.php stores it.
        set_config('jibri_notify_key', crypto::encrypt('the-key'), 'local_academysessions');
        $this->assertTrue(jitsi_notify_key_valid('the-key'));
        $this->assertFalse(jitsi_notify_key_valid(get_config('local_academysessions', 'jibri_notify_key')));
    }

    public function test_no_key_refuses_everything(): void {
        $this->resetAfterTest();
        set_config('jibri_notify_key', '', 'local_academysessions');
        $this->assertFalse(jitsi_notify_key_valid(''));
        $this->assertFalse(jitsi_notify_key_valid('anything'));
    }
}
