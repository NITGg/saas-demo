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

namespace local_nit_assistant\chat;

use local_nit_assistant\runtime\state;

/**
 * Finds or starts the caller's conversation. A signed-in user has one open
 * conversation per channel. A visitor is known only by a random key their
 * browser keeps; the server stores its hash. When a visitor signs in, their
 * conversation follows them; after signing out, the browser starts a new one,
 * so a shared computer never shows a signed-in user's chat.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conversation {
    /** Conversations table. */
    private const TABLE = 'local_nit_asst_conv';

    /** A visitor's browser key: 32–64 letters and digits. */
    public const KEY_PATTERN = '/^[A-Za-z0-9]{32,64}$/';

    /** An open conversation idle this long starts over (seconds). */
    public const IDLE = 7 * DAYSECS;

    /**
     * The caller's current conversation, or null.
     *
     * @param int $userid 0 for a visitor
     * @param string|null $visitorkey the browser key (visitors)
     * @param string $channel
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function find(int $userid, ?string $visitorkey, string $channel = 'web', ?int $now = null): ?\stdClass {
        global $DB;
        $now ??= time();
        if ($userid > 0) {
            $where = 'userid = :userid AND channel = :channel';
            $params = ['userid' => $userid, 'channel' => $channel];
        } else {
            if (!self::valid_key($visitorkey)) {
                return null;
            }
            $where = 'userid = 0 AND sessionkey = :skey AND channel = :channel';
            $params = ['skey' => self::hash($visitorkey), 'channel' => $channel];
        }
        $rows = $DB->get_records_select(
            self::TABLE,
            "{$where} AND status <> :closed",
            $params + ['closed' => state::CLOSED],
            'id DESC',
            '*',
            0,
            1
        );
        $conv = $rows ? reset($rows) : null;
        if ($conv && self::idle($conv, $now)) {
            return null;
        }
        return $conv;
    }

    /**
     * The caller's conversation, started if needed. A visitor's chat moves to the
     * user once they sign in.
     *
     * @param int $userid
     * @param string|null $visitorkey
     * @param array $meta channel, lang, mode, ip, page
     * @param int|null $now
     * @return \stdClass
     */
    public static function find_or_create(int $userid, ?string $visitorkey, array $meta, ?int $now = null): \stdClass {
        global $DB;
        $now ??= time();
        $channel = $meta['channel'] ?? 'web';
        $conv = self::find($userid, $visitorkey, $channel, $now);
        if (!$conv && $userid > 0 && self::valid_key($visitorkey)) {
            $conv = self::find(0, $visitorkey, $channel, $now);
            if ($conv) {
                $DB->update_record(self::TABLE, (object) ['id' => $conv->id, 'userid' => $userid, 'sessionkey' => null]);
                $conv->userid = $userid;
                $conv->sessionkey = null;
            }
        }
        if ($conv) {
            return $conv;
        }
        $record = (object) [
            'userid' => $userid,
            'sessionkey' => $userid > 0 || !self::valid_key($visitorkey) ? null : self::hash($visitorkey),
            'channel' => $channel,
            'mode' => $meta['mode'] ?? 'sales',
            'status' => state::OPEN,
            'lang' => self::lang($meta['lang'] ?? null),
            'iphash' => !empty($meta['ip']) ? self::hash($meta['ip']) : null,
            'sourcepage' => isset($meta['page']) ? \core_text::substr((string) $meta['page'], 0, 255) : null,
            'lastmessageat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record(self::TABLE, $record);
        return $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Close the caller's conversation so the next message starts a new one.
     *
     * @param int $userid
     * @param string|null $visitorkey
     * @param string $channel
     * @return bool
     */
    public static function close(int $userid, ?string $visitorkey, string $channel = 'web'): bool {
        $conv = self::find($userid, $visitorkey, $channel);
        return $conv ? state::transition((int) $conv->id, [state::OPEN, state::WAITING, state::HUMAN], state::CLOSED) : false;
    }

    /**
     * Store a message and move the conversation's counters.
     *
     * @param \stdClass $conv
     * @param string $role visitor | assistant | staff | system
     * @param string $text
     * @param array $extra more fields (staffid…)
     * @param int|null $now
     * @return int message id
     */
    public static function add_message(\stdClass $conv, string $role, string $text, array $extra = [], ?int $now = null): int {
        global $DB;
        $now ??= time();
        $id = $DB->insert_record('local_nit_asst_msg', (object) ($extra + [
            'convid' => $conv->id, 'role' => $role, 'content' => $text, 'status' => 'sent', 'timecreated' => $now,
        ]));
        $DB->execute(
            'UPDATE {' . self::TABLE . '} SET messagecount = messagecount + 1, lastmessageat = ?, timemodified = ? WHERE id = ?',
            [$now, $now, $conv->id]
        );
        return (int) $id;
    }

    /**
     * Messages the user may see (their own, the assistant's, the team's), after a message id.
     *
     * @param int $convid
     * @param int $afterid
     * @return array list of ['id', 'role', 'text', 'time']
     */
    public static function messages(int $convid, int $afterid = 0): array {
        global $DB;
        $rows = $DB->get_records_select(
            'local_nit_asst_msg',
            "convid = :convid AND id > :after AND status = 'sent' AND role IN ('visitor', 'assistant', 'staff')",
            ['convid' => $convid, 'after' => $afterid],
            'id ASC',
            'id, role, content, timecreated'
        );
        return array_values(array_map(static fn($r): array => [
            'id' => (int) $r->id, 'role' => $r->role, 'text' => $r->content, 'time' => (int) $r->timecreated,
        ], $rows));
    }

    /**
     * Is this a well-formed browser key?
     *
     * @param string|null $key
     * @return bool
     */
    public static function valid_key(?string $key): bool {
        return $key !== null && preg_match(self::KEY_PATTERN, $key) === 1;
    }

    /**
     * Site-salted hash (visitor keys and IPs are never stored as they are).
     *
     * @param string $value
     * @return string
     */
    public static function hash(string $value): string {
        return hash('sha256', get_site_identifier() . '|' . $value);
    }

    /**
     * The conversation language from a Moodle language code.
     *
     * @param string|null $lang
     * @return string ar | en
     */
    public static function lang(?string $lang): string {
        return str_starts_with((string) ($lang ?? current_language()), 'ar') ? 'ar' : 'en';
    }

    /**
     * Has an open conversation gone quiet for too long?
     *
     * @param \stdClass $conv
     * @param int $now
     * @return bool
     */
    private static function idle(\stdClass $conv, int $now): bool {
        return $conv->status === state::OPEN && (int) $conv->lastmessageat > 0 && $now - (int) $conv->lastmessageat > self::IDLE;
    }
}
