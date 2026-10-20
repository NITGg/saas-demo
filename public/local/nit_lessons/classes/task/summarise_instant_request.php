<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_nit_lessons\task;

use local_nit_lessons\service\instant_service;

/**
 * One-line AI summary of an instant request's note for the teachers' prompt (local_nit_assistant's
 * model client and key). Runs in the background so asking for a tutor is never slowed down; when
 * the request is no longer searching, or the AI is unavailable, it does nothing.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summarise_instant_request extends \core\task\adhoc_task {

    /** @var string Instructions for the model. */
    const SYSTEM = 'You help a tutor decide in seconds whether to accept a live lesson. Summarise the student\'s '
        . 'request in ONE short line (max 20 words), in the same language as the request: the topic, the exact '
        . 'problem, and the level if it is clear. No greetings, no advice, no personal data.';

    public function execute() {
        global $DB;
        $requestid = (int) ($this->get_custom_data()->requestid ?? 0);
        $r = $DB->get_record('nit_instant_request', ['id' => $requestid]);
        if (!$r || $r->status !== instant_service::SEARCHING || trim((string) $r->note) === ''
                || !class_exists('\local_nit_assistant\config') || !\local_nit_assistant\config::usable()) {
            return;
        }
        try {
            $client = \core\di::get(\local_nit_assistant\llm\client::class);
            $result = $client->call([
                'model' => \local_nit_assistant\config::summary_model(),
                'system' => self::SYSTEM,
                'messages' => [['role' => 'user', 'text' => 'Subject: ' . strip_tags(format_string($r->subject))
                    . "\nRequest: " . \core_text::substr((string) $r->note, 0, 1000)]],
                'tools' => [],
                'maxtokens' => 120,
            ]);
            (new instant_service())->set_summary($requestid, (string) $result->text);
        } catch (\Throwable $e) {
            mtrace('local_nit_lessons: instant summary failed: ' . $e->getMessage());
        }
    }
}
