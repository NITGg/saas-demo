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

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use local_nit_assistant\llm\anthropic_client;
use local_nit_assistant\llm\result;

/**
 * The real SDK path against a canned Claude stream (only the network is mocked).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\llm\anthropic_client::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\local\sdk_loader::class)]
final class anthropic_client_test extends \basic_testcase {
    /** @var array Requests the mock received. */
    private array $sent = [];

    /**
     * A client whose HTTP layer returns $responses in order.
     *
     * @param Response[] $responses
     * @return anthropic_client
     */
    private function client(array $responses): anthropic_client {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));
        return new anthropic_client('sk-ant-test', 10.0, new GuzzleClient(['handler' => $stack]));
    }

    /**
     * Server-sent events as the API streams them.
     *
     * @param array $events list of [type, data]
     * @return Response
     */
    private static function sse(array $events): Response {
        $body = '';
        foreach ($events as [$type, $data]) {
            $body .= "event: {$type}\ndata: " . json_encode($data) . "\n\n";
        }
        return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    /**
     * A streamed reply: text, then a tool call.
     *
     * @param string $model
     * @return Response
     */
    private static function reply(string $model = 'claude-sonnet-5-5'): Response {
        return self::sse([
            ['message_start', ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant',
                'model' => $model, 'content' => [], 'stop_reason' => null, 'stop_sequence' => null,
                'usage' => ['input_tokens' => 120, 'output_tokens' => 1, 'cache_read_input_tokens' => 800,
                    'cache_creation_input_tokens' => 0]]]],
            ['content_block_start', ['type' => 'content_block_start', 'index' => 0,
                'content_block' => ['type' => 'text', 'text' => '']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0,
                'delta' => ['type' => 'text_delta', 'text' => 'Hello ']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0,
                'delta' => ['type' => 'text_delta', 'text' => 'there.']]],
            ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]],
            ['content_block_start', ['type' => 'content_block_start', 'index' => 1,
                'content_block' => ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'find', 'input' => new \stdClass()]]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 1,
                'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"q": "eng']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 1,
                'delta' => ['type' => 'input_json_delta', 'partial_json' => 'lish"}']]],
            ['content_block_stop', ['type' => 'content_block_stop', 'index' => 1]],
            ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use', 'stop_sequence' => null],
                'usage' => ['output_tokens' => 42]]],
            ['message_stop', ['type' => 'message_stop']],
        ]);
    }

    /**
     * A request in the runtime's shape.
     *
     * @param string $model
     * @return array
     */
    private static function request(string $model = 'claude-sonnet-5-5'): array {
        return [
            'model' => $model, 'system' => 'You are the assistant.', 'volatile' => 'Current date: 2026-10-12',
            'messages' => [
                ['role' => 'user', 'text' => 'Do you teach English?'],
                ['role' => 'assistant', 'text' => '',
                    'raw' => [['type' => 'tool_use', 'id' => 'toolu_0', 'name' => 'find', 'input' => ['q' => 'x']]]],
                ['role' => 'tool_results', 'results' => [['id' => 'toolu_0', 'content' => '{"ok":true}', 'iserror' => false]]],
            ],
            'tools' => [['name' => 'find', 'description' => 'Find courses', 'schema' => ['type' => 'object',
                'properties' => ['q' => ['type' => 'string']], 'required' => ['q'], 'additionalProperties' => false]]],
            'maxtokens' => 1500, 'effort' => 'low',
        ];
    }

    public function test_a_streamed_reply_becomes_text_tool_calls_usage_and_model(): void {
        $streamed = '';
        $res = $this->client([self::reply()])->call(self::request(), function (string $d) use (&$streamed): void {
            $streamed .= $d;
        });
        $this->assertSame('Hello there.', $streamed);
        $this->assertSame('Hello there.', $res->text);
        $this->assertSame(result::TOOL_USE, $res->stopreason);
        $this->assertSame([['id' => 'toolu_1', 'name' => 'find', 'input' => ['q' => 'english']]], $res->toolcalls);
        $this->assertSame(120, $res->usage->input);
        $this->assertSame(42, $res->usage->output);
        $this->assertSame(800, $res->usage->cacheread);
        $this->assertSame('claude-sonnet-5-5', $res->model);
        $this->assertCount(2, $res->raw, 'raw blocks are kept for replay within the turn');
    }

    public function test_the_request_caches_the_stable_prompt_and_opts_into_refusal_fallback_on_sonnet(): void {
        $this->client([self::reply()])->call(self::request());
        $req = $this->sent[0]['request'];
        $this->assertSame('https://api.anthropic.com/v1/messages?beta=true', (string) $req->getUri());
        $this->assertSame('sk-ant-test', $req->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $req->getHeaderLine('anthropic-beta'));
        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertTrue($body['stream']);
        $this->assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control']);
        $this->assertSame('Current date: 2026-10-12', $body['system'][1]['text']);
        $this->assertArrayNotHasKey('cache_control', $body['system'][1], 'volatile context sits after the breakpoint');
        $this->assertSame(['effort' => 'low'], $body['output_config']);
        $this->assertSame('find', $body['tools'][0]['name']);
        $this->assertTrue($body['tools'][0]['eager_input_streaming']);
        $this->assertSame(['q'], $body['tools'][0]['input_schema']['required']);
        $toolresult = $body['messages'][2]['content'][0];
        $this->assertSame(['type' => 'tool_result', 'tool_use_id' => 'toolu_0', 'content' => '{"ok":true}'], $toolresult);
    }

    public function test_haiku_gets_no_fallback_and_api_errors_become_a_moodle_exception(): void {
        $this->client([self::reply('claude-haiku-5-5')])->call(self::request('claude-haiku-5-5'));
        $body = json_decode((string) $this->sent[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('fallbacks', $body);

        $bad = new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']])
        );
        $this->expectException(\moodle_exception::class);
        $this->client([$bad])->call(self::request());
    }

    public function test_input_estimate_grows_with_the_conversation(): void {
        $c = $this->client([]);
        $small = $c->estimate_input_tokens(['system' => 'x', 'messages' => [['role' => 'user', 'text' => 'hi']]]);
        $big = $c->estimate_input_tokens(['system' => 'x', 'messages' => [['role' => 'user', 'text' => str_repeat('a', 3500)]]]);
        $this->assertGreaterThan($small + 900, $big);
    }
}
