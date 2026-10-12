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
 * Token usage of one model call (or a sum of calls).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usage {
    /**
     * Constructor.
     *
     * @param int $input uncached input tokens
     * @param int $output output tokens
     * @param int $cacheread input tokens read from the prompt cache
     * @param int $cachewrite input tokens written to the prompt cache
     */
    public function __construct(
        /** @var int */
        public int $input = 0,
        /** @var int */
        public int $output = 0,
        /** @var int */
        public int $cacheread = 0,
        /** @var int */
        public int $cachewrite = 0,
    ) {
    }

    /**
     * All input tokens the call was charged for.
     *
     * @return int
     */
    public function total_input(): int {
        return $this->input + $this->cacheread + $this->cachewrite;
    }

    /**
     * Add another call's usage to this one.
     *
     * @param usage $other
     * @return void
     */
    public function add(usage $other): void {
        $this->input += $other->input;
        $this->output += $other->output;
        $this->cacheread += $other->cacheread;
        $this->cachewrite += $other->cachewrite;
    }
}
