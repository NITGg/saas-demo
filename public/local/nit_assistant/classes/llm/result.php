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
 * Outcome of one model call.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class result {
    /** Normal end of the reply. */
    public const END = 'end';
    /** The model wants to call tools. */
    public const TOOL_USE = 'tool_use';
    /** The reply hit the output limit. */
    public const MAX_TOKENS = 'max_tokens';
    /** The model declined the request. */
    public const REFUSAL = 'refusal';
    /** Any other stop reason. */
    public const OTHER = 'other';

    /**
     * Constructor.
     *
     * @param string $text the reply text (all text blocks joined)
     * @param array $toolcalls list of ['id' => string, 'name' => string, 'input' => mixed]
     * @param string $stopreason one of the constants above
     * @param usage $usage
     * @param string $model the model that answered (may differ from the request after a fallback)
     * @param array $raw the assistant content blocks, replayed verbatim within the same turn
     */
    public function __construct(
        /** @var string */
        public string $text,
        /** @var array */
        public array $toolcalls,
        /** @var string */
        public string $stopreason,
        /** @var usage */
        public usage $usage,
        /** @var string */
        public string $model,
        /** @var array */
        public array $raw = [],
    ) {
    }

    /**
     * Map the API's stop reason to ours.
     *
     * @param string|null $reason
     * @return string
     */
    public static function map_stop(?string $reason): string {
        return match ($reason) {
            'end_turn', 'stop_sequence' => self::END,
            'tool_use' => self::TOOL_USE,
            'max_tokens' => self::MAX_TOKENS,
            'refusal' => self::REFUSAL,
            default => self::OTHER,
        };
    }
}
