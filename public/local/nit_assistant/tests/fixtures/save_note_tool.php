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

namespace local_nit_assistant\tests;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * A writing tool that counts its runs (idempotency and takeover tests).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_note_tool extends tool {
    /** @var int How many times run() executed. */
    public int $runs = 0;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'save_note';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'test';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['sales'];
    }

    /**
     * {@inheritDoc}
     */
    public function writes(): bool {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['note'],
            'properties' => ['note' => ['type' => 'string', 'maxLength' => 20], 'email' => ['type' => 'string']],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        $this->runs++;
        return self::ok(['saved' => true, 'runs' => $this->runs]);
    }
}
