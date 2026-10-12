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

/**
 * The model the runtime talks to. The Anthropic implementation is the only class
 * that touches the SDK; tests swap in a fake through \core\di.
 *
 * Request shape (array):
 *   model      string
 *   system     string   stable prompt (cached)
 *   volatile   ?string  per-request context after the cache breakpoint
 *   messages   list of ['role' => 'user', 'text' => string]
 *                      | ['role' => 'assistant', 'text' => string, 'raw' => ?array]  (raw = content blocks to replay inside a turn)
 *                      | ['role' => 'tool_results', 'results' => list<['id' => string, 'content' => string, 'iserror' => bool]>]
 *   tools      list of ['name' => string, 'description' => string, 'schema' => array]
 *   maxtokens  int
 *   effort     ?string  low | medium | high
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface client {
    /**
     * Run one model call. Text is passed to $ontext as it streams (may be null).
     *
     * @param array $request
     * @param callable|null $ontext function(string $delta): void
     * @return result
     * @throws \moodle_exception when the API can't be reached or refuses the request
     */
    public function call(array $request, ?callable $ontext = null): result;

    /**
     * Rough input-token estimate for the budget reservation (settled later with the real usage).
     *
     * @param array $request
     * @return int
     */
    public function estimate_input_tokens(array $request): int;
}
