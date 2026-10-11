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

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

use local_nit_core\helper\crypto;

/**
 * Integration tests for webhook secret encryption at the read side.
 *
 * @package    local_payments
 * @covers     \local_payments\manager
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_webhook_crypto_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        global $CFG;
        $CFG->nit_crypto_key = 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Insert a webhook row with its secret fields encrypted exactly as the live writer does.
     */
    private function make_webhook(array $cardinfo, array $sof = [], array $sigkeys = []): int {
        global $DB;
        return (int) $DB->insert_record('local_payments_webhooks', (object) [
            'provider_id'     => 1,
            'event_type'      => 'pay',
            'payload'         => '{}',
            'card_info'       => crypto::encrypt(json_encode($cardinfo), manager::ENCRYPTED_WEBHOOK_FIELDS['card_info']),
            'source_of_funds' => crypto::encrypt(json_encode($sof), manager::ENCRYPTED_WEBHOOK_FIELDS['source_of_funds']),
            'signature_keys'  => crypto::encrypt(json_encode($sigkeys), manager::ENCRYPTED_WEBHOOK_FIELDS['signature_keys']),
            'signature_valid' => 1,
            'status'          => 'received',
            'timecreated'     => time(),
        ]);
    }

    public function test_get_webhook_decrypts_fields(): void {
        global $DB;
        $card = ['last4' => '1111', 'brand' => 'visa'];
        $id = $this->make_webhook($card, ['type' => 'card'], ['sig' => 'abc']);

        // Raw row is ciphertext and must not leak plaintext.
        $raw = $DB->get_record('local_payments_webhooks', ['id' => $id], '*', MUST_EXIST);
        $this->assertTrue(crypto::is_encrypted($raw->card_info));
        $this->assertStringNotContainsString('1111', $raw->card_info);

        // get_webhook() returns the record with fields decrypted back to the original JSON.
        $webhook = manager::get_webhook($id);
        $this->assertNotNull($webhook);
        $this->assertSame($card, json_decode($webhook->card_info, true));
        $this->assertSame(['type' => 'card'], json_decode($webhook->source_of_funds, true));
        $this->assertSame(['sig' => 'abc'], json_decode($webhook->signature_keys, true));
    }

    public function test_get_webhook_missing_returns_null(): void {
        $this->assertNull(manager::get_webhook(999999));
    }

    public function test_get_webhook_must_exist_throws(): void {
        $this->expectException(\dml_exception::class);
        manager::get_webhook(999999, MUST_EXIST);
    }

    public function test_legacy_plaintext_row_passes_through(): void {
        global $DB;
        // A row written before encryption was enabled: card_info is plain JSON.
        $id = (int) $DB->insert_record('local_payments_webhooks', (object) [
            'provider_id' => 1,
            'event_type'  => 'pay',
            'payload'     => '{}',
            'card_info'   => json_encode(['last4' => '4242']),
            'timecreated' => time(),
        ]);
        $webhook = manager::get_webhook($id);
        $this->assertSame(['last4' => '4242'], json_decode($webhook->card_info, true));
    }

    public function test_decrypt_webhooks_list(): void {
        global $DB;
        $id1 = $this->make_webhook(['last4' => '1111']);
        $id2 = $this->make_webhook(['last4' => '2222']);

        $records = $DB->get_records_select('local_payments_webhooks', 'id IN (:a, :b)',
            ['a' => $id1, 'b' => $id2], 'id');
        manager::decrypt_webhooks($records);

        $this->assertSame('1111', json_decode($records[$id1]->card_info, true)['last4']);
        $this->assertSame('2222', json_decode($records[$id2]->card_info, true)['last4']);
    }
}
