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

/**
 * One tool the model may call. Tools read the academy's data as the caller
 * (context::$userid) and return plain arrays; they never trust the model for who
 * the caller is.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class tool {
    /** Error codes a tool may return — nothing else reaches the model. */
    public const ERRORS = ['invalid_input', 'validation_failed', 'not_found', 'not_authorized', 'rate_limited',
        'conflict', 'service_unavailable'];

    /**
     * Tool name as the model sees it (snake_case).
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * What it does and when to use it (written for the model).
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * Modes it is offered in.
     *
     * @return string[] subset of sales | student | teacher
     */
    abstract public function modes(): array;

    /**
     * JSON schema of the input (type object, additionalProperties false).
     *
     * @return array
     */
    abstract public function schema(): array;

    /**
     * Run it. Return ok() or fail().
     *
     * @param context $ctx
     * @param array $input already validated against schema()
     * @return array
     */
    abstract public function run(context $ctx, array $input): array;

    /**
     * Channels it is offered on.
     *
     * @return string[] subset of web | app | whatsapp
     */
    public function channels(): array {
        return ['web', 'app', 'whatsapp'];
    }

    /**
     * Writes something? Writing tools are refused once a person owns the conversation.
     *
     * @return bool
     */
    public function writes(): bool {
        return false;
    }

    /**
     * Idempotency key for writing tools (null = no dedupe).
     *
     * @param context $ctx
     * @param array $input
     * @param string $tooluseid
     * @return string|null
     */
    public function idempotency_key(context $ctx, array $input, string $tooluseid): ?string {
        return $this->writes() ? $this->name() . ':' . $ctx->convid . ':' . $tooluseid : null;
    }

    /**
     * Successful result.
     *
     * @param mixed $data
     * @return array
     */
    final protected static function ok(mixed $data): array {
        return ['ok' => true, 'data' => $data];
    }

    /**
     * Failed result with one of self::ERRORS.
     *
     * @param string $code
     * @param string $message for the model (no internals)
     * @return array
     */
    final protected static function fail(string $code, string $message): array {
        $code = in_array($code, self::ERRORS, true) ? $code : 'service_unavailable';
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }
}
