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

namespace local_nit_assistant\tools\student;

use local_nit_assistant\inbox\alerts;
use local_nit_assistant\runtime\rate_limit;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * Open a support ticket for a problem the assistant cannot solve (a payment
 * that went wrong, a lesson dispute, a technical fault, an account issue). Staff
 * see it in Tickets and are alerted; it is linked to this conversation.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class open_ticket extends tool {
    /** Categories. */
    public const CATEGORIES = ['billing', 'lesson', 'technical', 'account', 'other'];

    /** Tickets one user may open per day. */
    public const PER_DAY = 3;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'open_ticket';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Open a support ticket for the academy team when the user has a real problem you cannot fix with your '
            . 'tools: a payment charged but not delivered, a lesson or credit dispute, something broken, an account '
            . 'issue. First look it up (my_payments, my_lessons…) so the ticket has the facts. Write a clear subject '
            . 'and description with the details (dates, amounts, lesson, what they tried). Never put passwords or card '
            . 'numbers in it. Tell the user the ticket number.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['student'];
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
                'category' => ['type' => 'string', 'enum' => self::CATEGORIES],
                'subject' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 120],
                'description' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 2000],
            ],
            'required' => ['category', 'subject', 'description'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $DB;
        if ($ctx->userid <= 0) {
            return self::fail('not_authorized', 'Only signed-in students can open tickets.');
        }
        if (!rate_limit::hit("ticket:{$ctx->userid}", self::PER_DAY, DAYSECS, $ctx->now)['allowed']) {
            return self::fail('rate_limited', 'Too many tickets today. The team already has the earlier ones.');
        }
        $ticket = (object) [
            'convid' => $ctx->convid,
            'userid' => $ctx->userid,
            'category' => $input['category'],
            'subject' => trim($input['subject']),
            'description' => \local_nit_assistant\security\redaction::text(trim($input['description'])),
            'status' => 'open',
            'timecreated' => $ctx->now,
            'timemodified' => $ctx->now,
        ];
        $ticket->id = $DB->insert_record('local_nit_asst_ticket', $ticket);
        $ctx->afterturn[] = static fn() => alerts::ticket($ticket);
        return self::ok(['ticket_number' => (int) $ticket->id, 'message' => 'The team will follow up in this chat.']);
    }
}
