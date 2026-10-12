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

namespace local_nit_assistant\tools\sales;

use local_nit_assistant\inbox\alerts;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * Save a visitor's contact details so the team can call them back — only after
 * they agreed to be contacted. One lead per conversation: a second call updates it.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capture_lead extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'capture_lead';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Save the visitor\'s name and phone or email so the academy team can contact them about what they '
            . 'want. ONLY call it after the visitor clearly agreed to be contacted (set consent to true) — ask first. '
            . 'Never invent details; use exactly what they wrote.';
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
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                'phone' => ['type' => 'string', 'maxLength' => 30],
                'email' => ['type' => 'string', 'maxLength' => 120],
                'interest' => ['type' => 'string', 'maxLength' => 500, 'description' => 'What they want, in a sentence'],
                'consent' => ['type' => 'boolean', 'description' => 'They agreed to be contacted'],
            ],
            'required' => ['name', 'interest', 'consent'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $DB;
        if ($input['consent'] !== true) {
            return self::fail('validation_failed', 'Ask the visitor if the team may contact them first.');
        }
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $digits = preg_replace('/\D+/', '', strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        if ($phone !== '' && (strlen($digits) < 7 || strlen($digits) > 15)) {
            return self::fail('validation_failed', 'That phone number does not look complete. Ask for it again.');
        }
        if ($email !== '' && !validate_email($email)) {
            return self::fail('validation_failed', 'That email address does not look right. Ask for it again.');
        }
        if ($phone === '' && $email === '') {
            return self::fail('validation_failed', 'A phone number or an email is needed.');
        }
        $lead = (object) [
            'convid' => $ctx->convid,
            'userid' => $ctx->userid,
            'name' => trim($input['name']),
            'phone' => $phone !== '' ? ($phone[0] === '+' ? '+' : '') . $digits : null,
            'email' => $email !== '' ? \core_text::strtolower($email) : null,
            'interest' => trim($input['interest']),
            'consenttime' => $ctx->now,
            'timemodified' => $ctx->now,
        ];
        $existing = $DB->get_record('local_nit_asst_lead', ['convid' => $ctx->convid], 'id', IGNORE_MULTIPLE);
        if ($existing) {
            $lead->id = $existing->id;
            $DB->update_record('local_nit_asst_lead', $lead);
        } else {
            $lead->status = 'new';
            $lead->timecreated = $ctx->now;
            $lead->id = $DB->insert_record('local_nit_asst_lead', $lead);
            $ctx->afterturn[] = static fn() => alerts::lead($ctx->convid, $lead);
        }
        return self::ok(['saved' => true, 'message' => 'The team will contact them.']);
    }
}
