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

class get_course_price extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'country' => new external_value(PARAM_ALPHA, 'Country code from app', VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, string $country = '', string $lang = '', string $alang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'country' => $country,
            'lang' => $lang,
            'alang' => $alang,
        ]);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        // Course must exist (context_course::instance throws otherwise), but validate
        // against the SYSTEM context: this endpoint is called by users who are NOT yet
        // enrolled (they're deciding whether to buy). Validating the course context runs
        // require_login($course) and would reject them with require_login_exception.
        \context_course::instance($params['courseid']);
        self::validate_context(\context_system::instance());

        $is_purchased = \local_payments\price_resolver::is_purchased($params['courseid'], $USER->id);
        $is_enrolled = \local_payments\enrollment_handler::is_enrolled($USER->id, $params['courseid']);

        // No active pricing rule — course is free, nothing to buy.
        if (!\local_payments\price_resolver::has_pricing($params['courseid'])) {
            return [
                'courseid' => $params['courseid'],
                'country' => '',
                'currency' => '',
                'price' => 0.0,
                'sale_price' => 0.0,
                'original_price' => 0.0,
                'discount_percentage' => 0,
                'is_sale_active' => false,
                'sale_ends_at' => 0,
                'is_purchased' => $is_purchased,
                'is_enrolled' => $is_enrolled,
                'is_free' => true,
            ];
        }

        $pricing = \local_payments\price_resolver::resolve(
            $params['courseid'],
            $USER->id,
            !empty($params['country']) ? $params['country'] : null
        );

        return [
            'courseid' => $params['courseid'],
            'country' => $pricing->country,
            'currency' => $pricing->currency,
            'price' => $pricing->price,
            'sale_price' => $pricing->sale_price ?? 0,
            'original_price' => $pricing->original_price,
            'discount_percentage' => $pricing->discount_pct,
            'is_sale_active' => $pricing->is_sale_active,
            'sale_ends_at' => $pricing->sale_ends_at ?? 0,
            'is_purchased' => $is_purchased,
            'is_enrolled' => $is_enrolled,
            'is_free' => false,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'country' => new external_value(PARAM_ALPHA, 'Detected country'),
            'currency' => new external_value(PARAM_ALPHA, 'Currency code'),
            'price' => new external_value(PARAM_FLOAT, 'Effective price'),
            'sale_price' => new external_value(PARAM_FLOAT, 'Sale price or 0'),
            'original_price' => new external_value(PARAM_FLOAT, 'Original price'),
            'discount_percentage' => new external_value(PARAM_INT, 'Discount percentage'),
            'is_sale_active' => new external_value(PARAM_BOOL, 'Whether sale is active'),
            'sale_ends_at' => new external_value(PARAM_INT, 'Sale end timestamp or 0'),
            'is_purchased' => new external_value(PARAM_BOOL, 'Already purchased'),
            'is_enrolled' => new external_value(PARAM_BOOL, 'Already enrolled'),
            'is_free' => new external_value(PARAM_BOOL, 'Course has no active pricing — free/open access'),
        ]);
    }
}
