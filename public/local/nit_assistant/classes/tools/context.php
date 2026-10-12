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
 * Everything a tool may know about the caller. Built by the server from the
 * signed-in session — the model never supplies a user id, a mode or a price.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context {
    /** @var array<int,array{type:string,label:string,url:string}> Buttons collected during the turn. */
    public array $actions = [];

    /** @var array|null Set by a handoff tool: ['status' => string, 'message' => string]. */
    public ?array $handoff = null;

    /** @var callable[] Work to run after the reply (alerts, summaries). Best-effort. */
    public array $afterturn = [];

    /**
     * Constructor.
     *
     * @param int $convid
     * @param string $turnid
     * @param string $mode sales | student | teacher
     * @param string $channel web | app | whatsapp
     * @param string $lang ar | en
     * @param int $userid 0 for a visitor
     * @param int $now unix time of the turn
     */
    public function __construct(
        /** @var int */
        public readonly int $convid,
        /** @var string */
        public readonly string $turnid,
        /** @var string */
        public readonly string $mode,
        /** @var string */
        public readonly string $channel,
        /** @var string */
        public readonly string $lang,
        /** @var int */
        public readonly int $userid,
        /** @var int */
        public readonly int $now,
    ) {
    }
}
