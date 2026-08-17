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

class get_course_access extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'country' => new external_value(PARAM_ALPHA, 'Country code from app (optional, accepted for consistency)', VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, string $country = '', string $lang = '', string $alang = ''): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid, 'country' => $country, 'lang' => $lang, 'alang' => $alang]);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        // Validate against the SYSTEM context: this endpoint is polled for unenrolled
        // users deciding whether to buy, so validating the course context (which enforces
        // course access via require_login) would reject them. context_course::instance
        // still confirms the course exists.
        \context_course::instance($params['courseid']);
        self::validate_context(\context_system::instance());

        $is_enrolled = \local_payments\enrollment_handler::is_enrolled($USER->id, $params['courseid']);
        $is_purchased = \local_payments\price_resolver::is_purchased($params['courseid'], $USER->id);

        $payment_status = '';
        $order_id = '';
        if ($is_purchased) {
            $txn = $DB->get_record_select(
                'local_payments_transactions',
                'userid = :userid AND courseid = :courseid AND status = :status',
                ['userid' => $USER->id, 'courseid' => $params['courseid'], 'status' => 'completed'],
                'id, order_id, status',
                IGNORE_MULTIPLE
            );
            if ($txn) {
                $payment_status = $txn->status;
                $order_id = $txn->order_id;
            }
        }

        // Check for pending payment.
        $has_pending = $DB->record_exists_select(
            'local_payments_transactions',
            'userid = :userid AND courseid = :courseid AND status = :status AND expires_at > :now',
            ['userid' => $USER->id, 'courseid' => $params['courseid'], 'status' => 'pending', 'now' => time()]
        );

        return [
            'courseid' => $params['courseid'],
            'is_enrolled' => $is_enrolled,
            'is_purchased' => $is_purchased,
            'payment_status' => $payment_status,
            'order_id' => $order_id,
            'has_pending_payment' => $has_pending,
            'is_free' => !\local_payments\price_resolver::has_pricing($params['courseid']),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'is_enrolled' => new external_value(PARAM_BOOL, 'Enrolled'),
            'is_purchased' => new external_value(PARAM_BOOL, 'Purchased'),
            'payment_status' => new external_value(PARAM_TEXT, 'Payment status'),
            'order_id' => new external_value(PARAM_TEXT, 'Order ID'),
            'has_pending_payment' => new external_value(PARAM_BOOL, 'Has pending payment'),
            'is_free' => new external_value(PARAM_BOOL, 'Course has no active pricing — free/open access'),
        ]);
    }
}
