<?php
namespace local_license;

defined('MOODLE_INTERNAL') || die();

/**
 * The academy's licence: which tier it is on, what that unlocks, and when it
 * expires. This is the single source of truth other code asks.
 *
 * SAFETY: enforcement is OFF by default (the "enabled" setting). Until an admin
 * turns it on and picks a tier, nothing is limited — so installing this plugin
 * on an existing academy changes nothing until you opt in.
 *
 * Tier numbers live in {@see TIERS} — one place to edit when the exact limits
 * are finalised (decision D7). -1 means "unlimited".
 */
class license {

    /** @var array Tier catalogue. Edit here to change limits. */
    const TIERS = [
        'demo' => [
            'name'         => 'Demo',
            'durationdays' => 14,
            'maxcourses'   => 1,
            'maxteachers'  => 1,
            'videosource'  => 'limited',
            'features'     => [],
            // Per-bucket activity caps (see BUCKETS). 'default' covers any other type.
            'limits'       => ['quiz' => 2, 'video' => 4, 'pdf' => 1, 'default' => 1],
        ],
        'basic' => [
            'name'         => 'Basic',
            'durationdays' => 365,
            'maxcourses'   => 3,
            'maxteachers'  => 1,
            'videosource'  => 'youtube',
            'features'     => [],
            'limits'       => ['quiz' => -1, 'video' => -1, 'pdf' => -1, 'default' => -1],
        ],
        'standard' => [
            'name'         => 'Standard',
            'durationdays' => 365,
            'maxcourses'   => 10,
            'maxteachers'  => -1,
            'videosource'  => 'vimeo',
            'features'     => [],
            'limits'       => ['quiz' => -1, 'video' => -1, 'pdf' => 10, 'default' => -1],
        ],
        'professional' => [
            'name'         => 'Professional',
            'durationdays' => 365,
            'maxcourses'   => -1,
            'maxteachers'  => -1,
            'videosource'  => 'vdocipher',
            'features'     => ['drm', 'coupons', 'offers', 'subscriptions', 'packages', 'jitsi'],
            'limits'       => ['quiz' => -1, 'video' => -1, 'pdf' => -1, 'default' => -1],
        ],
    ];

    /**
     * Which "bucket" a course-module type counts against. Anything not listed
     * falls under 'default'. Adjust as the activity set firms up (D7).
     * @var array modname => bucket
     */
    const BUCKETS = [
        'quiz'      => 'quiz',
        'vdocipher' => 'video',
        'url'       => 'video',   // YouTube/Vimeo links
        'resource'  => 'pdf',     // uploaded files (mostly PDFs)
        'testnew'   => 'pdf',
    ];

    /** @return bool whether limit enforcement is switched on. */
    public static function is_enforced(): bool {
        return (bool) get_config('local_license', 'enabled');
    }

    /** @return string current tier key (defaults to 'demo' when unset). */
    public static function tier(): string {
        $t = (string) get_config('local_license', 'tier');
        return isset(self::TIERS[$t]) ? $t : 'demo';
    }

    /** @return array the current tier definition. */
    public static function tierdef(): array {
        return self::TIERS[self::tier()];
    }

    /** @return string human tier name. */
    public static function tiername(): string {
        return self::tierdef()['name'];
    }

    /**
     * Does the tier include a feature? Always true when enforcement is off, so
     * the full-featured academy keeps working until you opt in.
     *
     * @param string $feature drm|coupons|offers|subscriptions|packages|jitsi
     * @return bool
     */
    public static function has_feature(string $feature): bool {
        if (!self::is_enforced()) {
            return true;
        }
        return in_array($feature, self::tierdef()['features'], true);
    }

    /** @return string allowed video source (all|limited|youtube|vimeo|vdocipher). */
    public static function video_source(): string {
        return self::is_enforced() ? self::tierdef()['videosource'] : 'all';
    }

    /**
     * Expiry as a timestamp (0 = none set / never).
     *
     * Stored as a 'YYYY-MM-DD' string ('expirydate') so admins can type it; a
     * bare number is also accepted (a raw timestamp, e.g. written by the CLI /
     * provisioner). End-of-day is used so the last day is fully included.
     *
     * @return int
     */
    public static function expiry(): int {
        $raw = trim((string) get_config('local_license', 'expirydate'));
        if ($raw === '') {
            return 0;
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        $ts = strtotime($raw . ' 23:59:59');
        return $ts ?: 0;
    }

    /** @return int grace period in days after expiry before locking. */
    public static function grace_days(): int {
        return (int) get_config('local_license', 'gracedays');
    }

    /** @return bool whether the academy is past expiry (+ grace) and should be locked. */
    public static function is_expired(): bool {
        if (!self::is_enforced()) {
            return false;
        }
        $expiry = self::expiry();
        if ($expiry <= 0) {
            return false; // no expiry set = never locks.
        }
        return time() > ($expiry + self::grace_days() * DAYSECS);
    }

    /** @return int whole days left before expiry (negative once past). */
    public static function days_left(): int {
        $expiry = self::expiry();
        if ($expiry <= 0) {
            return PHP_INT_MAX;
        }
        return (int) floor(($expiry - time()) / DAYSECS);
    }

    /**
     * The cap for a bucket under the current tier (-1 = unlimited).
     *
     * @param string $bucket
     * @return int
     */
    public static function bucket_limit(string $bucket): int {
        if (!self::is_enforced()) {
            return -1;
        }
        $limits = self::tierdef()['limits'];
        return $limits[$bucket] ?? ($limits['default'] ?? -1);
    }

    /**
     * Map a module name to its bucket.
     *
     * @param string $modname
     * @return string
     */
    public static function bucket_for(string $modname): string {
        return self::BUCKETS[$modname] ?? 'default';
    }

    /**
     * Some activity types are only available on tiers that include a feature —
     * e.g. the DRM video activity requires the 'drm' feature (Professional).
     * @var array modname => required feature
     */
    const FEATURE_MODULES = [
        'vdocipher' => 'drm',
    ];

    /**
     * The feature a module type requires, or null if it needs none.
     *
     * @param string $modname
     * @return string|null
     */
    public static function module_requires_feature(string $modname): ?string {
        return self::FEATURE_MODULES[$modname] ?? null;
    }

    /** @return int max courses for the tier (-1 unlimited; PHP_INT_MAX when off). */
    public static function max_courses(): int {
        return self::is_enforced() ? (int) self::tierdef()['maxcourses'] : -1;
    }

    /** @return int max teachers for the tier (-1 unlimited; PHP_INT_MAX when off). */
    public static function max_teachers(): int {
        return self::is_enforced() ? (int) self::tierdef()['maxteachers'] : -1;
    }
}
