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
 * A tool that throws (internal details must not reach the model).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boom_tool extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'boom';
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
    public function schema(): array {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        throw new \dml_exception('secret SQL detail');
    }
}
