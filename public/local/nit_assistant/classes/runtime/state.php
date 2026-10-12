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

namespace local_nit_assistant\runtime;

/**
 * Conversation state machine: open → waiting_human → human → closed (and back to
 * open when staff hand it back). Every change happens under the conversation's
 * lock and bumps stateversion, so exactly one of two simultaneous changes wins
 * (e.g. two staff taking over at once) and a running AI turn can tell a person
 * stepped in while it was writing.
 *
 * Also the per-conversation turn claim: one AI reply at a time.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state {
    /** The assistant answers. */
    public const OPEN = 'open';
    /** Handed off; waiting for a person. */
    public const WAITING = 'waiting_human';
    /** A person owns the conversation. */
    public const HUMAN = 'human';
    /** Finished. */
    public const CLOSED = 'closed';

    /** Conversations table. */
    private const TABLE = 'local_nit_asst_conv';

    /**
     * Move a conversation to $to if its status is one of $from. Single winner.
     *
     * @param int $convid
     * @param string[] $from
     * @param string $to
     * @param array $extra more fields to set on success (e.g. assignedto)
     * @return bool true if this call made the change
     */
    public static function transition(int $convid, array $from, string $to, array $extra = []): bool {
        global $DB;
        $lock = self::lock("state_{$convid}", 5);
        if (!$lock) {
            return false;
        }
        try {
            $conv = $DB->get_record(self::TABLE, ['id' => $convid], 'id, status, stateversion');
            if (!$conv || !in_array($conv->status, $from, true)) {
                return false;
            }
            $DB->update_record(self::TABLE, (object) ($extra + [
                'id' => $convid, 'status' => $to, 'stateversion' => (int) $conv->stateversion + 1, 'timemodified' => time(),
            ]));
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Staff take over: open or waiting → human, assigned to $staffid. Single winner.
     *
     * @param int $convid
     * @param int $staffid
     * @return bool
     */
    public static function take_over(int $convid, int $staffid): bool {
        return self::transition($convid, [self::OPEN, self::WAITING], self::HUMAN, ['assignedto' => $staffid]);
    }

    /**
     * Try to start an AI turn: false if one is already running. The claim is a
     * time on the conversation, so a turn that crashed frees itself when it runs out.
     *
     * @param int $convid
     * @param int $seconds how long the claim lasts (the turn's time limit + margin)
     * @param int|null $now
     * @return bool
     */
    public static function begin_turn(int $convid, int $seconds = 60, ?int $now = null): bool {
        global $DB;
        $now ??= time();
        $lock = self::lock("state_{$convid}", 5);
        if (!$lock) {
            return false;
        }
        try {
            $until = $DB->get_field(self::TABLE, 'turnlockuntil', ['id' => $convid]);
            if ($until === false || (int) $until > $now) {
                return false;
            }
            $DB->set_field(self::TABLE, 'turnlockuntil', $now + $seconds, ['id' => $convid]);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * End an AI turn (always call it, even after an error).
     *
     * @param int $convid
     * @return void
     */
    public static function end_turn(int $convid): void {
        global $DB;
        $DB->set_field(self::TABLE, 'turnlockuntil', 0, ['id' => $convid]);
    }

    /**
     * Get a named plugin lock.
     *
     * @param string $resource
     * @param int $timeout seconds to wait
     * @param int $maxlifetime seconds before a crashed holder's lock is considered stale
     * @return \core\lock\lock|false
     */
    private static function lock(string $resource, int $timeout, int $maxlifetime = 60) {
        $factory = \core\lock\lock_config::get_lock_factory('local_nit_assistant');
        return $factory->get_lock($resource, $timeout, $maxlifetime);
    }
}
