<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class status_machine {

    const PENDING = 'pending';
    const COMPLETED = 'completed';
    const FAILED = 'failed';
    const CANCELLED = 'cancelled';
    const EXPIRED = 'expired';
    const TIMED_OUT = 'timed_out';
    const REFUNDED = 'refunded';
    const PARTIALLY_REFUNDED = 'partially_refunded';
    const VOIDED = 'voided';
    const CHARGEBACK = 'chargeback';
    const DUPLICATE = 'duplicate';

    private static array $transitions = [
        self::PENDING => [
            self::COMPLETED, self::FAILED, self::CANCELLED,
            self::EXPIRED, self::TIMED_OUT, self::DUPLICATE,
        ],
        self::COMPLETED => [
            self::REFUNDED, self::PARTIALLY_REFUNDED,
            self::VOIDED, self::CHARGEBACK,
        ],
        self::PARTIALLY_REFUNDED => [
            self::REFUNDED,
        ],
    ];

    public static function can_transition(string $from, string $to): bool {
        if ($to === self::DUPLICATE) {
            return true;
        }
        return in_array($to, self::$transitions[$from] ?? []);
    }

    public static function transition(string $from, string $to): string {
        if (!self::can_transition($from, $to)) {
            throw new \coding_exception("Invalid payment status transition: {$from} -> {$to}");
        }
        return $to;
    }

    public static function is_terminal(string $status): bool {
        return in_array($status, [
            self::COMPLETED, self::FAILED, self::CANCELLED,
            self::EXPIRED, self::TIMED_OUT, self::REFUNDED,
            self::VOIDED, self::CHARGEBACK, self::DUPLICATE,
        ]);
    }

    public static function is_successful(string $status): bool {
        return $status === self::COMPLETED;
    }

    public static function all_statuses(): array {
        return [
            self::PENDING, self::COMPLETED, self::FAILED,
            self::CANCELLED, self::EXPIRED, self::TIMED_OUT,
            self::REFUNDED, self::PARTIALLY_REFUNDED, self::VOIDED,
            self::CHARGEBACK, self::DUPLICATE,
        ];
    }
}
