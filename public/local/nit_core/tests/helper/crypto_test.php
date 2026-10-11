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
 * Tests for the app-layer encryption helper.
 *
 * @package    local_nit_core
 * @covers     \local_nit_core\helper\crypto
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class crypto_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        global $CFG;
        $CFG->nit_crypto_key = 'base64:' . base64_encode(random_bytes(32));
    }

    public function test_round_trip(): void {
        $secret = '{"pan":"411111******1111","exp":"12/29"}';
        $enc = crypto::encrypt($secret);

        $this->assertTrue(crypto::is_encrypted($enc));
        $this->assertStringNotContainsString('411111', $enc, 'ciphertext must not leak plaintext');
        $this->assertSame($secret, crypto::decrypt($enc));
    }

    public function test_two_encryptions_differ(): void {
        // Random nonce → same input encrypts to different ciphertexts.
        $this->assertNotSame(crypto::encrypt('same'), crypto::encrypt('same'));
    }

    public function test_null_passthrough(): void {
        $this->assertNull(crypto::encrypt(null));
        $this->assertNull(crypto::decrypt(null));
    }

    public function test_legacy_plaintext_is_returned_unchanged(): void {
        // Rows written before encryption was switched on must keep working.
        $this->assertSame('legacy-plain', crypto::decrypt('legacy-plain'));
        $this->assertFalse(crypto::is_encrypted('legacy-plain'));
    }

    public function test_tampered_ciphertext_is_rejected(): void {
        $enc = crypto::encrypt('top secret');
        $tampered = 'ENC1:' . substr($enc, 5, 2) . strrev(substr($enc, 7));
        $this->expectException(\Throwable::class);
        crypto::decrypt($tampered);
    }

    public function test_wrong_context_is_rejected(): void {
        $enc = crypto::encrypt('value', 'local_payments.card_info');
        $this->expectException(\Throwable::class);
        crypto::decrypt($enc, 'local_payments.signature_keys');
    }

    public function test_missing_key_throws(): void {
        global $CFG;
        unset($CFG->nit_crypto_key);
        $this->assertFalse(crypto::is_configured());
        $this->expectException(\coding_exception::class);
        crypto::encrypt('x');
    }

    public function test_config_secret_prefers_forced_setting(): void {
        global $CFG;
        set_config('apisecret', crypto::encrypt('db-value'), 'vdocipher');
        // DB value (encrypted) is decrypted transparently.
        $this->assertSame('db-value', crypto::config_secret('vdocipher', 'apisecret'));

        // A forced config.php setting wins and is returned as-is.
        $CFG->forced_plugin_settings['vdocipher']['apisecret'] = 'config-value';
        $this->assertSame('config-value', crypto::config_secret('vdocipher', 'apisecret'));
    }
}
