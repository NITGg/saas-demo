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

namespace local_nit_assistant;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_nit_assistant\admin\setting_secret;
use local_nit_assistant\privacy\provider;

/**
 * Privacy export/delete, and the encrypted API-key setting.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\admin\setting_secret::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\config::class)]
final class privacy_and_settings_test extends \core_privacy\tests\provider_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->nit_crypto_key = 'base64:' . base64_encode(random_bytes(32)); // As local_nit_core's crypto test does.
    }

    /**
     * A conversation with two messages and a tool call for $userid.
     *
     * @param int $userid
     * @return int
     */
    private function chat(int $userid): int {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_nit_asst_conv', (object) ['userid' => $userid, 'lang' => 'en', 'timecreated' => $now]);
        $DB->insert_record(
            'local_nit_asst_msg',
            (object) ['convid' => $id, 'role' => 'visitor', 'content' => "hello from {$userid}", 'timecreated' => $now]
        );
        $tool = (object) ['convid' => $id, 'role' => 'tool', 'content' => '{}', 'timecreated' => $now];
        $DB->insert_record('local_nit_asst_msg', $tool);
        $DB->insert_record('local_nit_asst_audit', (object) ['convid' => $id, 'turnid' => 't', 'userid' => $userid,
            'mode' => 'student', 'tool' => 'my_subscription', 'status' => 'ok', 'timecreated' => $now]);
        $DB->insert_record('local_nit_asst_event', (object) ['day' => '2026-10-12', 'kind' => 'chat', 'model' => 'm',
            'convid' => $id, 'userid' => $userid, 'cost' => 0.01, 'timecreated' => $now]);
        return $id;
    }

    public function test_a_users_conversations_are_exported_without_tool_rows(): void {
        $u = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $id = $this->chat((int) $u->id);
        $this->chat((int) $other->id);
        $ctx = \context_system::instance();

        $this->assertEquals([$ctx->id], provider::get_contexts_for_userid((int) $u->id)->get_contextids());
        provider::export_user_data(new approved_contextlist($u, 'local_nit_assistant', [$ctx->id]));
        $data = writer::with_context($ctx)->get_data([get_string('privacy:conversations', 'local_nit_assistant'), (string) $id]);
        $this->assertCount(1, $data->messages);
        $this->assertSame("hello from {$u->id}", $data->messages[0]['text']);

        $list = new userlist($ctx, 'local_nit_assistant');
        provider::get_users_in_context($list);
        $this->assertEqualsCanonicalizing([$u->id, $other->id], array_unique($list->get_userids()));
    }

    public function test_deleting_a_user_removes_only_their_data(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->chat((int) $u->id);
        $theirs = $this->chat((int) $other->id);

        provider::delete_data_for_user(new approved_contextlist($u, 'local_nit_assistant', [\context_system::instance()->id]));
        $this->assertFalse($DB->record_exists('local_nit_asst_conv', ['id' => $mine]));
        $this->assertSame(0, $DB->count_records('local_nit_asst_msg', ['convid' => $mine]));
        $this->assertSame(0, $DB->count_records('local_nit_asst_audit', ['userid' => $u->id]));
        $this->assertSame(0, $DB->count_records('local_nit_asst_event', ['userid' => $u->id]));
        $this->assertTrue($DB->record_exists('local_nit_asst_conv', ['id' => $theirs]));

        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), 'local_nit_assistant', [$other->id]));
        $this->assertSame(0, $DB->count_records('local_nit_asst_conv'));
    }

    public function test_the_api_key_is_stored_encrypted_and_never_shown_again(): void {
        $s = new setting_secret('local_nit_assistant/apikey', 'k', '', '');
        $this->assertSame('', $s->write_setting('sk-ant-secret-1'));
        $stored = get_config('local_nit_assistant', 'apikey');
        $this->assertStringStartsWith('ENC1:', $stored);
        $this->assertStringNotContainsString('sk-ant-secret-1', $stored);
        $this->assertSame(setting_secret::PLACEHOLDER, $s->get_setting());
        $this->assertSame('sk-ant-secret-1', config::apikey());

        // Re-saving the form with the placeholder keeps the key; an empty value clears it.
        $this->assertSame('', $s->write_setting(setting_secret::PLACEHOLDER));
        $this->assertSame('sk-ant-secret-1', config::apikey());
        $s->write_setting('');
        $this->assertNull(config::apikey());
    }

    public function test_the_assistant_is_usable_only_with_switch_key_and_budget(): void {
        $this->assertFalse(config::usable());
        set_config('enabled', 1, 'local_nit_assistant');
        (new setting_secret('local_nit_assistant/apikey', 'k', '', ''))->write_setting('sk-ant-x');
        $this->assertFalse(config::usable(), 'budget still 0');
        set_config('dailybudget', '2.5', 'local_nit_assistant');
        $this->assertTrue(config::usable());
        $this->assertSame('claude-sonnet-5-5', config::chat_model());
        $this->assertSame('claude-haiku-5-5', config::summary_model());
    }
}
