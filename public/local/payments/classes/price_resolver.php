<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class price_resolver {

    /**
     * Whether a course has any active pricing rule configured at all.
     * Used to decide whether to apply the payment gate — a course with
     * no active pricing is treated as free/open.
     */
    public static function has_pricing(int $courseid): bool {
        global $DB;
        return $DB->record_exists('local_payments_course_prices', [
            'courseid' => $courseid,
            'is_active' => 1,
        ]);
    }

    /**
     * Resolve the price for a course based on user's detected country.
     *
     * @param int $courseid
     * @param int|null $userid
     * @param string|null $app_country Country from Flutter app.
     * @return object {price_id, price, sale_price, original_price, currency, country, discount_pct, is_sale_active}
     * @throws \moodle_exception if no pricing found.
     */
    public static function resolve(int $courseid, ?int $userid = null, ?string $app_country = null): object {
        global $DB, $USER;

        $userid = $userid ?? $USER->id;
        $country = country_detector::detect($userid, $app_country);

        // Country-specific active price wins; otherwise the course's default active price.
        $price_record = $DB->get_record_select(
            'local_payments_course_prices',
            'courseid = :courseid AND country = :country AND is_active = 1',
            ['courseid' => $courseid, 'country' => $country],
            '*',
            IGNORE_MULTIPLE
        );

        if (!$price_record) {
            $price_record = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_default = 1 AND is_active = 1',
                ['courseid' => $courseid],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if (!$price_record) {
            throw new \moodle_exception('nopricefound', 'local_payments', '', null,
                "No pricing rule found for course {$courseid}, country {$country}");
        }

        // Sale/date/priority were removed — the stored price is the final price.
        $price = (float) $price_record->price;

        return (object) [
            'price_id' => (int) $price_record->id,
            'price' => $price,
            'sale_price' => null,
            'original_price' => $price,
            'currency' => $price_record->currency,
            'country' => $country,
            'discount_pct' => 0,
            'is_sale_active' => false,
            'sale_ends_at' => 0,
        ];
    }

    /**
     * Check if a user has already purchased a course.
     */
    public static function is_purchased(int $courseid, int $userid): bool {
        global $DB;
        return $DB->record_exists('local_payments_transactions', [
            'courseid' => $courseid,
            'userid' => $userid,
            'status' => status_machine::COMPLETED,
        ]);
    }

    /**
     * Whether an active subscription of the user's covers this course (grants access but
     * hasn't created real Moodle enrolment yet — see buy.php's action=enroll handler).
     */
    public static function is_covered_by_active_subscription(int $courseid, int $userid): bool {
        if ($userid <= 0
            || !class_exists('\local_nit_subscriptions\subscription_purchase_manager')
            || !class_exists('\local_nit_subscriptions\subscription_manager')) {
            return false;
        }
        $activesub = \local_nit_subscriptions\subscription_purchase_manager::get_active_subscription($userid);
        if (!$activesub) {
            return false;
        }
        $covered_courses = \local_nit_subscriptions\subscription_manager::courses_for_subscription($activesub->subscriptionid);
        return in_array($courseid, $covered_courses);
    }

    /**
     * Build the context array for rendering the local_payments/course_card_price
     * template for a given course, from any course-listing page (catalog grids,
     * category pages, etc).
     */
    public static function card_context(int $courseid, ?int $userid = null): array {
        global $USER;

        $userid = $userid ?? (int) ($USER->id ?? 0);
        $is_enrolled = $userid > 0 ? enrollment_handler::is_enrolled($userid, $courseid) : false;
        $course_url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        // Subscription coverage grants access but doesn't create real Moodle enrolment until
        // the student explicitly clicks "Enroll" (see buy.php's action=enroll handler) — so it
        // must not be treated as already enrolled here.
        $can_enroll_via_sub = !$is_enrolled && self::is_covered_by_active_subscription($courseid, $userid);

        if ($can_enroll_via_sub) {
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => false,
                'can_enroll_via_sub' => true,
                'course_url' => $course_url,
                'enroll_url' => (new \moodle_url('/local/payments/buy.php',
                    ['courseid' => $courseid, 'action' => 'enroll', 'sesskey' => sesskey()]))->out(false),
            ];
        }

        // The course keeps showing in "My courses" after a subscription/package lapses (the
        // enrolment record survives but is expired). Flag that so the card can invite the
        // student to renew instead of silently looking like a fresh, never-enrolled course.
        $can_renew = !$is_enrolled && $userid > 0
            && enrollment_handler::has_expired_enrolment($userid, $courseid);

        if ($is_enrolled || !self::has_pricing($courseid)) {
            return [
                'is_enrolled' => $is_enrolled,
                'is_free' => !self::has_pricing($courseid),
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
            ];
        }

        $buy_url = (new \moodle_url('/local/payments/buy.php', ['courseid' => $courseid]))->out(false);
        $is_purchased = $userid > 0 ? self::is_purchased($courseid, $userid) : false;

        if ($is_purchased) {
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => true,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
                'buy_url' => $buy_url,
            ];
        }

        try {
            $pricing = self::resolve($courseid, $userid > 0 ? $userid : null);
        } catch (\moodle_exception $e) {
            return [
                'is_enrolled' => false,
                'is_free' => true,
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
            ];
        }

        return [
            'is_enrolled' => false,
            'is_free' => false,
            'is_purchased' => false,
            'can_renew' => $can_renew,
            'price' => number_format((float) $pricing->price, 2),
            'sale_price' => $pricing->sale_price !== null ? number_format((float) $pricing->sale_price, 2) : '',
            'original_price' => number_format((float) $pricing->original_price, 2),
            'currency' => $pricing->currency,
            'is_sale_active' => (bool) $pricing->is_sale_active,
            'discount_pct' => (int) $pricing->discount_pct,
            'buy_url' => $buy_url,
            'course_url' => $course_url,
        ];
    }
}
