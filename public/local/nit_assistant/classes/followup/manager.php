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

namespace local_nit_assistant\followup;

use local_nit_assistant\tools\sales\helper;

/**
 * Follow-up drafts: made daily from finder's candidates, in each user's own
 * language; a staff member reads (and may edit) each one, then sends or
 * dismisses it. Nothing goes out without that approval. Each refkey gets at most
 * one draft ever, and nobody gets more than one follow-up per cooldown.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /** Follow-ups table. */
    private const TABLE = 'local_nit_asst_followup';

    /** Message provider for the user-facing message (db/messages.php). */
    public const PROVIDER = 'followup';

    /** Statuses. */
    public const STATUSES = ['draft', 'sent', 'dismissed', 'failed'];

    /** Longest message body (characters). */
    public const MAXBODY = 1000;

    /**
     * Are follow-ups switched on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config('local_nit_assistant', 'followups');
    }

    /**
     * Days between two follow-ups to the same person.
     *
     * @return int
     */
    public static function cooldown_days(): int {
        return max(1, (int) (get_config('local_nit_assistant', 'followupcooldown') ?: 7));
    }

    /**
     * Make today's drafts.
     *
     * @param int|null $now
     * @param array|null $candidates finder::all() by default
     * @return int drafts made
     */
    public static function create_drafts(?int $now = null, ?array $candidates = null): int {
        global $DB;
        $now ??= time();
        $made = 0;
        foreach ($candidates ?? finder::all($now) as $c) {
            if ($DB->record_exists(self::TABLE, ['refkey' => substr($c['refkey'], 0, 191)])) {
                continue;
            }
            $user = self::may_contact((int) $c['userid'], $now);
            if (!$user) {
                continue;
            }
            [$title, $body] = self::text($c, $user);
            try {
                $DB->insert_record(self::TABLE, (object) [
                    'userid' => $user->id, 'kind' => $c['kind'], 'refkey' => substr($c['refkey'], 0, 191),
                    'title' => \core_text::substr($title, 0, 255), 'body' => $body, 'url' => $c['url'] ?: null,
                    'convid' => $c['convid'] ?? null, 'status' => 'draft', 'timecreated' => $now, 'timemodified' => $now,
                ]);
                $made++;
            } catch (\dml_write_exception $e) {
                unset($e); // Made at the same moment by another run (unique refkey).
            }
        }
        return $made;
    }

    /**
     * The user, if they may get a follow-up now: an active, confirmed account, no
     * draft waiting for them, and nothing sent to them within the cooldown.
     *
     * @param int $userid
     * @param int $now
     * @return \stdClass|null
     */
    public static function may_contact(int $userid, int $now): ?\stdClass {
        global $DB;
        $user = $userid > 0 ? \core_user::get_user($userid) : null;
        if (!$user || $user->deleted || $user->suspended || !$user->confirmed || isguestuser($user)) {
            return null;
        }
        if ($DB->record_exists(self::TABLE, ['userid' => $userid, 'status' => 'draft'])) {
            return null;
        }
        if (
            $DB->record_exists_select(
                self::TABLE,
                "userid = ? AND status = 'sent' AND timesent > ?",
                [$userid, $now - self::cooldown_days() * DAYSECS]
            )
        ) {
            return null;
        }
        return $user;
    }

    /**
     * Send a draft (after staff read it; they may have edited the text). Single winner.
     *
     * @param int $id
     * @param int $staffid
     * @param string|null $title edited title, null keeps the draft's
     * @param string|null $body edited body, null keeps the draft's
     * @return bool true if this call sent it
     */
    public static function approve(int $id, int $staffid, ?string $title = null, ?string $body = null): bool {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('local_nit_assistant')->get_lock("followup_{$id}", 5);
        if (!$lock) {
            return false;
        }
        try {
            $f = $DB->get_record(self::TABLE, ['id' => $id]);
            if (!$f || $f->status !== 'draft') {
                return false;
            }
            if ($title !== null && trim($title) !== '') {
                $f->title = \core_text::substr(trim($title), 0, 255);
            }
            if ($body !== null && trim($body) !== '') {
                $f->body = \core_text::substr(trim($body), 0, self::MAXBODY);
            }
            $sent = self::deliver($f);
            $now = time();
            $DB->update_record(self::TABLE, (object) [
                'id' => $id, 'title' => $f->title, 'body' => $f->body, 'status' => $sent ? 'sent' : 'failed',
                'decidedby' => $staffid, 'timedecided' => $now, 'timesent' => $sent ? $now : 0, 'timemodified' => $now,
            ]);
            return $sent;
        } finally {
            $lock->release();
        }
    }

    /**
     * Dismiss a draft (it is never drafted again).
     *
     * @param int $id
     * @param int $staffid
     * @return bool
     */
    public static function dismiss(int $id, int $staffid): bool {
        global $DB;
        $f = $DB->get_record(self::TABLE, ['id' => $id], 'id, status');
        if (!$f || $f->status !== 'draft') {
            return false;
        }
        $DB->update_record(self::TABLE, (object) ['id' => $id, 'status' => 'dismissed', 'decidedby' => $staffid,
            'timedecided' => time(), 'timemodified' => time()]);
        return true;
    }

    /**
     * Follow-ups, newest first.
     *
     * @param string $status
     * @param int $page
     * @param int $perpage
     * @return array [rows, total]
     */
    public static function list(string $status, int $page, int $perpage): array {
        global $DB;
        $total = $DB->count_records(self::TABLE, ['status' => $status]);
        $rows = $DB->get_records(self::TABLE, ['status' => $status], 'timecreated DESC, id DESC', '*', $page * $perpage, $perpage);
        return [array_values($rows), $total];
    }

    /**
     * Drafts waiting for a decision.
     *
     * @return int
     */
    public static function waiting(): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['status' => 'draft']);
    }

    /**
     * The draft text in the user's own language.
     *
     * @param array $c candidate
     * @param \stdClass $user
     * @return array [title, body]
     */
    private static function text(array $c, \stdClass $user): array {
        $lang = str_starts_with((string) ($user->lang ?: get_config('core', 'lang')), 'ar') ? 'ar' : 'en';
        $a = (object) ['name' => $user->firstname, 'item' => helper::ml((string) $c['item'], $lang)];
        $sm = get_string_manager();
        return [
            $sm->get_string("fu_{$c['kind']}_title", 'local_nit_assistant', $a, $lang),
            $sm->get_string("fu_{$c['kind']}_body", 'local_nit_assistant', $a, $lang),
        ];
    }

    /**
     * Send the message (bell, app push, email — as the user's preferences allow).
     *
     * @param \stdClass $f
     * @return bool
     */
    private static function deliver(\stdClass $f): bool {
        try {
            if (class_exists('\local_nit_notifications\sender')) {
                $n = \local_nit_notifications\sender::send_to_users(
                    'nit_assistant_followup',
                    'general',
                    [(int) $f->userid],
                    $f->title,
                    $f->body,
                    0,
                    (string) $f->url,
                    'local_nit_assistant/' . self::PROVIDER
                );
                return $n !== null;
            }
            $user = \core_user::get_user((int) $f->userid);
            if (!$user) {
                return false;
            }
            $msg = new \core\message\message();
            $msg->component = 'local_nit_assistant';
            $msg->name = self::PROVIDER;
            $msg->userfrom = \core_user::get_noreply_user();
            $msg->userto = $user;
            $msg->subject = $f->title;
            $msg->fullmessage = $f->body . ($f->url ? "\n\n" . $f->url : '');
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml = '';
            $msg->smallmessage = $f->title;
            $msg->notification = 1;
            $msg->contexturl = (string) $f->url;
            $msg->contexturlname = $f->title;
            return (bool) message_send($msg);
        } catch (\Throwable $e) {
            debugging('local_nit_assistant: follow-up delivery failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}
