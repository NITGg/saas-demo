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

namespace local_academysessions;

/**
 * The Jitsi JWT: signed with the site's secret only (no secret baked into the code),
 * and the warning for a site still on the value that used to ship in the code.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academysessions\jitsi_jwt
 */
final class jitsi_jwt_test extends \advanced_testcase {

    public function test_no_secret_means_no_token(): void {
        $this->resetAfterTest();
        set_config('jitsi_jwt_app_secret', '', 'local_academysessions');
        try {
            jitsi_jwt::generate('room', 'Name', 'a@example.com', false);
            $this->fail('a token was signed without a configured secret');
        } catch (\moodle_exception $e) {
            $this->assertSame('jitsinotconfigured', $e->errorcode);
        }
    }

    public function test_token_is_signed_with_the_site_secret(): void {
        $this->resetAfterTest();
        set_config('jitsi_jwt_app_secret', 'site-secret', 'local_academysessions');
        $jwt = jitsi_jwt::generate('room1', 'Name', 'a@example.com', true);
        [$header, $payload, $sig] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", 'site-secret', true)), '+/', '-_'), '=');
        $this->assertSame($expected, $sig);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertSame('room1', $claims['room']);
        $this->assertTrue($claims['context']['user']['moderator']);
        $this->assertSame('owner', $claims['context']['user']['affiliation']);
    }

    public function test_public_secret_is_detected(): void {
        $this->resetAfterTest();
        // The value that used to be the code fallback / setting default (public in git history).
        set_config('jitsi_jwt_app_secret', 'academy_jitsi_secret_2024_change_in_prod', 'local_academysessions');
        $this->assertTrue(jitsi_jwt::uses_public_secret('jitsi_jwt_app_secret'));
        set_config('jitsi_jwt_app_secret', 'a-new-random-value', 'local_academysessions');
        $this->assertFalse(jitsi_jwt::uses_public_secret('jitsi_jwt_app_secret'));
        set_config('jibri_notify_key', 'academy-cron-2024', 'local_academysessions');
        $this->assertTrue(jitsi_jwt::uses_public_secret('jibri_notify_key'));
        set_config('jibri_notify_key', '', 'local_academysessions');
        $this->assertFalse(jitsi_jwt::uses_public_secret('jibri_notify_key'));
    }
}
