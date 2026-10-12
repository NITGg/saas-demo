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

use local_nit_assistant\config;
use local_nit_assistant\inbox\alerts;
use local_nit_assistant\runtime\state;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * Hand the conversation to the academy's team: the assistant stops answering,
 * staff are alerted, and the user is told when to expect a reply.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class handoff_to_human extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'handoff_to_human';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Pass the conversation to a person from the academy team. Use it when the user asks for a person, '
            . 'is upset, has a payment/refund/account problem you cannot solve with your tools, or after you could '
            . 'not answer twice. After this you stop answering; the team replies in this chat. Write a short note '
            . 'for the team: who they are (if known), what they want, what you already told them.';
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
    public function writes(): bool {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'note' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 800, 'description' => 'For the team'],
            ],
            'required' => ['note'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        $note = trim($input['note']);
        if (!state::transition($ctx->convid, [state::OPEN], state::WAITING, ['summary' => $note])) {
            return self::fail('conflict', 'A team member is already handling this conversation.');
        }
        $hours = trim(helper::ml(config::working_hours(), $ctx->lang));
        $sm = get_string_manager();
        $message = $sm->get_string('msg_handoff', 'local_nit_assistant', null, $ctx->lang)
            . ($hours !== '' ? ' ' . $sm->get_string('msg_handoff_hours', 'local_nit_assistant', $hours, $ctx->lang) : '');
        $ctx->handoff = ['status' => state::WAITING, 'message' => $message];
        $ctx->afterturn[] = static fn() => alerts::handoff($ctx->convid, $note);
        return self::ok(['handed_off' => true, 'tell_user' => $message]);
    }
}
