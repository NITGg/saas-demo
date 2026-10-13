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

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;
use local_nit_assistant\whatsapp\identity;

/**
 * On WhatsApp only: start linking the number to the user's account by sending
 * a 6-digit code to their account email. The user then types the code in the chat;
 * it is checked outside the model.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_whatsapp_account extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'link_whatsapp_account';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'On WhatsApp, link this number to the user\'s academy account so you can help with their own '
            . 'subscription, lessons and payments. Ask for the email of their account and pass it; a 6-digit code is '
            . 'emailed to that account (if it exists — the answer is the same either way). Then tell them to type the '
            . 'code here within 15 minutes. Never ask for passwords.';
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
    public function channels(): array {
        return ['whatsapp'];
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
            'properties' => ['email' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 120]],
            'required' => ['email'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $DB;
        $conv = $DB->get_record('local_nit_asst_conv', ['id' => $ctx->convid], '*', MUST_EXIST);
        if ($conv->channel !== 'whatsapp' || empty($conv->phone)) {
            return self::fail('not_authorized', 'Only on WhatsApp.');
        }
        if (!validate_email(trim($input['email']))) {
            return self::fail('validation_failed', 'That email address does not look right. Ask for it again.');
        }
        if (identity::start($conv, $input['email'], $ctx->now) === 'rate_limited') {
            return self::fail('rate_limited', 'Too many codes requested. Ask them to try again in an hour.');
        }
        return self::ok(['code_sent_if_account_exists' => true,
            'tell_user' => 'If an account has this email, a 6-digit code was sent to it. Type the code here within 15 minutes.']);
    }
}
