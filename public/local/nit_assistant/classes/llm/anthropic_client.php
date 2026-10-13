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

namespace local_nit_assistant\llm;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client as AnthropicClient;
use Anthropic\Lib\Streaming\MessageAccumulator;
use local_nit_assistant\local\sdk_loader;
use Psr\Http\Client\ClientInterface;

/**
 * Claude through the official PHP SDK (bundled, thirdpartylibs.xml), on Moodle's
 * own HTTP client so the site's proxy and blocked-host settings apply.
 *
 * - Streams every call (tool inputs stream too) and rebuilds the final message.
 * - Prompt caching: the stable system prompt carries the cache breakpoint; the
 *   per-request context goes after it.
 * - Sonnet / Opus opt into the server-side refusal fallback ("default" routing);
 *   Haiku has none, so a refusal there simply ends the turn.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class anthropic_client implements client {
    /** Beta header for the server-side refusal fallback. */
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';
    /** Models that accept the "default" fallback form. */
    private const FALLBACK_MODELS = ['claude-sonnet-5-5', 'claude-opus-5-5'];

    /** @var AnthropicClient */
    private AnthropicClient $sdk;

    /**
     * Constructor.
     *
     * @param string $apikey the academy's Anthropic key
     * @param float $timeout seconds per call (the turn has its own overall limit)
     * @param ClientInterface|null $transporter PSR-18 client; tests pass a mock (network only)
     */
    public function __construct(string $apikey, float $timeout = 30.0, ?ClientInterface $transporter = null) {
        sdk_loader::register();
        // A reply streams for a while (tool calls, then a long answer): give the whole response time,
        // and cut only when nothing arrives for $timeout seconds.
        $total = max(120.0, $timeout * 2);
        $transporter ??= new \core\http_client(['timeout' => $total, 'read_timeout' => $timeout, 'connect_timeout' => 10]);
        $this->sdk = new AnthropicClient(
            apiKey: $apikey,
            requestOptions: ['transporter' => $transporter, 'maxRetries' => 1, 'timeout' => $total],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function call(array $request, ?callable $ontext = null): result {
        $model = (string) $request['model'];
        $params = [
            'model' => $model,
            'maxTokens' => (int) $request['maxtokens'],
            'system' => self::system($request),
            'messages' => self::messages($request['messages']),
        ];
        if (!empty($request['tools'])) {
            $params['tools'] = array_map(static fn(array $t): array => [
                'name' => $t['name'],
                'description' => $t['description'],
                'inputSchema' => $t['schema'],
                'eagerInputStreaming' => true, // Inputs are validated by the registry before anything runs.
            ], $request['tools']);
        }
        if (!empty($request['effort'])) {
            $params['outputConfig'] = ['effort' => $request['effort']];
        }
        if (in_array($model, self::FALLBACK_MODELS, true)) {
            $params['betas'] = [self::FALLBACK_BETA];
            $params['fallbacks'] = 'default';
        }

        try {
            $stream = $this->sdk->beta->messages->createStream(...$params);
            $acc = MessageAccumulator::forBetaMessages();
            foreach ($stream as $event) {
                $acc->accumulate($event);
                if ($ontext && $event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                    $ontext($event->delta->text);
                }
            }
            $msg = $acc->message();
        } catch (\Throwable $e) {
            throw new \moodle_exception('error_model', 'local_nit_assistant', '', null, get_class($e) . ': ' . $e->getMessage());
        }

        $text = '';
        $calls = [];
        $raw = [];
        foreach ($msg->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
                $raw[] = ['type' => 'text', 'text' => (string) $block->text];
            } else if ($block instanceof BetaToolUseBlock) {
                $input = self::tool_input($block);
                $calls[] = ['id' => $block->id, 'name' => $block->name, 'input' => $input];
                $raw[] = ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $input];
            } else {
                $raw[] = $block; // Anything else is replayed as the SDK gave it.
            }
        }
        $u = $msg->usage;
        return new result(
            $text,
            $calls,
            result::map_stop($msg->stopReason),
            new usage(
                $u->inputTokens,
                $u->outputTokens,
                (int) ($u->cacheReadInputTokens ?? 0),
                (int) ($u->cacheCreationInputTokens ?? 0)
            ),
            $msg->model,
            $raw,
        );
    }

    /**
     * A tool call's input as an array. A tool without parameters is called with an empty
     * input, which the SDK can't hand back as ->input ("property is overridden"), so read the
     * raw payload: an array, a JSON string (possibly empty) or nothing.
     *
     * @param BetaToolUseBlock $block
     * @return array
     */
    private static function tool_input(BetaToolUseBlock $block): array {
        $input = $block['input'] ?? null;
        if (is_string($input)) {
            $input = trim($input) === '' ? [] : json_decode($input, true);
        } else if (is_object($input)) {
            $input = json_decode(json_encode($input), true);
        }
        return is_array($input) ? $input : [];
    }

    /**
     * {@inheritDoc}
     */
    public function estimate_input_tokens(array $request): int {
        // About 3.5 characters per token for mixed Arabic/English, rounded up. Only used for the
        // budget reservation, which is settled with the real usage afterwards.
        $chars = strlen($request['system'] ?? '') + strlen($request['volatile'] ?? '')
            + strlen(json_encode($request['tools'] ?? []));
        foreach ($request['messages'] as $m) {
            $chars += $m['role'] === 'tool_results' ? strlen(json_encode($m['results'])) : strlen($m['text'] ?? '');
            if (!empty($m['raw'])) {
                $chars += strlen(json_encode($m['raw']));
            }
        }
        return (int) ceil($chars / 3.5) + 50;
    }

    /**
     * System blocks: the stable prompt (cache breakpoint), then the volatile context.
     *
     * @param array $request
     * @return array
     */
    private static function system(array $request): array {
        $blocks = [['type' => 'text', 'text' => (string) $request['system'], 'cacheControl' => ['type' => 'ephemeral']]];
        if (!empty($request['volatile'])) {
            $blocks[] = ['type' => 'text', 'text' => (string) $request['volatile']];
        }
        return $blocks;
    }

    /**
     * Our message list → the SDK's.
     *
     * @param array $messages
     * @return array
     */
    private static function messages(array $messages): array {
        return array_map(static function (array $m): array {
            if ($m['role'] === 'user') {
                return ['role' => 'user', 'content' => (string) $m['text']];
            }
            if ($m['role'] === 'assistant') {
                // Inside a turn the reply's blocks are replayed; a tool call's empty input must go back as {}.
                if (empty($m['raw'])) {
                    return ['role' => 'assistant', 'content' => (string) $m['text'] ?: '…'];
                }
                $content = array_map(static function ($b) {
                    if (is_array($b) && ($b['type'] ?? '') === 'tool_use' && empty($b['input'])) {
                        $b['input'] = new \stdClass();
                    }
                    return $b;
                }, $m['raw']);
                return ['role' => 'assistant', 'content' => $content];
            }
            return ['role' => 'user', 'content' => array_map(static fn(array $r): array => array_filter([
                'type' => 'tool_result',
                'toolUseID' => $r['id'],
                'content' => (string) $r['content'],
                'isError' => !empty($r['iserror']) ? true : null,
            ], static fn($v) => $v !== null), $m['results'])];
        }, $messages);
    }
}
