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

/**
 * Expiry reminders: warn a subscriber before their plan runs out, and open the renew door.
 *
 * One setting drives two visible behaviours, which is the point of keeping them in one class:
 *
 *  * the notification — sent once per (purchase, lead time, deadline) by the hourly task. A
 *    lead time of 0 ({@see EXPIRY_DAY}) is the message on the day the plan actually ends,
 *    sent after it has lapsed rather than before;
 *  * the renew window — {@see renew_due()} opens from the EARLIEST configured lead time
 *    onwards, i.e. as soon as the first reminder is due, so a "renew" affordance and the
 *    warning that prompts it can never disagree.
 *
 * Changing the lead times re-runs the whole calculation immediately (see {@see sync()}) rather
 * than waiting for the next cron: an admin who widens the window expects the people it now
 * covers to hear about it today, and an admin who narrows it expects the window to close
 * for the people it no longer covers.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads and applies the expiry-reminder configuration.
 */
class reminder_manager {

    /** @var string plugin name the settings live under. */
    public const COMPONENT = 'local_nit_subscriptions';

    /** @var string config key: reminders on/off. */
    public const SETTING_ENABLED = 'reminder_enabled';

    /** @var string config key: comma-separated lead times, in days. */
    public const SETTING_DAYS = 'reminder_days';

    /** @var int the largest lead time an admin may set. A year is already absurd; more is a typo. */
    public const MAX_DAYS = 365;

    /** @var int how many lead times may be configured at once. */
    public const MAX_ENTRIES = 10;

    /** @var int the "on the day it ends" reminder — sent once the plan has actually run out. */
    public const EXPIRY_DAY = 0;

    /** @var int[] the schedule reminders ship with: a month, a week, a day, and the day itself. */
    public const DEFAULT_DAYS = [30, 7, 1, self::EXPIRY_DAY];

    /**
     * The current configuration.
     *
     * @return array{enabled: bool, days: int[]} days are sorted largest first
     */
    public static function get_settings(): array {
        $enabled = get_config(self::COMPONENT, self::SETTING_ENABLED);
        $days = get_config(self::COMPONENT, self::SETTING_DAYS);

        // Never configured: reminders are ON, with the standard schedule. A subscriber who
        // loses their courses without ever being warned is the exact failure this feature
        // exists to prevent, so silence must be something an admin chose (by unticking the
        // box) rather than something nobody noticed.
        if ($days === false) {
            return ['enabled' => true, 'days' => self::DEFAULT_DAYS];
        }

        return [
            'enabled' => ($enabled !== false && (int) $enabled === 1),
            'days'    => self::clean_days(explode(',', (string) $days)),
        ];
    }

    /**
     * Save the configuration and immediately bring every subscriber into line with it.
     *
     * @param bool $enabled
     * @param array $days lead times in days; duplicates, blanks and out-of-range values are dropped
     * @return array{enabled: bool, days: int[], sent: int, cleared: int} what was saved and what it did
     */
    public static function save_settings(bool $enabled, array $days): array {
        $clean = self::clean_days($days);

        if ($enabled && !$clean) {
            throw new \moodle_exception('err_reminderdaysrequired', self::COMPONENT);
        }

        set_config(self::SETTING_ENABLED, $enabled ? 1 : 0, self::COMPONENT);
        set_config(self::SETTING_DAYS, implode(',', $clean), self::COMPONENT);

        $result = self::sync();

        return [
            'enabled' => $enabled,
            'days'    => $clean,
            'sent'    => $result['sent'],
            'cleared' => $result['cleared'],
        ];
    }

    /**
     * Re-apply the current settings to every live subscription.
     *
     * Two halves, and both matter:
     *
     *  * receipts for lead times that are no longer configured are deleted. Without this a lead
     *    time that is removed and later restored would stay silent forever, and — because the
     *    renew window is driven by the same lead times — someone dropped out of the window
     *    would keep a receipt saying they had already been told;
     *  * everyone who is inside the window now and has no receipt for it is notified, so
     *    widening the window takes effect the moment it is saved.
     *
     * @param int $now unix time to work from (defaults to now; injectable for tests)
     * @return array{sent: int, cleared: int, considered: int}
     */
    public static function sync(int $now = 0): array {
        global $DB;

        $settings = self::get_settings();
        $cleared = 0;

        if (!$settings['days']) {
            $cleared = $DB->count_records('nit_sub_reminder');
            $DB->delete_records('nit_sub_reminder');
        } else {
            [$insql, $params] = $DB->get_in_or_equal($settings['days'], SQL_PARAMS_NAMED, 'd', false);
            $cleared = $DB->count_records_select('nit_sub_reminder', "days $insql", $params);
            $DB->delete_records_select('nit_sub_reminder', "days $insql", $params);
        }

        $run = self::run($now);

        return ['sent' => $run['sent'], 'cleared' => $cleared, 'considered' => $run['considered']];
    }

    /**
     * Send every reminder that is due and not yet sent.
     *
     * Safe to call as often as you like: the receipt table makes each (purchase, lead time,
     * deadline) fire exactly once, so the hourly task and a settings save cannot double up.
     *
     * @param int $now unix time to work from (defaults to now; injectable for tests)
     * @return array{sent: int, considered: int, failed: int}
     */
    public static function run(int $now = 0): array {
        global $DB;

        $now = $now > 0 ? $now : time();
        $settings = self::get_settings();

        if (!$settings['enabled'] || !$settings['days']) {
            return ['sent' => 0, 'considered' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;
        $purchases = self::live_purchases($now, max($settings['days']), self::grace_for($settings['days']));

        foreach ($purchases as $purchase) {
            $daysleft = self::days_left($purchase, $now);

            foreach (self::leads_due($purchase, $settings['days'], $now) as $lead) {
                $already = $DB->record_exists('nit_sub_reminder', [
                    'purchaseid' => (int) $purchase->id,
                    'days'       => $lead,
                    'expires_at' => (int) $purchase->expires_at,
                ]);
                if ($already) {
                    continue;
                }

                if (self::notify($purchase, $daysleft)) {
                    $sent++;
                } else {
                    $failed++;
                }

                // The receipt is written whether or not the message got through: a mail server
                // that is down must not turn into the same warning every hour for a week.
                $DB->insert_record('nit_sub_reminder', (object) [
                    'purchaseid'     => (int) $purchase->id,
                    'userid'         => (int) $purchase->userid,
                    'subscriptionid' => (int) $purchase->subscriptionid,
                    'days'           => $lead,
                    'expires_at'     => (int) $purchase->expires_at,
                    'timecreated'    => $now,
                ]);
            }
        }

        return ['sent' => $sent, 'considered' => count($purchases), 'failed' => $failed];
    }

    /**
     * How many people the current settings would warn right now, without warning them.
     *
     * Drives the "this will notify N subscribers" line on the admin tab, so an admin can see
     * the size of what they are about to do before they save it.
     *
     * @param array $days candidate lead times (unsaved values from the form)
     * @param int $now
     * @return array{due: int, active: int} due = would be notified now, active = live subscriptions
     */
    public static function preview(array $days, int $now = 0): array {
        global $DB;

        $now = $now > 0 ? $now : time();
        $clean = self::clean_days($days);
        $active = count(self::live_purchases($now, 0));

        if (!$clean) {
            return ['due' => 0, 'active' => $active];
        }

        $due = 0;
        foreach (self::live_purchases($now, max($clean), self::grace_for($clean)) as $purchase) {
            foreach (self::leads_due($purchase, $clean, $now) as $lead) {
                $already = $DB->record_exists('nit_sub_reminder', [
                    'purchaseid' => (int) $purchase->id,
                    'days'       => $lead,
                    'expires_at' => (int) $purchase->expires_at,
                ]);
                if (!$already) {
                    $due++;
                    break;   // Count people, not messages.
                }
            }
        }

        return ['due' => $due, 'active' => $active];
    }

    /**
     * Is this purchase inside the renew window — i.e. has its first reminder come due?
     *
     * The renew window and the reminders share one setting on purpose: a warning that does not
     * come with a way to act on it is just an interruption.
     *
     * @param \stdClass $purchase nit_sub_purchase record
     * @param int $now
     * @return bool
     */
    public static function renew_due($purchase, int $now = 0): bool {
        $settings = self::get_settings();
        if (!$settings['enabled'] || !$settings['days']) {
            return false;
        }
        if (empty($purchase) || (int) $purchase->expires_at <= 0) {
            return false;   // An open-ended plan never runs out, so it never needs renewing.
        }

        return self::days_left($purchase, $now) <= max($settings['days']);
    }

    /**
     * Whole days from now until the purchase expires (0 once the deadline has passed).
     *
     * Rounded UP, so a plan with 30 hours left is "2 days", the same figure the message
     * shows. Rounding down would tell somebody with 30 hours that they have one day.
     *
     * @param \stdClass $purchase
     * @param int $now
     * @return int
     */
    public static function days_left($purchase, int $now = 0): int {
        $now = $now > 0 ? $now : time();
        $expires = (int) $purchase->expires_at;
        if ($expires <= 0) {
            return PHP_INT_MAX;
        }
        return max(0, (int) ceil(($expires - $now) / DAYSECS));
    }

    // ── Helpers ──

    /**
     * Dated purchases whose deadline falls inside the window we care about.
     *
     * $grace is what makes the expiry-day reminder possible: a plan that ended a few hours ago
     * may already have been closed out — its row saying `expired`, not `active` — so looking
     * only at live purchases would mean the "it ended today" message could never be sent.
     *
     * @param int $now
     * @param int $within days ahead to look; 0 means no upper bound unless $grace is set,
     *                    in which case the window ends at $now (i.e. only what has run out)
     * @param int $grace seconds to look BACK for purchases that have just run out; 0 = none
     * @return \stdClass[]
     */
    private static function live_purchases(int $now, int $within, int $grace = 0): array {
        global $DB;

        $statuses = [subscription_purchase_manager::STATUS_ACTIVE];
        if ($grace > 0) {
            $statuses[] = subscription_purchase_manager::STATUS_EXPIRED;
        }
        [$insql, $params] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');

        $select = "status $insql AND expires_at > :floor";
        $params['floor'] = $now - $grace;

        if ($within > 0 || $grace > 0) {
            $select .= ' AND expires_at <= :until';
            $params['until'] = $now + ($within * DAYSECS);
        }

        $rows = array_values($DB->get_records_select('nit_sub_purchase', $select, $params, 'expires_at ASC'));

        if ($grace <= 0) {
            return $rows;
        }

        // Somebody who renewed BEFORE their old period ran out holds both rows: the lapsed one
        // and the live one that took over. Their access never stopped, so telling them their
        // subscription has ended would be flatly untrue — and would arrive days after they
        // paid to prevent exactly that.
        return array_values(array_filter($rows, function ($row) use ($now) {
            if (((int) $row->expires_at) > $now) {
                return true;
            }
            return !subscription_purchase_manager::get_active_subscription((int) $row->userid);
        }));
    }

    /**
     * Which of the configured lead times this purchase has reached, receipts aside.
     *
     * A larger lead time that has already passed still counts — someone whose plan has four
     * days left and who has never been told is owed the 7-day warning too, once. The one
     * exception is a plan that has ALREADY run out: it is owed the "it ended today" message
     * and nothing else, because firing the 30-, 7- and 1-day warnings retrospectively, all in
     * the same minute, would be nonsense.
     *
     * @param \stdClass $purchase
     * @param int[] $days the configured lead times
     * @param int $now
     * @return int[] the lead times owed, in configured order
     */
    private static function leads_due($purchase, array $days, int $now): array {
        $expired = ((int) $purchase->expires_at) <= $now;
        $daysleft = self::days_left($purchase, $now);

        $out = [];
        foreach ($days as $lead) {
            if ($expired ? ($lead !== self::EXPIRY_DAY) : ($daysleft > $lead)) {
                continue;
            }
            $out[] = $lead;
        }

        return $out;
    }

    /**
     * How far back to keep looking for purchases that have just run out.
     *
     * A day: long enough that an hourly task (or a cron that missed a few runs) still catches
     * the plans that ended, short enough that reviving a long-dead subscription cannot set off
     * an "it ended today" message weeks late.
     *
     * @param int[] $days the configured lead times
     * @return int seconds; 0 when the expiry-day reminder is not configured
     */
    private static function grace_for(array $days): int {
        return in_array(self::EXPIRY_DAY, $days, true) ? DAYSECS : 0;
    }

    /**
     * Turn whatever the form sent into a clean, ordered set of lead times.
     *
     * @param array $days
     * @return int[] unique, 0..MAX_DAYS, largest first (so the expiry-day entry sorts last),
     *               capped at MAX_ENTRIES
     */
    public static function clean_days(array $days): array {
        $out = [];
        foreach ($days as $day) {
            $day = trim((string) $day);

            // Digits only, and checked BEFORE the cast. Now that 0 is a meaningful lead time
            // ("on the day it ends"), a plain (int) cast would turn an empty box — or the ''
            // that explode() hands back for an empty list — into a reminder the admin never
            // asked for, which is the one mistake this setting must not make quietly.
            if ($day === '' || !ctype_digit($day)) {
                continue;
            }

            $day = (int) $day;
            if ($day >= self::EXPIRY_DAY && $day <= self::MAX_DAYS) {
                $out[$day] = $day;
            }
        }
        $out = array_values($out);
        rsort($out, SORT_NUMERIC);

        return array_slice($out, 0, self::MAX_ENTRIES);
    }

    /**
     * Send one subscriber their "your plan is about to end" notification.
     *
     * Delivered through Moodle's own message system, so it reaches the notification bell and
     * the recipient's email according to their own preferences, and is written in their
     * language rather than the admin's.
     *
     * @param \stdClass $purchase
     * @param int $daysleft
     * @return bool true when the message was handed to the message system
     */
    private static function notify($purchase, int $daysleft): bool {
        global $DB, $CFG;

        $user = $DB->get_record('user', ['id' => (int) $purchase->userid]);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }

        $plan = $DB->get_record('nit_subscription', ['id' => (int) $purchase->subscriptionid]);
        $planname = $plan ? format_string(subscription_manager::resolve_mlang($plan->name)) : '';

        // The recipient's language, not the sender's.
        $old = force_current_language($user->lang ?: $CFG->lang);

        try {
            $a = (object) [
                'plan'    => $planname,
                'days'    => $daysleft,
                'expires' => userdate((int) $purchase->expires_at, get_string('strftimedaydate')),
            ];

            // An academy sells its plans from the subscriptions block on its own front page —
            // there is no per-plan page to link to here — so that is where "renew" leads.
            $renewurl = new \moodle_url('/');

            // The day it actually ends reads differently from a warning about it: past tense,
            // no countdown, and renewal is the only thing left to offer. "0 day(s) remaining"
            // would be both wrong and slightly insulting.
            $key = ($daysleft <= 0) ? '_today' : '';

            $body = get_string('reminder_msg_body' . $key, self::COMPONENT, $a);

            $message = new \core\message\message();
            $message->component         = self::COMPONENT;
            $message->name              = 'subscriptionreminder';
            $message->userfrom          = \core_user::get_noreply_user();
            $message->userto            = $user;
            $message->subject           = get_string('reminder_msg_subject' . $key, self::COMPONENT, $a);
            $message->fullmessage       = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = text_to_html($body);
            $message->smallmessage      = get_string('reminder_msg_small' . $key, self::COMPONENT, $a);
            $message->notification      = 1;
            $message->contexturl        = $renewurl->out(false);
            $message->contexturlname    = get_string('reminder_msg_action', self::COMPONENT);

            return message_send($message) !== false;
        } catch (\Throwable $e) {
            debugging('local_nit_subscriptions: expiry reminder failed for purchase '
                . (int) $purchase->id . ': ' . $e->getMessage(), DEBUG_NORMAL);
            return false;
        } finally {
            force_current_language($old);
        }
    }
}
