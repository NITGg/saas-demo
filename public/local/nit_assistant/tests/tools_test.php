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

use local_nit_assistant\runtime\state;
use local_nit_assistant\security\redaction;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\registry;
use local_nit_assistant\tools\schema;
use local_nit_assistant\tests\boom_tool;
use local_nit_assistant\tests\save_note_tool;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/save_note_tool.php');
require_once(__DIR__ . '/fixtures/boom_tool.php');

/**
 * The tool contract: schema validation, modes, writes after a takeover,
 * idempotency, redacted audit — and the first tool.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\registry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\security\redaction::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\common\academy_info::class)]
final class tools_test extends \advanced_testcase {
    /** @var int */
    private int $convid;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $conv = (object) ['status' => state::OPEN, 'mode' => 'sales', 'timecreated' => time()];
        $this->convid = $DB->insert_record('local_nit_asst_conv', $conv);
    }

    /**
     * Context for the test conversation.
     *
     * @param string $mode
     * @return context
     */
    private function ctx(string $mode = 'sales'): context {
        return new context($this->convid, 'turn-1', $mode, 'web', 'en', 0, time());
    }

    public function test_schema_refuses_wrong_types_unknown_fields_and_missing_required(): void {
        $s = ['type' => 'object', 'additionalProperties' => false, 'required' => ['n'],
            'properties' => ['n' => ['type' => 'integer', 'minimum' => 1], 'c' => ['type' => 'string', 'enum' => ['a', 'b']]]];
        $this->assertSame([], schema::errors(['n' => 2, 'c' => 'a'], $s));
        $this->assertNotEmpty(schema::errors(['n' => '2'], $s));
        $this->assertNotEmpty(schema::errors(['n' => 0], $s));
        $this->assertNotEmpty(schema::errors(['c' => 'a'], $s));
        $this->assertNotEmpty(schema::errors(['n' => 1, 'x' => 1], $s));
        $this->assertNotEmpty(schema::errors(['n' => 1, 'c' => 'z'], $s));
        $this->assertSame([], schema::errors([], ['type' => 'object', 'properties' => new \stdClass()]));
        $this->assertNotEmpty(schema::errors(['x' => 1], ['type' => 'object', 'properties' => new \stdClass()]));
    }

    public function test_redaction_masks_contacts_cards_and_secret_keys(): void {
        $this->assertSame('a***@acme.com', redaction::text('ahmed@acme.com'));
        $this->assertSame('call [phone …567]', redaction::text('call +20 100 123 4567'));
        $this->assertSame('[card]', redaction::text('4111 1111 1111 1111'));
        $v = redaction::value(['note' => 'mail me at sara@x.io', 'password' => 'hunter2', 'nested' => ['otp' => '123456']]);
        $this->assertSame(['note' => 'mail me at s***@x.io', 'password' => '[redacted]', 'nested' => ['otp' => '[redacted]']], $v);
    }

    public function test_tools_only_run_in_their_mode_with_valid_input_and_every_call_is_audited_redacted(): void {
        global $DB;
        $tool = new save_note_tool();
        $reg = new registry([$tool]);
        $this->assertSame(['save_note'], array_column($reg->definitions('sales'), 'name'));
        $this->assertSame([], $reg->definitions('student'));

        $this->assertSame('not_authorized', $reg->execute($this->ctx('student'), 'save_note', ['note' => 'x'], 't1')['code']);
        $this->assertSame('not_authorized', $reg->execute($this->ctx(), 'no_such_tool', [], 't2')['code']);
        $bad = $reg->execute($this->ctx(), 'save_note', ['note' => str_repeat('x', 30)], 't3');
        $this->assertSame('invalid_input', $bad['code']);
        $this->assertSame(0, $tool->runs, 'nothing ran on invalid input');

        $ok = $reg->execute($this->ctx(), 'save_note', ['note' => 'hi', 'email' => 'sara@acme.com'], 't4');
        $this->assertTrue($ok['ok']);
        $audit = $DB->get_records('local_nit_asst_audit', ['convid' => $this->convid], 'id');
        $this->assertCount(4, $audit);
        $last = end($audit);
        $this->assertSame('ok', $last->status);
        $this->assertStringContainsString('s***@acme.com', $last->args);
        $this->assertStringNotContainsString('sara@acme.com', $last->args);
    }

    public function test_a_write_runs_once_per_tool_call_and_never_after_a_person_took_over(): void {
        $tool = new save_note_tool();
        $reg = new registry([$tool]);
        $first = $reg->execute($this->ctx(), 'save_note', ['note' => 'hi'], 'same-id');
        $again = $reg->execute($this->ctx(), 'save_note', ['note' => 'hi'], 'same-id');
        $this->assertSame($first, $again);
        $this->assertSame(1, $tool->runs);

        state::take_over($this->convid, 99);
        $this->assertSame('conflict', $reg->execute($this->ctx(), 'save_note', ['note' => 'later'], 'new-id')['code']);
        $this->assertSame(1, $tool->runs);
    }

    public function test_a_failing_tool_returns_a_safe_error(): void {
        $reg = new registry([new boom_tool()]);
        $r = @$reg->execute($this->ctx(), 'boom', [], 'x');
        $this->resetDebugging();
        $this->assertSame(['ok' => false, 'code' => 'service_unavailable', 'message' => 'Could not do that right now.'], $r);
    }

    public function test_academy_info_returns_public_details_only(): void {
        global $CFG;
        set_config('support_phone', '+201001234567', 'theme_nit');
        $r = (new registry())->execute($this->ctx(), 'get_academy_info', [], 'a1');
        $this->assertTrue($r['ok']);
        $this->assertSame('+201001234567', $r['data']['contact']['whatsapp']);
        $this->assertSame($CFG->wwwroot, $r['data']['contact']['website']);
        $this->assertContains('en', $r['data']['languages']);
        foreach (['student', 'teacher'] as $mode) {
            $this->assertTrue((new registry())->execute($this->ctx($mode), 'get_academy_info', [], 'b' . $mode)['ok']);
        }
    }
}
