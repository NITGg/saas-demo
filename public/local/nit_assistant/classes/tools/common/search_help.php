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

namespace local_nit_assistant\tools\common;

use local_nit_assistant\knowledge\help_index;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * Search the academy's own help content: CMS pages (about, FAQ, terms,
 * refund policy…), articles, and the notes staff wrote for the assistant.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_help extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'search_help';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Search the academy\'s help content: its pages (about, FAQ, terms, privacy, refund and payment '
            . 'policies, how-to), articles, and notes from the academy team. Use it for policies, how things work, '
            . 'payment methods, refunds, technical how-to and anything not covered by the other tools. Pass a few '
            . 'key words. Answer only from what it returns.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['sales', 'student', 'teacher'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 120]],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        $results = help_index::search($input['query'], $ctx->lang, $ctx->userid === 0);
        return self::ok([
            'results' => $results,
            'note' => $results ? null : 'Nothing found in the academy\'s help content.',
        ]);
    }
}
