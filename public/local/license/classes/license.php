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

    /** @return string current tier/licence key (defaults to 'demo' when unset). */
    public static function tier(): string {
        $t = trim((string) get_config('local_license', 'tier'));
        return $t !== '' ? $t : 'demo';
    }

    /**
     * The current licence definition. Prefers the JSON pushed by the control plane
     * (local_license/definition) so limits + features are editable without a code
     * change; falls back to the built-in {@see TIERS} default when absent/invalid.
     * Missing keys in a pushed definition are backfilled from the default.
     *
     * @return array
     */
    public static function tierdef(): array {
        $default = self::TIERS[self::tier()] ?? self::TIERS['demo'];
        $raw = trim((string) get_config('local_license', 'definition'));
        if ($raw !== '') {
            $def = json_decode($raw, true);
            if (is_array($def)) {
                return $def + $default; // pushed definition wins; default fills any gaps
            }
        }
        return $default;
    }

    /** @return string human tier name. */
    public static function tiername(): string {
        return (string) (self::tierdef()['name'] ?? self::tier());
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
        $feats = self::tierdef()['features'] ?? [];
        return is_array($feats) && in_array($feature, $feats, true);
    }

    /** @return string allowed video source (all|limited|youtube|vimeo|vdocipher). */
    public static function video_source(): string {
        return self::is_enforced() ? self::tierdef()['videosource'] : 'all';
    }

    /**
     * Whether the mobile app may be used with this academy at all — the
     * design_system `site.supportedapp` flag. Driven by the DYNAMIC licence: the
     * control plane sets `supportedapp:false` in the pushed definition on a
     * package that must not open in the app (e.g. Demo). This is read regardless
     * of enforcement, because "can the app connect" is a packaging question, not a
     * limit that only applies once enforcement is switched on.
     *
     * Fallbacks when the definition doesn't carry the flag (legacy hosts): the
     * built-in demo tier is refused, everything else is allowed.
     *
     * @return bool
     */
    public static function supported_app(): bool {
        $def = self::tierdef();
        if (array_key_exists('supportedapp', $def)) {
            $v = $def['supportedapp'];
            if (is_bool($v)) {
                return $v;
            }
            if (is_int($v)) {
                return $v !== 0;
            }
            return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
        }
        return self::tier() !== 'demo';
    }

    /**
     * Whether a given activity type is allowed under the tier's video source.
     *
     * Moodle can only tell one video type apart at add-time: the DRM VdoCipher
     * activity. External links (YouTube/Vimeo) both use the generic `url` module,
     * so which host is allowed is enforced by the mobile app (it reads
     * `video_source` from getsettings). Here we only gate the DRM host: it needs a
     * tier whose source is `vdocipher` (or the catch-all `all`).
     *
     * @param string $modname
     * @return bool
     */
    public static function video_allowed(string $modname): bool {
        if (!self::is_enforced()) {
            return true;
        }
        if ($modname !== 'vdocipher') {
            return true; // only the DRM host is source-gated at the Moodle level.
        }
        $vs = self::video_source();
        return $vs === 'vdocipher' || $vs === 'all';
    }

    /**
     * The mobile app's feature map for the current tier.
     *
     * This is the "licence output" the white-label mobile app reads from
     * design_system.php (site.features) — booleans only, never editable by the
     * tenant's own admin. It maps our internal tier features (drm|coupons|
     * offers|subscriptions|packages|jitsi) onto the app's fixed feature keys.
     *
     * When enforcement is OFF, has_feature() returns true for everything, so this
     * returns an all-on map — but callers honouring the "absence rule" should emit
     * `features` only when {@see is_enforced()} is true, so an unmanaged host reads
     * as "legacy => everything on" (a missing object, not an all-true one).
     *
     * The key→feature mapping is the one commercial knob — adjust it here when the
     * package definitions are finalised.
     *
     * @return array<string,bool>
     */
    public static function mobile_features(): array {
        $has = static function (string $f): bool {
            return self::has_feature($f);
        };
        return [
            // Core — always available.
            'quizzes'        => true,
            'assignments'    => true,
            'teachers'       => true,
            'calendar'       => true,
            'messaging'      => true,
            'notifications'  => true,
            // Commerce.
            'payments'       => $has('coupons') || $has('subscriptions') || $has('packages'),
            'coupons'        => $has('coupons'),
            'subscriptions'  => $has('subscriptions'),
            // Media.
            'vdocipher'      => $has('drm'),
            'watermark'      => $has('drm'),
            // Vimeo video is on when the licence's video source is Vimeo (mod_vimeo
            // + local_vimeo). Lets the app know this academy plays Vimeo embeds.
            'vimeo'          => self::video_source() === 'vimeo',
            // Extras.
            'jobform'        => $has('packages'),
            // Social login (Apple/Facebook not built yet — off by default).
            'google_login'   => true,
            'apple_login'    => false,
            'facebook_login' => false,
        ];
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

    /**
     * Admin "suspend" switch — independent of the tier / enforcement flag. When on,
     * the academy is locked to the notice page regardless of tier (used by the
     * dashboard's Suspend/Resume). Set by the provisioner (local_license/suspended).
     * @return bool
     */
    public static function is_suspended(): bool {
        return (bool) get_config('local_license', 'suspended');
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
