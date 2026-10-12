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

namespace message_fcm;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/message/output/fcm/message_output_fcm.php');

/**
 * Unit tests for message_fcm plugin.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fcm_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test client configuration and project id detection.
     */
    public function test_fcm_configuration(): void {
        $isConfigured = fcm_client::is_configured();
        $this->assertTrue($isConfigured, 'FCM should be configured with the service account key.');

        $projectId = fcm_client::get_project_id();
        $this->assertEquals('bassthalk-ce6a8', $projectId);
    }

    /**
     * Test OAuth2 token fetching from Google Auth.
     */
    public function test_get_access_token(): void {
        $token = fcm_client::get_access_token();
        $this->assertNotEmpty($token, 'OAuth2 Access Token should be fetched successfully.');
        $this->assertIsString($token);
    }

    /**
     * Test message processor configuration checks.
     */
    public function test_message_processor(): void {
        $processor = new \message_output_fcm();
        $this->assertTrue($processor->is_system_configured());
        $this->assertTrue($processor->has_message_preferences());
    }

    /**
     * A key pasted in the settings page is used instead of the file, and a new key drops the cached token.
     */
    public function test_pasted_key_wins_over_file(): void {
        $key = ['type' => 'service_account', 'project_id' => 'pasted-project',
            'private_key' => 'not-a-real-key', 'client_email' => 'push@pasted-project.iam.gserviceaccount.com'];
        set_config('cached_token_data', json_encode(['token' => 'old', 'expires_at' => time() + 3600]), 'message_fcm');
        set_config('serviceaccountjson', json_encode($key, JSON_PRETTY_PRINT), 'message_fcm');
        fcm_client::reset_token_cache();

        $this->assertSame('pasted-project', fcm_client::get_project_id());
        $this->assertSame('push@pasted-project.iam.gserviceaccount.com', fcm_client::get_credentials_data()['client_email']);
        $this->assertSame(get_string('serviceaccountjson', 'message_fcm'), fcm_client::get_credentials_source());
        $this->assertFalse(get_config('message_fcm', 'cached_token_data'));
    }

    /**
     * The pasted-key setting accepts an empty value or a key, and rejects anything else.
     */
    public function test_serviceaccountjson_validation(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $setting = new admin_setting_serviceaccountjson('message_fcm/serviceaccountjson', 'k', 'd', '');
        $invalid = get_string('serviceaccountjson_invalid', 'message_fcm');

        $this->assertTrue($setting->validate(''));
        $this->assertSame($invalid, $setting->validate('hello'));
        $this->assertSame($invalid, $setting->validate('{"project_id":"x"}'));
        $this->assertTrue($setting->validate(json_encode(['project_id' => 'x', 'private_key' => 'k',
            'client_email' => 'a@x.iam.gserviceaccount.com'])));
    }

    /**
     * Test send_message handles users with no registered devices gracefully.
     */
    public function test_send_message_no_devices(): void {
        $user = $this->getDataGenerator()->create_user();
        $processor = new \message_output_fcm();

        $eventdata = new \stdClass();
        $eventdata->userto = $user;
        $eventdata->userfrom = $this->getDataGenerator()->create_user();
        $eventdata->subject = 'Test Notification';
        $eventdata->smallmessage = 'Hello world from test';
        $eventdata->savedmessageid = 123;

        $result = $processor->send_message($eventdata);
        $this->assertTrue($result);
    }
}
