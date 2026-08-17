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

/**
 * Wraps core_course_external::get_courses_by_field and appends country-resolved
 * pricing fields to every course in the result. The course data is identical to
 * what the core function returns (same fields, visibility rules, formatting).
 */
class get_courses_with_pricing extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'field' => new external_value(
                PARAM_ALPHA,
                'Field to filter by: id | ids | shortname | idnumber | category. Empty = all courses.',
                VALUE_DEFAULT,
                ''
            ),
            'value' => new external_value(
                PARAM_RAW,
                'Value for the filter field. For ids, comma-separated integers.',
                VALUE_DEFAULT,
                ''
            ),
            'country' => new external_value(
                PARAM_ALPHA,
                'ISO-3166-1 alpha-2 country code from the app (overrides auto-detection).',
                VALUE_DEFAULT,
                ''
            ),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $field = '', string $value = '', string $country = '',
            string $lang = '', string $alang = ''): array {
        global $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'field'   => $field,
            'value'   => $value,
            'country' => $country,
            'lang'    => $lang,
            'alang'   => $alang,
        ]);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        self::validate_context(\context_system::instance());

        // Delegate to the core function — gets all standard fields, visibility
        // filtering, capability checks, and formatted output for free. In Moodle 5
        // this is still core_course_external::get_courses_by_field (course/externallib.php),
        // not a namespaced per-function class.
        require_once($CFG->dirroot . '/course/externallib.php');
        $core_result = \core_course_external::get_courses_by_field(
            $params['field'],
            $params['value']
        );

        $app_country = !empty($params['country']) ? $params['country'] : null;
        $courses     = [];

        foreach ($core_result['courses'] as $course_data) {
            $courseid = (int) $course_data['id'];

            if ($courseid === SITEID) {
                continue;
            }

            $is_purchased = \local_payments\price_resolver::is_purchased($courseid, $USER->id);
            $is_enrolled  = \local_payments\enrollment_handler::is_enrolled($USER->id, $courseid);

            $pricing = [
                'pricing_country'     => '',
                'currency'            => '',
                'price'               => 0.0,
                'sale_price'          => 0.0,
                'original_price'      => 0.0,
                'discount_percentage' => 0,
                'is_sale_active'      => false,
                'sale_ends_at'        => 0,
                'is_free'             => true,
                'is_purchased'        => $is_purchased,
                'is_enrolled'         => $is_enrolled,
            ];

            if (\local_payments\price_resolver::has_pricing($courseid)) {
                try {
                    $resolved = \local_payments\price_resolver::resolve($courseid, $USER->id, $app_country);
                    $pricing['pricing_country']     = $resolved->country;
                    $pricing['currency']            = $resolved->currency;
                    $pricing['price']               = $resolved->price;
                    $pricing['sale_price']          = $resolved->sale_price ?? 0.0;
                    $pricing['original_price']      = $resolved->original_price;
                    $pricing['discount_percentage'] = $resolved->discount_pct;
                    $pricing['is_sale_active']      = $resolved->is_sale_active;
                    $pricing['sale_ends_at']        = (int) ($resolved->sale_ends_at ?? 0);
                    $pricing['is_free']             = false;
                } catch (\moodle_exception $e) {
                    // No pricing rule for this country — treat as free.
                }
            }

            $courses[] = array_merge($course_data, $pricing);
        }

        return [
            'courses'  => $courses,
            'warnings' => $core_result['warnings'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        global $CFG;
        // Reuse core's own return structure so it never drifts from
        // core_course_get_courses_by_field, then add the pricing fields per course.
        require_once($CFG->dirroot . '/course/externallib.php');
        $returns = \core_course_external::get_courses_by_field_returns();

        $pricingkeys = [
            'pricing_country'     => new external_value(PARAM_ALPHA, 'resolved country code for pricing', VALUE_OPTIONAL),
            'currency'            => new external_value(PARAM_ALPHA, 'currency code (e.g. EGP)', VALUE_OPTIONAL),
            'price'               => new external_value(PARAM_FLOAT, 'effective price - sale price if active, otherwise original', VALUE_OPTIONAL),
            'sale_price'          => new external_value(PARAM_FLOAT, 'sale price, or 0 if no active sale', VALUE_OPTIONAL),
            'original_price'      => new external_value(PARAM_FLOAT, 'original price before any discount', VALUE_OPTIONAL),
            'discount_percentage' => new external_value(PARAM_INT,   'discount percentage 0-100', VALUE_OPTIONAL),
            'is_sale_active'      => new external_value(PARAM_BOOL,  'whether a sale is currently active', VALUE_OPTIONAL),
            'sale_ends_at'        => new external_value(PARAM_INT,   'sale end unix timestamp, or 0', VALUE_OPTIONAL),
            'is_free'             => new external_value(PARAM_BOOL,  'true if no active pricing rule', VALUE_OPTIONAL),
            'is_purchased'        => new external_value(PARAM_BOOL,  'current user has a completed purchase', VALUE_OPTIONAL),
            'is_enrolled'         => new external_value(PARAM_BOOL,  'current user is enrolled in the course', VALUE_OPTIONAL),
        ];
        // $returns->keys['courses'] is an external_multiple_structure; its ->content is the
        // per-course external_single_structure we augment with the pricing keys.
        foreach ($pricingkeys as $k => $v) {
            $returns->keys['courses']->content->keys[$k] = $v;
        }
        return $returns;
    }
}
