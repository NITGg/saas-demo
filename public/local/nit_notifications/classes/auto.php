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

namespace local_nit_notifications;

/**
 * Automatic notifications: the platform notifies users by itself when something
 * happens. Each goes through \local_nit_notifications\sender, so it is written in
 * every installed language (each recipient gets theirs), lands in the notification
 * log as "automatic", and follows the recipient's preference row for its kind.
 *
 *  - subscription started / renewed / cancelled, course purchase cancelled
 *    (called by local_nit_subscriptions);
 *  - a new quiz became visible in a course (event observer);
 *  - live session and private lesson reminders before they start (scheduled task);
 *  - subscription and Flex package expiry (local_nit_subscriptions / local_nit_flex
 *    reminders, sent with their own providers).
 *
 * Every kind can be switched off in Site administration → Local plugins →
 * Notifications → Automatic notifications. A failure never breaks the action that
 * triggered it (a payment, a quiz save…).
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto {

    /** Lang component of the messages. */
    private const C = 'local_nit_notifications';

    /** Default minutes before a live session / lesson for the reminders. */
    public const DEFAULT_LEADS = '60,10';

    /**
     * Whether a kind of automatic notification is on (on unless switched off).
     *
     * @param string $kind subscription | newquiz | sessionreminder
     * @return bool
     */
    public static function enabled(string $kind): bool {
        $value = get_config(self::C, 'auto_' . $kind);
        return $value === false || $value === '' || (bool) $value;
    }

    /**
     * Run a notification without ever breaking the caller.
     *
     * @param callable $fn
     */
    private static function safely(callable $fn): void {
        try {
            $fn();
        } catch (\Throwable $e) {
            debugging('local_nit_notifications: automatic notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Claim an item for a one-time notification.
     *
     * @param string $source
     * @param int $itemid
     * @return bool true the first time, false when it was already claimed
     */
    private static function claim(string $source, int $itemid): bool {
        global $DB;
        if ($DB->record_exists('local_nit_notif_once', ['source' => $source, 'itemid' => $itemid])) {
            return false;
        }
        try {
            $DB->insert_record('local_nit_notif_once', (object) ['source' => $source, 'itemid' => $itemid,
                'timecreated' => time()]);
            return true;
        } catch (\dml_write_exception $e) {
            return false; // Claimed by a parallel run.
        }
    }

    /**
     * Send one automatic notification built from this plugin's strings ($key_title, $key_body).
     *
     * @param string $source log source
     * @param string $type sender::TYPES
     * @param int[] $userids
     * @param string $key string key prefix
     * @param mixed $a placeholders (or a function of the language code)
     * @param int $courseid
     * @param \moodle_url|null $url
     * @param string $provider name of this plugin's provider
     */
    private static function send(string $source, string $type, array $userids, string $key, $a, int $courseid,
            ?\moodle_url $url, string $provider): void {
        sender::send_to_users($source, $type, $userids,
            mlang::from_string($key . '_title', self::C, $a),
            mlang::from_string($key . '_body', self::C, $a),
            $courseid, $url ? $url->out(false) : '', self::C . '/' . $provider);
    }

    // =========================================================================
    // Subscriptions.
    // =========================================================================

    /**
     * A subscription was activated: "started", or "renewed" when the user had this plan before.
     *
     * @param \stdClass $purchase nit_sub_purchase record
     */
    public static function subscription_started(\stdClass $purchase): void {
        if (!self::enabled('subscription')) {
            return;
        }
        self::safely(function() use ($purchase) {
            global $DB;
            $plan = (string) $DB->get_field('nit_subscription', 'name', ['id' => $purchase->subscriptionid]);
            $renewed = $DB->record_exists_select('nit_sub_purchase', 'userid = ? AND subscriptionid = ? AND id <> ?',
                [$purchase->userid, $purchase->subscriptionid, $purchase->id]);
            $expires = (int) $purchase->expires_at;
            $key = $renewed ? 'auto_subrenewed' : 'auto_substarted';
            self::send($key === 'auto_subrenewed' ? 'subscription_renewed' : 'subscription_started', 'subscriptions',
                [(int) $purchase->userid], $key,
                fn() => ['plan' => $plan, 'expires' => userdate($expires, get_string('strftimedaydate', 'langconfig'))],
                0, new \moodle_url('/'), 'subscription');
        });
    }

    /**
     * An admin cancelled a user's subscription.
     *
     * @param \stdClass $purchase nit_sub_purchase record
     */
    public static function subscription_cancelled(\stdClass $purchase): void {
        if (!self::enabled('subscription')) {
            return;
        }
        self::safely(function() use ($purchase) {
            global $DB;
            $plan = (string) $DB->get_field('nit_subscription', 'name', ['id' => $purchase->subscriptionid]);
            self::send('subscription_cancelled', 'subscriptions', [(int) $purchase->userid], 'auto_subcancelled',
                ['plan' => $plan], 0, new \moodle_url('/'), 'subscription');
        });
    }

    /**
     * An admin cancelled (or refunded) a user's course purchase.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $refund
     */
    public static function course_purchase_cancelled(int $userid, int $courseid, bool $refund): void {
        if (!self::enabled('subscription')) {
            return;
        }
        self::safely(function() use ($userid, $courseid, $refund) {
            global $DB;
            $course = (string) $DB->get_field('course', 'fullname', ['id' => $courseid]);
            self::send($refund ? 'course_refunded' : 'course_cancelled', 'subscriptions', [$userid],
                $refund ? 'auto_courserefunded' : 'auto_coursecancelled', ['course' => $course], $courseid,
                new \moodle_url('/'), 'subscription');
        });
    }

    /**
     * Send a subscription / package expiry reminder through the log, with the owner
     * plugin's provider and strings (local_nit_subscriptions, local_nit_flex).
     *
     * @param string $source log source
     * @param int $userid
     * @param string $component the strings' component
     * @param string $titlekey
     * @param string $bodykey
     * @param \Closure $a placeholders for a language code
     * @param string $provider "component/name"
     * @param \moodle_url $url
     * @return bool true when it was handed over
     */
    public static function expiry_reminder(string $source, int $userid, string $component, string $titlekey,
            string $bodykey, \Closure $a, string $provider, \moodle_url $url): bool {
        $notif = sender::send_to_users($source, 'subscriptions', [$userid],
            mlang::from_string($titlekey, $component, $a), mlang::from_string($bodykey, $component, $a),
            0, $url->out(false), $provider);
        return $notif !== null && (int) $notif->sent > 0;
    }

    // =========================================================================
    // Offers and coupons: a ready-made notification for the send page.
    // =========================================================================

    /**
     * A filled-in notification about an offer or a coupon (local_nit_commerce), in
     * every language: what it is, the discount, what it applies to, until when.
     *
     * @param string $kind offer | coupon
     * @param int $id
     * @return array|null {type, audience, title, body, url} or null when it does not exist
     */
    public static function commerce_prefill(string $kind, int $id): ?array {
        global $DB;
        $table = $kind === 'coupon' ? 'nit_coupon' : 'nit_offer';
        if (!$DB->get_manager()->table_exists($table) || !($rec = $DB->get_record($table, ['id' => $id]))) {
            return null;
        }
        $items = $DB->get_records($table . '_item', [$kind . 'id' => $id], 'id', 'id, item_type, item_id');
        $a = function() use ($rec, $items, $kind) {
            $labels = [];
            foreach ($items as $it) {
                $labels[] = class_exists('\local_nit_commerce\discount_manager')
                    ? \local_nit_commerce\discount_manager::item_label($it->item_type, $it->item_id) : '';
            }
            $labels = array_filter($labels);
            $value = rtrim(rtrim(number_format((float) $rec->discount_value, 2, '.', ''), '0'), '.');
            return [
                'name' => (string) ($rec->name ?? ''),
                'code' => (string) ($rec->code ?? ''),
                'discount' => $rec->discount_type === 'percent' ? $value . '%'
                    : $value . ' ' . get_string('co_currency', 'local_nit_commerce'),
                'items' => $labels ? implode('، ', $labels) : get_string('notify_allitems', self::C),
                'dates' => (int) $rec->enddate ? get_string('notify_until', self::C,
                    userdate((int) $rec->enddate, get_string('strftimedaydate', 'langconfig'))) : '',
            ];
        };
        return [
            'type' => 'offers',
            'audience' => audience::ALL_STUDENTS,
            'title' => mlang::from_string($kind . '_notify_title', self::C, $a),
            'body' => mlang::from_string($kind . '_notify_body', self::C, $a),
            'url' => (new \moodle_url('/'))->out(false),
        ];
    }

    // =========================================================================
    // New quiz.
    // =========================================================================

    /**
     * A quiz was added or changed: when it is visible to students (and the course
     * too), tell the course's students — once per quiz.
     *
     * @param int $cmid
     */
    public static function quiz_changed(int $cmid): void {
        if (!self::enabled('newquiz')) {
            return;
        }
        self::safely(function() use ($cmid) {
            global $DB;
            $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm || !$cm->visible || !empty($cm->deletioninprogress)) {
                return;
            }
            $course = $DB->get_record('course', ['id' => $cm->course], 'id, fullname, visible');
            if (!$course || !$course->visible || !self::claim('newquiz', $cmid)) {
                return;
            }
            $students = audience::resolve(audience::COURSE_STUDENTS, (int) $course->id, 0);
            if (!$students) {
                return;
            }
            self::send('newquiz', 'courses', $students, 'auto_newquiz',
                ['quiz' => $cm->name, 'course' => $course->fullname], (int) $course->id,
                new \moodle_url('/mod/quiz/view.php', ['id' => $cmid]), 'newquiz');
        });
    }

    // =========================================================================
    // Live session / private lesson reminders.
    // =========================================================================

    /**
     * The reminder lead times, in minutes, largest first.
     *
     * @return int[]
     */
    public static function leads(): array {
        $raw = (string) (get_config(self::C, 'session_reminder_minutes') ?: self::DEFAULT_LEADS);
        $leads = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), fn($m) => $m > 0)));
        rsort($leads);
        return $leads;
    }

    /**
     * Send the reminders that are due: for each live session (local_academysessions)
     * and confirmed private lesson (local_nit_lessons) starting within the largest
     * lead time. A session that starts sooner than several leads gets one reminder.
     *
     * @param int $now
     * @return int how many reminders went out
     */
    public static function send_session_reminders(int $now): int {
        global $DB;
        if (!self::enabled('sessionreminder') || !($leads = self::leads())) {
            return 0;
        }
        $until = $now + $leads[0] * MINSECS;
        $dbman = $DB->get_manager();
        $count = 0;

        if ($dbman->table_exists('academy_live_sessions')) {
            $sessions = $DB->get_records_select('academy_live_sessions',
                "status = 'scheduled' AND start_time > :now AND start_time <= :until",
                ['now' => $now, 'until' => $until]);
            foreach ($sessions as $s) {
                if (!($minutes = self::due('session', (int) $s->id, (int) $s->start_time, $leads, $now))) {
                    continue;
                }
                self::safely(function() use ($s, $minutes, &$count) {
                    global $DB;
                    $users = array_map('intval', array_keys(
                        \local_academysessions\session_manager::get_allowed_students((int) $s->id)));
                    if ((int) $s->teacherid) {
                        $users[] = (int) $s->teacherid;
                    }
                    $course = (string) $DB->get_field('course', 'fullname', ['id' => $s->courseid]);
                    self::send('session_reminder', 'courses', $users, 'auto_session',
                        ['title' => $s->title, 'course' => $course, 'minutes' => $minutes], (int) $s->courseid,
                        new \moodle_url('/course/view.php', ['id' => $s->courseid]), 'sessionreminder');
                    $count++;
                });
            }
        }

        if ($dbman->table_exists('nit_lesson')) {
            $lessons = $DB->get_records_select('nit_lesson',
                "status = 'confirmed' AND ((confirmed_time > :now1 AND confirmed_time <= :until1)
                   OR ((confirmed_time IS NULL OR confirmed_time = 0) AND requested_time > :now2 AND requested_time <= :until2))",
                ['now1' => $now, 'until1' => $until, 'now2' => $now, 'until2' => $until]);
            foreach ($lessons as $l) {
                $start = (int) ($l->confirmed_time ?: $l->requested_time);
                if (!($minutes = self::due('lesson', (int) $l->id, $start, $leads, $now))) {
                    continue;
                }
                self::safely(function() use ($l, $minutes, &$count) {
                    $student = \core_user::get_user((int) $l->studentid);
                    $teacher = \core_user::get_user((int) $l->teacherid);
                    foreach ([[$l->studentid, $teacher, '/local/nit_lessons/student.php', ['tab' => 'lessons']],
                              [$l->teacherid, $student, '/local/nit_lessons/my_lessons.php', []]] as [$to, $with, $path, $p]) {
                        self::send('lesson_reminder', 'courses', [(int) $to], 'auto_lesson',
                            ['subject' => (string) $l->subject, 'with' => $with ? fullname($with) : '', 'minutes' => $minutes],
                            0, new \moodle_url($path, $p), 'sessionreminder');
                    }
                    $count++;
                });
            }
        }
        return $count;
    }

    /**
     * Whether a reminder is due for an item now; claims every lead it covers.
     *
     * @param string $kind session | lesson
     * @param int $itemid
     * @param int $start start time
     * @param int[] $leads minutes, largest first
     * @param int $now
     * @return int minutes left (rounded up) when a reminder is due, else 0
     */
    private static function due(string $kind, int $itemid, int $start, array $leads, int $now): int {
        $due = false;
        foreach ($leads as $lead) {
            if ($start - $now <= $lead * MINSECS && self::claim($kind . '_' . $lead, $itemid)) {
                $due = true;
            }
        }
        return $due ? (int) max(1, ceil(($start - $now) / MINSECS)) : 0;
    }
}
