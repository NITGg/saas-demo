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
 * Finds who could use a follow-up, for the situations the academy's own
 * automatic reminders don't cover (they already remind before a subscription or
 * a package expires):
 * - checkout_unpaid: a payment failed, expired or was cancelled and they have not bought it since;
 * - winback: their subscription ended a few days ago and they have no plan now;
 * - chat_interest: the assistant gave them a buying link and they have not bought since;
 * - flex_idle: their package has credits left but they have not booked a lesson for a while.
 *
 * Each candidate is ['userid', 'kind', 'refkey', 'item', 'url', 'convid'].
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class finder {
    /** The kinds, in the order they are looked for. */
    public const KINDS = ['checkout_unpaid', 'winback', 'chat_interest', 'flex_idle'];

    /** Payment statuses that mean "they tried and did not pay". */
    private const UNPAID = ['failed', 'cancelled', 'expired', 'timed_out'];

    /** A refkey repeats at most once per this period (seconds). */
    private const PERIOD = 30 * DAYSECS;

    /**
     * Every candidate of every kind.
     *
     * @param int $now
     * @return array
     */
    public static function all(int $now): array {
        return array_merge(
            self::checkout_unpaid($now),
            self::winback($now),
            self::chat_interest($now),
            self::flex_idle($now)
        );
    }

    /**
     * A payment that did not go through 1–7 days ago, for an item they still don't have.
     *
     * @param int $now
     * @return array
     */
    public static function checkout_unpaid(int $now): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(self::UNPAID, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'local_payments_transactions',
            "status {$in} AND userid > 0 AND timecreated >= :from AND timecreated <= :to",
            $params + ['from' => $now - 7 * DAYSECS, 'to' => $now - DAYSECS],
            'timecreated DESC'
        );
        $out = [];
        foreach ($rows as $t) {
            $meta = json_decode((string) $t->metadata, true) ?: [];
            $type = (string) ($meta['item_type'] ?? ((int) $t->courseid > 0 ? 'course' : ''));
            $itemid = (int) ($meta['item_id'] ?? $t->courseid);
            if (!in_array($type, ['course', 'subscription', 'package'], true) || $itemid <= 0) {
                continue;
            }
            $refkey = "checkout_unpaid:{$t->userid}:{$type}:{$itemid}:" . intdiv((int) $t->timecreated, self::PERIOD);
            if (isset($out[$refkey]) || self::bought_since((int) $t->userid, $type, $itemid, (int) $t->timecreated)) {
                continue;
            }
            $out[$refkey] = [
                'userid' => (int) $t->userid,
                'kind' => 'checkout_unpaid',
                'refkey' => $refkey,
                'item' => self::item_name($type, $itemid),
                'url' => class_exists('\local_payments\local\return_pages')
                    ? \local_payments\local\return_pages::retry($t)->out(false) : self::item_url($type, $itemid),
                'convid' => null,
            ];
        }
        return array_values($out);
    }

    /**
     * A subscription that ended 3–30 days ago, with no plan now.
     *
     * @param int $now
     * @return array
     */
    public static function winback(int $now): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_sub_purchase')) {
            return [];
        }
        $rows = $DB->get_records_select(
            'nit_sub_purchase',
            "status <> 'cancelled' AND expires_at > 0 AND expires_at >= :from AND expires_at <= :to",
            ['from' => $now - 30 * DAYSECS, 'to' => $now - 3 * DAYSECS],
            'expires_at DESC'
        );
        $out = [];
        foreach ($rows as $p) {
            $active = $DB->record_exists_select(
                'nit_sub_purchase',
                "userid = ? AND status = 'active' AND (expires_at = 0 OR expires_at > ?)",
                [$p->userid, $now]
            );
            $refkey = "winback:{$p->userid}:{$p->id}";
            if ($active || isset($out[$refkey])) {
                continue;
            }
            $out[$refkey] = [
                'userid' => (int) $p->userid,
                'kind' => 'winback',
                'refkey' => $refkey,
                'item' => self::item_name('subscription', (int) $p->subscriptionid),
                'url' => self::item_url('subscription', 0),
                'convid' => null,
            ];
        }
        return array_values($out);
    }

    /**
     * A signed-in user the assistant gave a buying link to 2–7 days ago, who has not bought it since.
     *
     * @param int $now
     * @return array
     */
    public static function chat_interest(int $now): array {
        global $DB;
        $rows = $DB->get_records_select(
            'local_nit_asst_audit',
            "tool = 'start_checkout' AND status = 'ok' AND userid > 0 AND timecreated >= :from AND timecreated <= :to",
            ['from' => $now - 7 * DAYSECS, 'to' => $now - 2 * DAYSECS],
            'timecreated DESC'
        );
        $out = [];
        foreach ($rows as $a) {
            $args = json_decode((string) $a->args, true) ?: [];
            $type = (string) ($args['item_type'] ?? '');
            $itemid = (int) ($args['item_id'] ?? 0);
            $refkey = "chat_interest:{$a->convid}";
            if (isset($out[$refkey]) || !in_array($type, ['course', 'subscription', 'package'], true)) {
                continue;
            }
            if (self::bought_since((int) $a->userid, $type, $itemid, (int) $a->timecreated)) {
                continue;
            }
            $out[$refkey] = [
                'userid' => (int) $a->userid,
                'kind' => 'chat_interest',
                'refkey' => $refkey,
                'item' => self::item_name($type, $itemid),
                'url' => self::item_url($type, $itemid),
                'convid' => (int) $a->convid,
            ];
        }
        return array_values($out);
    }

    /**
     * An active package with credits left and no lesson asked for in 14 days.
     *
     * @param int $now
     * @return array
     */
    public static function flex_idle(int $now): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('nit_package_purchase') || !$dbman->table_exists('nit_lesson')) {
            return [];
        }
        $rows = $DB->get_records_select(
            'nit_package_purchase',
            "status = 'active' AND remaining_flex > 0 AND timeactivated <= :since AND (expires_at = 0 OR expires_at > :now)",
            ['since' => $now - 14 * DAYSECS, 'now' => $now]
        );
        $out = [];
        foreach ($rows as $p) {
            if ($DB->record_exists_select('nit_lesson', 'studentid = ? AND timecreated > ?', [$p->userid, $now - 14 * DAYSECS])) {
                continue;
            }
            $refkey = "flex_idle:{$p->id}:" . intdiv($now, self::PERIOD);
            $out[$refkey] = [
                'userid' => (int) $p->userid,
                'kind' => 'flex_idle',
                'refkey' => $refkey,
                'item' => (string) (int) $p->remaining_flex,
                'url' => helper::url('/local/nit_lessons/student.php', ['tab' => 'book']),
                'convid' => null,
            ];
        }
        return array_values($out);
    }

    /**
     * Has the user paid for (or got) the item since a time?
     *
     * @param int $userid
     * @param string $type course | subscription | package
     * @param int $itemid
     * @param int $since
     * @return bool
     */
    public static function bought_since(int $userid, string $type, int $itemid, int $since): bool {
        global $DB;
        if ($type === 'course' && $itemid > 0) {
            $context = \context_course::instance($itemid, IGNORE_MISSING);
            if ($context && is_enrolled($context, $userid, '', true)) {
                return true;
            }
        }
        if (
            $type === 'subscription' && $DB->get_manager()->table_exists('nit_sub_purchase')
                && $DB->record_exists_select('nit_sub_purchase', 'userid = ? AND timecreated >= ?', [$userid, $since])
        ) {
            return true;
        }
        if (
            $type === 'package' && $DB->get_manager()->table_exists('nit_package_purchase')
                && $DB->record_exists_select('nit_package_purchase', 'userid = ? AND timecreated >= ?', [$userid, $since])
        ) {
            return true;
        }
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return false;
        }
        $paid = $DB->get_records_select(
            'local_payments_transactions',
            "userid = ? AND status = 'completed' AND timecreated >= ?",
            [$userid, $since],
            '',
            'id, courseid, metadata'
        );
        foreach ($paid as $t) {
            $meta = json_decode((string) $t->metadata, true) ?: [];
            $ptype = (string) ($meta['item_type'] ?? ((int) $t->courseid > 0 ? 'course' : ''));
            if ($ptype === $type && ($itemid <= 0 || (int) ($meta['item_id'] ?? $t->courseid) === $itemid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The item's name (raw; may hold {mlang} parts).
     *
     * @param string $type
     * @param int $itemid
     * @return string
     */
    private static function item_name(string $type, int $itemid): string {
        global $DB;
        [$table, $field] = match ($type) {
            'course' => ['course', 'fullname'],
            'subscription' => ['nit_subscription', 'name'],
            default => ['nit_package', 'name'],
        };
        if ($itemid <= 0 || !$DB->get_manager()->table_exists($table)) {
            return '';
        }
        return (string) $DB->get_field($table, $field, ['id' => $itemid]);
    }

    /**
     * Where to buy the item.
     *
     * @param string $type
     * @param int $itemid
     * @return string
     */
    private static function item_url(string $type, int $itemid): string {
        return match ($type) {
            'course' => helper::url('/local/academy/course.php', ['id' => $itemid]),
            'subscription' => helper::url('/local/nit_lessons/student.php', ['tab' => 'subavailable']),
            default => helper::url('/local/nit_lessons/student.php', ['tab' => 'flexavailable']),
        };
    }
}
