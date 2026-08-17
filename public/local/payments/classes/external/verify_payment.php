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

class verify_payment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'order_id' => new external_value(PARAM_TEXT, 'Order ID to verify'),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $order_id, string $lang = '', string $alang = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['order_id' => $order_id, 'lang' => $lang, 'alang' => $alang]);

        $context = \context_system::instance();
        self::validate_context($context);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        // Ownership gate: a user may only verify their own order (staff with
        // viewalltransactions may verify any). Stops order-id enumeration and
        // forcing state transitions on other users' orders.
        global $USER, $DB;
        $tx = $DB->get_record('local_payments_transactions', ['order_id' => $params['order_id']]);
        if ($tx && (int) $tx->userid !== (int) $USER->id
                && !has_capability('local/payments:viewalltransactions', $context)) {
            throw new \moodle_exception('invalidaccess', 'error');
        }

        $result = \local_payments\manager::verify_callback($params['order_id']);

        return [
            'success' => $result->success,
            'status' => $result->status,
            'courseid' => $result->courseid,
            'enrolled' => $result->enrolled,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Payment verified'),
            'status' => new external_value(PARAM_TEXT, 'Transaction status'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'enrolled' => new external_value(PARAM_BOOL, 'Student enrolled'),
        ]);
    }
}
