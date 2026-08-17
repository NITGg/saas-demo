<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_warnings;
use core_external\external_format_value;
use core_external\external_files;

global $CFG;

class create_checkout extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'country' => new external_value(PARAM_ALPHA, 'Country code from app', VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_ALPHA, 'Display language (en/ar)', VALUE_DEFAULT, 'en'),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
            'coupon_code' => new external_value(PARAM_TEXT, 'Coupon code to apply at checkout (optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, string $country = '', string $lang = 'en',
            string $alang = '', string $coupon_code = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'country' => $country,
            'lang' => $lang,
            'alang' => $alang,
            'coupon_code' => $coupon_code,
        ]);
        // The app may send the language as `alang` (old convention); prefer it when present.
        if ($params['alang'] !== '') {
            $params['lang'] = $params['alang'];
        }

        // Validate against the SYSTEM context, not the course context: the buyer is by
        // definition NOT enrolled yet, so validating the course context (require_login)
        // would reject them with require_login_exception. The purchasecourse capability is
        // still checked against the course context (capability checks don't require enrolment).
        $context = \context_course::instance($params['courseid']);
        self::validate_context(\context_system::instance());
        require_capability('local/payments:purchasecourse', $context);

        $result = \local_payments\manager::create_checkout(
            $params['courseid'],
            $USER->id,
            !empty($params['country']) ? $params['country'] : null,
            $params['lang'],
            $params['coupon_code']
        );

        return [
            'order_id' => $result->order_id,
            'checkout_url' => $result->checkout_url,
            'expires_at' => $result->expires_at,
            'provider' => $result->provider,
            'transaction_id' => $result->transaction_id,
            'amount' => (float) ($result->amount ?? 0),
            'original_amount' => (float) ($result->original_amount ?? 0),
            'currency' => $result->currency ?? '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'order_id' => new external_value(PARAM_TEXT, 'Internal order ID'),
            'checkout_url' => new external_value(PARAM_URL, 'Provider checkout URL'),
            'expires_at' => new external_value(PARAM_INT, 'Expiry timestamp'),
            'provider' => new external_value(PARAM_TEXT, 'Provider name'),
            'transaction_id' => new external_value(PARAM_INT, 'Transaction record ID'),
            'amount' => new external_value(PARAM_FLOAT, 'Charged amount after coupon/offer — show THIS price'),
            'original_amount' => new external_value(PARAM_FLOAT, 'Price before discount'),
            'currency' => new external_value(PARAM_TEXT, 'Currency (e.g. EGP)'),
        ]);
    }
}
