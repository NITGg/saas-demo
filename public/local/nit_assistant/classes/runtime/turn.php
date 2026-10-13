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

namespace local_nit_assistant\runtime;

use local_nit_assistant\config;
use local_nit_assistant\knowledge\prompt;
use local_nit_assistant\llm\client;
use local_nit_assistant\llm\profiles;
use local_nit_assistant\llm\result;
use local_nit_assistant\llm\usage;
use local_nit_assistant\security\redaction;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\registry;

/**
 * One turn: the user's message (already stored), every model and tool call it
 * causes, and the final reply. Limits end the loop with a safe fallback text.
 * The reply is saved only if the conversation is still the assistant's when it
 * finishes — if a person took over meanwhile, it is discarded.
 *
 * Events passed to $emit (in order): delta {text}* · action {type,label,url}* ·
 * handoff {status,message}? · done {messageid|null}  — or error {code}.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class turn {
    /** Separator between text before and after a tool call. */
    private const PARAGRAPH = "\n\n";

    /**
     * Constructor.
     *
     * @param client $client the model
     * @param registry $tools
     * @param float $budget daily budget in USD
     * @param string $model chat model id
     * @param array $limits config::LIMITS-shaped
     */
    public function __construct(
        /** @var client */
        private client $client,
        /** @var registry */
        private registry $tools,
        /** @var float */
        private float $budget,
        /** @var string */
        private string $model,
        /** @var array */
        private array $limits = config::LIMITS,
    ) {
    }

    /**
     * The production turn: the academy's configured model, budget and tools.
     *
     * @return self
     */
    public static function create(): self {
        return new self(\core\di::get(client::class), new registry(), config::daily_budget(), config::chat_model());
    }

    /**
     * Run the turn for a conversation whose latest visitor message is stored.
     *
     * @param int $convid
     * @param callable $emit function(string $event, array $data): void
     * @param string|null $page the page the user is on
     * @param int|null $now
     * @return string replied | handoff | discarded | error
     */
    public function run(int $convid, callable $emit, ?string $page = null, ?int $now = null): string {
        global $DB, $SITE;
        $now ??= time();
        $started = microtime(true);
        $conv = $DB->get_record('local_nit_asst_conv', ['id' => $convid], '*', MUST_EXIST);
        $startversion = (int) $conv->stateversion;
        $turnid = \core\uuid::generate();
        $ctx = new context((int) $conv->id, $turnid, $conv->mode, $conv->channel, $conv->lang, (int) $conv->userid, $now);

        $system = prompt::system($conv->mode, format_string($SITE->fullname));
        $volatile = prompt::volatile($now, $conv->lang, $conv->userid > 0, $page, $conv->channel);
        $defs = $this->tools->definitions($conv->mode, $conv->channel);
        $messages = self::history($convid);

        $usage = new usage();
        $reply = '';
        $separator = false;
        $toolcalls = 0;
        $fallback = null;
        $error = null;
        $lastmodel = null;

        for ($iter = 0;; $iter++) {
            if ($iter >= $this->limits['maxiterations'] || (microtime(true) - $started) > $this->limits['turntimeout']) {
                $fallback = get_string('msg_turnlimit', 'local_nit_assistant');
                break;
            }
            $request = [
                'model' => $this->model, 'system' => $system, 'volatile' => $volatile, 'messages' => $messages, 'tools' => $defs,
                'maxtokens' => max(256, $this->limits['maxturnoutput'] - $usage->output), 'effort' => 'low',
            ];
            $est = $this->client->estimate_input_tokens($request);
            if ($iter > 0 && $usage->total_input() + $est > $this->limits['maxturninput']) {
                $fallback = get_string('msg_turnlimit', 'local_nit_assistant');
                break;
            }
            $reservation = budget::reserve(profiles::estimate($this->model, $est, $request['maxtokens']), $this->budget, $now);
            if (!$reservation) {
                $error = 'budget_exhausted';
                break;
            }

            $res = null;
            try {
                $res = $this->client->call($request, function (string $delta) use (&$reply, &$separator, $emit): void {
                    if ($separator) { // Text before and after a tool call are separate paragraphs.
                        $separator = false;
                        $reply .= self::PARAGRAPH;
                        $emit('delta', ['text' => self::PARAGRAPH]);
                    }
                    $reply .= $delta;
                    $emit('delta', ['text' => $delta]);
                });
            } catch (\Throwable $e) {
                $error = 'model_unavailable';
                debugging('local_nit_assistant: model call failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            } finally {
                budget::settle(
                    $reservation,
                    $res ? profiles::cost($res->model, $res->usage) : 0.0,
                    $res?->usage,
                    $res ? ['kind' => 'chat', 'model' => $res->model, 'convid' => $convid, 'userid' => (int) $conv->userid] : null
                );
            }
            if ($error) {
                break;
            }
            $lastmodel = $res->model;
            $usage->add($res->usage);

            if ($res->stopreason === result::REFUSAL) {
                $fallback = get_string('msg_turnlimit', 'local_nit_assistant');
                break;
            }
            if (!$res->toolcalls) {
                break; // Final answer.
            }
            if ($res->stopreason === result::MAX_TOKENS) {
                // A tool input cut off mid-way: never run it.
                $fallback = get_string('msg_turnlimit', 'local_nit_assistant');
                break;
            }
            // A person may have taken over while the model was writing.
            $live = $DB->get_record('local_nit_asst_conv', ['id' => $convid], 'status, stateversion');
            if ($live->status !== state::OPEN || (int) $live->stateversion !== $startversion) {
                break;
            }

            $results = [];
            $overlimit = false;
            foreach ($res->toolcalls as $call) {
                if ($toolcalls >= $this->limits['maxtoolcalls']) {
                    $overlimit = true;
                    break;
                }
                $toolcalls++;
                $r = $this->tools->execute($ctx, $call['name'], $call['input'], $call['id']);
                $results[] = ['id' => $call['id'], 'content' => json_encode($r), 'iserror' => empty($r['ok'])];
                $DB->insert_record('local_nit_asst_msg', (object) [
                    'convid' => $convid, 'role' => 'tool', 'toolname' => substr($call['name'], 0, 100),
                    'tooluseid' => substr($call['id'], 0, 100), 'turnid' => $turnid, 'status' => 'sent', 'timecreated' => time(),
                    'content' => json_encode(['input' => redaction::value($call['input']), 'ok' => !empty($r['ok']),
                        'code' => $r['code'] ?? null]),
                ]);
                if ($ctx->handoff) {
                    break;
                }
            }
            if ($overlimit) {
                $fallback = get_string('msg_turnlimit', 'local_nit_assistant');
                break;
            }
            if ($ctx->handoff) {
                break;
            }
            $separator = trim($reply) !== '';
            $messages[] = ['role' => 'assistant', 'text' => $res->text, 'raw' => $res->raw];
            $messages[] = ['role' => 'tool_results', 'results' => $results];
        }

        $tokens = [
            'tokensin' => (int) $conv->tokensin + $usage->total_input(),
            'tokensout' => (int) $conv->tokensout + $usage->output,
        ];

        if ($error) {
            $DB->update_record('local_nit_asst_conv', (object) (['id' => $convid] + $tokens));
            $emit('error', ['code' => $error]);
            return 'error';
        }

        // Our own handoff bumps stateversion by exactly one; anything else means a person stepped in.
        $live = $DB->get_record('local_nit_asst_conv', ['id' => $convid], 'status, stateversion');
        if ((int) $live->stateversion !== $startversion + ($ctx->handoff ? 1 : 0)) {
            $DB->update_record('local_nit_asst_conv', (object) (['id' => $convid] + $tokens));
            $emit('handoff', ['status' => $live->status, 'message' => '']);
            $emit('done', ['messageid' => null]);
            return 'discarded';
        }

        $text = trim($reply);
        if ($fallback) {
            $emit('delta', ['text' => ($text !== '' ? self::PARAGRAPH : '') . $fallback]);
            $text = $text !== '' ? $text . self::PARAGRAPH . $fallback : $fallback;
        }
        if ($ctx->handoff && $text === '') {
            $text = $ctx->handoff['message'];
        }
        $msgid = $DB->insert_record('local_nit_asst_msg', (object) [
            'convid' => $convid, 'role' => 'assistant', 'content' => $text !== '' ? $text : '…', 'status' => 'sent',
            'turnid' => $turnid, 'model' => $lastmodel, 'tokensin' => $usage->total_input(), 'tokensout' => $usage->output,
            'latencyms' => (int) round((microtime(true) - $started) * 1000), 'timecreated' => time(),
        ]);
        $DB->update_record('local_nit_asst_conv', (object) (['id' => $convid, 'messagecount' => (int) $conv->messagecount + 1,
            'lastmessageat' => time(), 'timemodified' => time()] + $tokens));

        $seen = [];
        foreach ($ctx->actions as $a) {
            if (!isset($seen[$a['url']])) {
                $seen[$a['url']] = true;
                $emit('action', $a);
            }
        }
        if ($ctx->handoff) {
            $emit('handoff', $ctx->handoff);
        }
        $emit('done', ['messageid' => (int) $msgid]);

        foreach ($ctx->afterturn as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                debugging('local_nit_assistant: after-turn task failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $ctx->handoff ? 'handoff' : 'replied';
    }

    /**
     * Past messages as plain text (visitor → user; assistant/staff → assistant), oldest first,
     * starting with the user.
     *
     * @param int $convid
     * @return array
     */
    public static function history(int $convid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(['visitor', 'assistant', 'staff'], SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'local_nit_asst_msg',
            "convid = :convid AND status = 'sent' AND role {$insql}",
            $params + ['convid' => $convid],
            'timecreated DESC, id DESC',
            'id, role, content',
            0,
            config::HISTORY
        );
        $msgs = [];
        foreach (array_reverse($rows) as $r) {
            $msgs[] = $r->role === 'visitor'
                ? ['role' => 'user', 'text' => $r->content]
                : ['role' => 'assistant', 'text' => $r->role === 'staff' ? '[Team member]: ' . $r->content : $r->content];
        }
        while ($msgs && $msgs[0]['role'] !== 'user') {
            array_shift($msgs);
        }
        return $msgs;
    }
}
