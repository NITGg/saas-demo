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

namespace local_nit_assistant\tools;

use local_nit_assistant\runtime\state;
use local_nit_assistant\security\redaction;

/**
 * The tools of the assistant. The model only sees the tools for its mode; every
 * call is validated against the tool's schema, refused outside its mode,
 * deduplicated by idempotency key when it writes, and audited (redacted).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var tool[] */
    private array $tools;

    /**
     * Constructor.
     *
     * @param tool[]|null $tools null = the built-in set (fixed order keeps the prompt cache valid)
     */
    public function __construct(?array $tools = null) {
        $this->tools = $tools ?? [
            new common\academy_info(),
        ];
    }

    /**
     * Tools offered in a mode.
     *
     * @param string $mode
     * @return tool[]
     */
    public function for_mode(string $mode): array {
        return array_values(array_filter($this->tools, static fn(tool $t): bool => in_array($mode, $t->modes(), true)));
    }

    /**
     * Definitions for the model.
     *
     * @param string $mode
     * @return array list of ['name', 'description', 'schema']
     */
    public function definitions(string $mode): array {
        return array_map(static fn(tool $t): array => [
            'name' => $t->name(), 'description' => $t->description(), 'schema' => $t->schema(),
        ], $this->for_mode($mode));
    }

    /**
     * Run a tool call from the model.
     *
     * @param context $ctx
     * @param string $name
     * @param mixed $rawinput as parsed from the model's tool call
     * @param string $tooluseid
     * @return array ['ok' => true, 'data' => …] or ['ok' => false, 'code' => …, 'message' => …]
     */
    public function execute(context $ctx, string $name, mixed $rawinput, string $tooluseid): array {
        global $DB;
        $started = microtime(true);
        $input = is_array($rawinput) ? $rawinput : (is_object($rawinput) ? json_decode(json_encode($rawinput), true) : null);
        $done = function (array $result) use ($ctx, $name, $input, $started): array {
            $this->audit($ctx, $name, $input, $result, (int) round((microtime(true) - $started) * 1000));
            return $result;
        };

        $tool = null;
        foreach ($this->tools as $t) {
            if ($t->name() === $name) {
                $tool = $t;
            }
        }
        if (!$tool || !in_array($ctx->mode, $tool->modes(), true)) {
            return $done(['ok' => false, 'code' => 'not_authorized', 'message' => 'This tool is not available here.']);
        }
        if (!is_array($input)) {
            return $done(['ok' => false, 'code' => 'invalid_input', 'message' => 'Input must be an object.']);
        }
        $errors = schema::errors($input, $tool->schema());
        if ($errors) {
            $message = 'Invalid input — ' . implode('; ', array_slice($errors, 0, 5));
            return $done(['ok' => false, 'code' => 'invalid_input', 'message' => $message]);
        }
        if ($tool->writes()) {
            $status = $DB->get_field('local_nit_asst_conv', 'status', ['id' => $ctx->convid]);
            if ($status !== state::OPEN) {
                $message = 'A team member is handling this conversation now.';
                return $done(['ok' => false, 'code' => 'conflict', 'message' => $message]);
            }
        }
        $key = $tool->idempotency_key($ctx, $input, $tooluseid);
        if ($key !== null) {
            $prev = $DB->get_field('local_nit_asst_idem', 'result', ['idemkey' => substr($key, 0, 191)]);
            if ($prev !== false) {
                return $done(json_decode($prev, true));
            }
        }
        try {
            $result = $tool->run($ctx, $input);
        } catch (\Throwable $e) {
            debugging("local_nit_assistant: tool {$name} failed: " . $e->getMessage(), DEBUG_DEVELOPER);
            $result = ['ok' => false, 'code' => 'service_unavailable', 'message' => 'Could not do that right now.'];
        }
        if ($key !== null && !empty($result['ok'])) {
            try {
                $DB->insert_record('local_nit_asst_idem', (object) [
                    'idemkey' => substr($key, 0, 191), 'tool' => $name, 'result' => json_encode($result), 'timecreated' => time(),
                ]);
            } catch (\dml_write_exception $e) {
                unset($e); // Same call recorded concurrently — the first result stands.
            }
        }
        return $done($result);
    }

    /**
     * Write the audit row (inputs redacted).
     *
     * @param context $ctx
     * @param string $name
     * @param array|null $input
     * @param array $result
     * @param int $latencyms
     * @return void
     */
    private function audit(context $ctx, string $name, ?array $input, array $result, int $latencyms): void {
        global $DB;
        $DB->insert_record('local_nit_asst_audit', (object) [
            'convid' => $ctx->convid, 'turnid' => $ctx->turnid, 'userid' => $ctx->userid, 'mode' => $ctx->mode,
            'tool' => substr($name, 0, 100), 'args' => json_encode(redaction::value($input ?? [])),
            'status' => !empty($result['ok']) ? 'ok' : 'error', 'errorcode' => $result['code'] ?? null,
            'latencyms' => $latencyms, 'timecreated' => time(),
        ]);
    }
}
