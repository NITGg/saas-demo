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

use local_nit_assistant\llm\result;
use local_nit_assistant\llm\usage;
use local_nit_assistant\runtime\budget;
use local_nit_assistant\runtime\state;
use local_nit_assistant\runtime\turn;
use local_nit_assistant\tests\fake_client;
use local_nit_assistant\tests\whoami_tool;
use local_nit_assistant\tools\registry;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/fake_client.php');
require_once(__DIR__ . '/fixtures/whoami_tool.php');

/**
 * One turn: replies, tool loop, limits, budget, takeover, failures.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\runtime\turn::class)]
final class turn_test extends \advanced_testcase {
    /** @var fake_client */
    private fake_client $llm;

    /** @var array<int,array{0:string,1:array}> Events emitted. */
    private array $events = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->llm = new fake_client();
        $this->events = [];
    }

    /**
     * A conversation with one visitor message.
     *
     * @param string $text
     * @param int $userid
     * @return int
     */
    private function conversation(string $text = 'Hello', int $userid = 0): int {
        global $DB;
        $id = $DB->insert_record('local_nit_asst_conv', (object) ['status' => state::OPEN, 'mode' => 'sales', 'lang' => 'en',
            'userid' => $userid, 'messagecount' => 1, 'timecreated' => time()]);
        $DB->insert_record('local_nit_asst_msg', (object) ['convid' => $id, 'role' => 'visitor', 'content' => $text,
            'timecreated' => time()]);
        return $id;
    }

    /**
     * Run a turn with the fake model.
     *
     * @param int $convid
     * @param array $tools
     * @param float $budget
     * @param array $limits
     * @return string
     */
    private function run_turn(int $convid, ?array $tools = null, float $budget = 5.0, array $limits = config::LIMITS): string {
        $t = new turn($this->llm, new registry($tools), $budget, 'claude-sonnet-5-5', $limits);
        return $t->run($convid, function (string $event, array $data): void {
            $this->events[] = [$event, $data];
        });
    }

    /**
     * Event names in order.
     *
     * @return string[]
     */
    private function names(): array {
        return array_column($this->events, 0);
    }

    public function test_a_plain_reply_streams_is_saved_and_costed(): void {
        global $DB;
        $id = $this->conversation('What do you teach?');
        $this->llm->script = [fake_client::text('We teach English.')];
        $this->assertSame('replied', $this->run_turn($id));
        $this->assertSame(['delta', 'done'], $this->names());
        $saved = $DB->get_record('local_nit_asst_msg', ['convid' => $id, 'role' => 'assistant']);
        $this->assertSame('We teach English.', $saved->content);
        $this->assertSame((int) $saved->id, $this->events[1][1]['messageid']);
        $conv = $DB->get_record('local_nit_asst_conv', ['id' => $id]);
        $this->assertEquals(2, $conv->messagecount);
        $this->assertEquals(1000, $conv->tokensin);
        $this->assertSame(1, $DB->count_records('local_nit_asst_event', ['convid' => $id, 'kind' => 'chat']));
        // The model got the stable prompt, the history and the tools of the mode.
        $req = $this->llm->requests[0];
        $this->assertStringContainsString('AI assistant', $req['system']);
        $this->assertSame([['role' => 'user', 'text' => 'What do you teach?']], $req['messages']);
        $this->assertSame(
            array_column((new \local_nit_assistant\tools\registry())->definitions('sales'), 'name'),
            array_column($req['tools'], 'name')
        );
        $this->assertNotContains('capture_lead', array_column(
            (new \local_nit_assistant\tools\registry())->definitions('student'),
            'name'
        ), 'each mode gets only its own tools');
    }

    public function test_tools_run_for_the_signed_in_user_never_who_the_model_says(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $id = $this->conversation('who am I?', (int) $user->id);
        $tool = new whoami_tool();
        $this->llm->script = [
            fake_client::tools([['whoami', ['userid' => 2]]], 'Let me check.'),
            fake_client::text('You are you.'),
        ];
        $this->assertSame('replied', $this->run_turn($id, [$tool]));
        $this->assertSame([(int) $user->id], $tool->seen, 'the tool ran as the conversation\'s user, not userid 2');
        $this->assertSame(['delta', 'delta', 'delta', 'action', 'done'], $this->names());
        $this->assertSame("Let me check.\n\nYou are you.", $DB->get_field(
            'local_nit_asst_msg',
            'content',
            ['convid' => $id, 'role' => 'assistant']
        ));
        // The second request carried the assistant turn and the tool result.
        $second = $this->llm->requests[1]['messages'];
        $this->assertSame('tool_results', end($second)['role']);
        $this->assertSame(1, $DB->count_records('local_nit_asst_msg', ['convid' => $id, 'role' => 'tool']));
    }

    public function test_limits_end_the_turn_with_the_fallback_text(): void {
        $id = $this->conversation();
        $tool = new whoami_tool();
        // The model keeps calling tools: the tool-call limit stops it.
        $this->llm->script = array_fill(0, 5, fake_client::tools([['whoami', []]]));
        $this->assertSame('replied', $this->run_turn($id, [$tool], 5.0, ['maxtoolcalls' => 2] + config::LIMITS));
        $this->assertCount(2, $tool->seen);
        $this->assertStringContainsString('couldn\'t complete', $this->text());
    }

    /**
     * All streamed text.
     *
     * @return string
     */
    private function text(): string {
        $deltas = array_filter($this->events, static fn($e) => $e[0] === 'delta');
        return implode('', array_map(static fn($e) => $e[1]['text'] ?? '', $deltas));
    }

    public function test_a_refusal_or_a_truncated_tool_call_never_runs_the_tool(): void {
        $id = $this->conversation();
        $tool = new whoami_tool();
        $cut = fake_client::tools([['whoami', []]]);
        $cut->stopreason = result::MAX_TOKENS;
        $this->llm->script = [$cut];
        $this->run_turn($id, [$tool]);
        $this->assertSame([], $tool->seen);
        $this->assertStringContainsString('couldn\'t complete', $this->text());

        $this->events = [];
        $id2 = $this->conversation();
        $this->llm->script = [new result('', [], result::REFUSAL, new usage(10, 0), 'claude-sonnet-5-5')];
        $this->assertSame('replied', $this->run_turn($id2, [$tool]));
        $this->assertStringContainsString('couldn\'t complete', $this->text());
    }

    public function test_no_budget_left_means_no_model_call(): void {
        $id = $this->conversation();
        $this->llm->script = [fake_client::text('never')];
        $this->assertSame('error', $this->run_turn($id, null, 0.0));
        $this->assertSame([['error', ['code' => 'budget_exhausted']]], $this->events);
        $this->assertSame([], $this->llm->requests);
    }

    public function test_a_model_failure_is_an_error_and_releases_the_reservation(): void {
        global $DB;
        $id = $this->conversation();
        $this->llm->script = [new \moodle_exception('error_model', 'local_nit_assistant')];
        $this->assertSame('error', @$this->run_turn($id));
        $this->resetDebugging();
        $this->assertSame([['error', ['code' => 'model_unavailable']]], $this->events);
        $row = $DB->get_record('local_nit_asst_usage', ['day' => budget::day()]);
        $this->assertEqualsWithDelta(0.0, (float) $row->committed, 1e-9);
        $this->assertFalse($DB->record_exists('local_nit_asst_msg', ['convid' => $id, 'role' => 'assistant']));
    }

    public function test_a_reply_finished_after_a_person_took_over_is_discarded(): void {
        global $DB;
        $id = $this->conversation();
        $this->llm->beforereply = static function () use ($id): void {
            state::take_over($id, 42);
        };
        $this->llm->script = [fake_client::text('Late answer')];
        $this->assertSame('discarded', $this->run_turn($id));
        $this->assertFalse($DB->record_exists('local_nit_asst_msg', ['convid' => $id, 'role' => 'assistant']));
        $this->assertSame(['delta', 'handoff', 'done'], $this->names());
        $this->assertSame(['messageid' => null], end($this->events)[1]);
    }

    public function test_history_starts_with_the_user_and_marks_staff_replies(): void {
        global $DB;
        $id = $this->conversation('first');
        $now = time();
        foreach ([['staff', 'Hi from Mona'], ['tool', '{}'], ['visitor', 'thanks']] as $i => [$role, $content]) {
            $DB->insert_record(
                'local_nit_asst_msg',
                (object) ['convid' => $id, 'role' => $role, 'content' => $content, 'timecreated' => $now + $i + 1]
            );
        }
        $this->assertSame([
            ['role' => 'user', 'text' => 'first'],
            ['role' => 'assistant', 'text' => '[Team member]: Hi from Mona'],
            ['role' => 'user', 'text' => 'thanks'],
        ], turn::history($id));
    }
}
