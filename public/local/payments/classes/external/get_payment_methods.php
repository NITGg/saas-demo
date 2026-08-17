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

class get_payment_methods extends external_api {

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
            'lang' => $lang,
            'alang' => $alang,
            'country' => $country,
        ]);

        // Validate against the SYSTEM context: called for unenrolled users before purchase,
        // so the course context (require_login) would wrongly reject them. The course
        // existence is still checked via context_course::instance.
        \context_course::instance($params['courseid']);
        self::validate_context(\context_system::instance());
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $pricing = \local_payments\price_resolver::resolve(
            $params['courseid'], $USER->id,
            !empty($params['country']) ? $params['country'] : null
        );

        return \local_payments\manager::get_available_providers($pricing->country, $pricing->currency);
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Provider name'),
                'display_name' => new external_value(PARAM_TEXT, 'Display name'),
                'priority' => new external_value(PARAM_INT, 'Priority'),
            ])
        );
    }
}
