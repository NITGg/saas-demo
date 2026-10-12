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

namespace local_nit_assistant\inbox;

use local_nit_assistant\chat\conversation;
use local_nit_assistant\runtime\state;

/**
 * The AI Inbox for the academy's staff: conversations by status, taking one
 * over, replying, handing it back to the assistant, closing it, and leads.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /** Lead statuses. */
    public const LEAD_STATUSES = ['new', 'contacted', 'won', 'lost'];

    /** Longest staff reply (characters). */
    public const MAXREPLY = 4000;

    /**
     * Conversations, newest activity first; those waiting for a person come first.
     *
     * @param string $status '' = all but closed
     * @param int $page
     * @param int $perpage
     * @return array [rows, total]
     */
    public static function conversations(string $status, int $page, int $perpage): array {
        global $DB;
        if ($status !== '') {
            $where = 'c.status = :status';
            $params = ['status' => $status];
        } else {
            $where = 'c.status <> :closed';
            $params = ['closed' => state::CLOSED];
        }
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {local_nit_asst_conv} c WHERE {$where}", $params);
        $rows = $DB->get_records_sql(
            "SELECT c.*, l.id AS leadid, l.name AS leadname
               FROM {local_nit_asst_conv} c
          LEFT JOIN {local_nit_asst_lead} l ON l.convid = c.id
              WHERE {$where} AND c.messagecount > 0
           ORDER BY CASE WHEN c.status = :waiting THEN 0 ELSE 1 END, c.lastmessageat DESC, c.id DESC",
            $params + ['waiting' => state::WAITING],
            $page * $perpage,
            $perpage
        );
        return [array_values($rows), $total];
    }

    /**
     * How many conversations per status (closed left out).
     *
     * @return array status => count
     */
    public static function counts(): array {
        global $DB;
        $rows = $DB->get_records_sql_menu(
            'SELECT status, COUNT(1) FROM {local_nit_asst_conv} WHERE status <> ? AND messagecount > 0 GROUP BY status',
            [state::CLOSED]
        );
        return array_map('intval', $rows) + [state::OPEN => 0, state::WAITING => 0, state::HUMAN => 0];
    }

    /**
     * Every message of a conversation for staff, tool calls included.
     *
     * @param int $convid
     * @param int $afterid
     * @return array
     */
    public static function messages(int $convid, int $afterid = 0): array {
        global $DB;
        return array_values($DB->get_records_select(
            'local_nit_asst_msg',
            "convid = ? AND id > ? AND status = 'sent'",
            [$convid, $afterid],
            'id ASC'
        ));
    }

    /**
     * Take a conversation over (single winner).
     *
     * @param int $convid
     * @param int $staffid
     * @return bool
     */
    public static function take_over(int $convid, int $staffid): bool {
        return state::take_over($convid, $staffid);
    }

    /**
     * Reply as staff. Replying to a conversation nobody owns takes it over first;
     * one another staff member owns is refused.
     *
     * @param int $convid
     * @param int $staffid
     * @param string $text
     * @return int the message id
     * @throws \moodle_exception
     */
    public static function reply(int $convid, int $staffid, string $text): int {
        global $DB;
        $text = trim($text);
        if ($text === '' || \core_text::strlen($text) > self::MAXREPLY) {
            throw new \moodle_exception('error_reply', 'local_nit_assistant');
        }
        $conv = $DB->get_record('local_nit_asst_conv', ['id' => $convid], '*', MUST_EXIST);
        if ($conv->status !== state::HUMAN) {
            state::take_over($convid, $staffid);
            $conv = $DB->get_record('local_nit_asst_conv', ['id' => $convid], '*', MUST_EXIST);
        }
        if ($conv->status !== state::HUMAN || (int) $conv->assignedto !== $staffid) {
            throw new \moodle_exception('error_notowner', 'local_nit_assistant');
        }
        return conversation::add_message($conv, 'staff', $text, ['staffid' => $staffid]);
    }

    /**
     * Give the conversation back to the assistant.
     *
     * @param int $convid
     * @return bool
     */
    public static function hand_back(int $convid): bool {
        return state::transition($convid, [state::HUMAN, state::WAITING], state::OPEN, ['assignedto' => null]);
    }

    /**
     * Close a conversation.
     *
     * @param int $convid
     * @return bool
     */
    public static function close(int $convid): bool {
        return state::transition($convid, [state::OPEN, state::WAITING, state::HUMAN], state::CLOSED);
    }

    /**
     * Leads, newest first.
     *
     * @param string $status '' = all
     * @param int $page
     * @param int $perpage
     * @return array [rows, total]
     */
    public static function leads(string $status, int $page, int $perpage): array {
        global $DB;
        $params = $status !== '' ? ['status' => $status] : [];
        $total = $DB->count_records('local_nit_asst_lead', $params);
        $rows = $DB->get_records('local_nit_asst_lead', $params, 'timecreated DESC, id DESC', '*', $page * $perpage, $perpage);
        return [array_values($rows), $total];
    }

    /**
     * Update a lead's status and notes.
     *
     * @param int $leadid
     * @param string $status
     * @param string $notes
     * @param int $staffid
     * @return void
     */
    public static function update_lead(int $leadid, string $status, string $notes, int $staffid): void {
        global $DB;
        if (!in_array($status, self::LEAD_STATUSES, true)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        $DB->get_record('local_nit_asst_lead', ['id' => $leadid], 'id', MUST_EXIST);
        $DB->update_record('local_nit_asst_lead', (object) [
            'id' => $leadid, 'status' => $status, 'notes' => \core_text::substr(trim($notes), 0, 2000),
            'assignedto' => $staffid, 'timemodified' => time(),
        ]);
    }

    /**
     * Usage and cost for the last $days days (today included).
     *
     * @param int $days
     * @param int|null $now
     * @return array ['days' => [...], 'models' => [...], 'totals' => [...]]
     */
    public static function usage(int $days = 30, ?int $now = null): array {
        global $DB;
        $now ??= time();
        $from = $now - ($days - 1) * DAYSECS;
        $fromday = \local_nit_assistant\runtime\budget::day($from);
        $daily = $DB->get_records_select(
            'local_nit_asst_usage',
            'day >= ?',
            [$fromday],
            'day DESC',
            'day, spent, tokensin, tokensout, cacheread'
        );
        $models = $DB->get_records_sql(
            'SELECT model, COUNT(1) AS calls, SUM(cost) AS cost, SUM(inputtokens) AS tokensin, SUM(outputtokens) AS tokensout
               FROM {local_nit_asst_event}
              WHERE day >= ?
           GROUP BY model
           ORDER BY SUM(cost) DESC',
            [$fromday]
        );
        $start = usergetmidnight($from);
        $convs = $DB->count_records_select('local_nit_asst_conv', 'timecreated >= ? AND messagecount > 0', [$start]);
        $leads = $DB->count_records_select('local_nit_asst_lead', 'timecreated >= ?', [$start]);
        $handoffs = $DB->count_records_select('local_nit_asst_audit', "tool = 'handoff_to_human' AND status = 'ok'
            AND timecreated >= ?", [$start]);
        $spent = array_sum(array_map(static fn($d) => (float) $d->spent, $daily));
        return [
            'days' => array_values($daily),
            'models' => array_values($models),
            'totals' => ['spent' => $spent, 'conversations' => $convs, 'leads' => $leads, 'handoffs' => $handoffs],
        ];
    }
}
