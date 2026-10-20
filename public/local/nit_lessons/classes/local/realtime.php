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

namespace local_nit_lessons\local;

/**
 * Instant-lesson signals over the academy notify-ws relay (Socket.IO; see academy repo
 * docs/realtime-notifications.md). Moodle POSTs /emit to the relay, which pushes a "notification"
 * event into the user's private room; the page then fetches the new state at once instead of
 * waiting for its next poll. Signals carry no data beyond {type: instant, what, requestid}.
 *
 * Off until the relay is configured in config.php (same settings as the academy relay):
 *   $CFG->academy_notify_publicurl   base URL browsers connect to (required to switch this on)
 *   $CFG->academy_notify_path        Socket.IO path, e.g. /notify-ws/socket.io
 *   $CFG->academy_notify_internalurl URL Moodle POSTs to, e.g. http://notify-ws:3100
 *   $CFG->academy_notify_secret      HMAC secret = the relay's NOTIFY_SECRET / INTERNAL_KEY
 *   $CFG->academy_notify_enabled     false to switch it off again
 *
 * Signals are queued and sent after the work that caused them is committed (flush()), so a page
 * never fetches the old state. Polling stays on as a slower safety net.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class realtime {
    /** @var array[] queued [userid, payload] */
    private static $queue = [];

    /** @var array[]|null when an array, flushed signals are collected here instead of sent (tests) */
    public static $sink = null;

    /**
     * Is the relay configured and switched on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        global $CFG;
        return !empty($CFG->academy_notify_publicurl) && !empty($CFG->academy_notify_secret)
            && (!isset($CFG->academy_notify_enabled) || !empty($CFG->academy_notify_enabled));
    }

    /**
     * What the page needs to connect (null when off).
     *
     * @param int $userid
     * @return array|null {url, path, token}
     */
    public static function client_config(int $userid): ?array {
        global $CFG;
        if (!self::enabled() || $userid <= 0) {
            return null;
        }
        return [
            'url' => rtrim($CFG->academy_notify_publicurl, '/'),
            'path' => !empty($CFG->academy_notify_path) ? $CFG->academy_notify_path : '/socket.io',
            'token' => self::mint_token($userid),
        ];
    }

    /**
     * A token the relay can verify on its own: "<userid>.<exp>.<sha256 hmac>", valid 6 hours.
     *
     * @param int $userid
     * @param int|null $now
     * @return string
     */
    public static function mint_token(int $userid, ?int $now = null): string {
        global $CFG;
        $payload = $userid . '.' . (($now ?? time()) + 6 * HOURSECS);
        return $payload . '.' . hash_hmac('sha256', $payload, (string) ($CFG->academy_notify_secret ?? ''));
    }

    /**
     * Queue a signal for users (sent on flush()).
     *
     * @param int[] $userids
     * @param string $what offer | closed | status
     * @param int $requestid
     * @return void
     */
    public static function signal(array $userids, string $what, int $requestid): void {
        if (!self::enabled() && self::$sink === null) {
            return;
        }
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if ($userid > 0) {
                self::$queue[] = [$userid, ['type' => 'instant', 'what' => $what, 'requestid' => $requestid]];
            }
        }
    }

    /**
     * Drop queued signals (the work that queued them was rolled back).
     *
     * @return void
     */
    public static function discard(): void {
        self::$queue = [];
    }

    /**
     * Send the queued signals. Never throws, never slows a request for long (1 s / 2 s timeouts).
     *
     * @return void
     */
    public static function flush(): void {
        global $CFG;
        $queue = self::$queue;
        self::$queue = [];
        if (self::$sink !== null) {
            foreach ($queue as $item) {
                self::$sink[] = $item;
            }
            return;
        }
        if (!$queue || !self::enabled()) {
            return;
        }
        $url = rtrim(!empty($CFG->academy_notify_internalurl) ? $CFG->academy_notify_internalurl : 'http://notify-ws:3100',
            '/') . '/emit';
        foreach ($queue as [$userid, $payload]) {
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['userid' => $userid, 'notification' => $payload]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json',
                        'x-internal-key: ' . $CFG->academy_notify_secret],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_TIMEOUT => 2,
                ]);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Throwable $e) {
                debugging('local_nit_lessons: realtime signal not sent: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
