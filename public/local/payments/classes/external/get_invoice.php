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

class get_invoice extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'transaction_id' => new external_value(PARAM_INT, 'Transaction ID'),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $transaction_id, string $lang = '', string $alang = ''): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            ['transaction_id' => $transaction_id, 'lang' => $lang, 'alang' => $alang]);

        $context = \context_system::instance();
        self::validate_context($context);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $txn = $DB->get_record('local_payments_transactions', ['id' => $params['transaction_id']], '*', MUST_EXIST);

        // Users can only see their own invoices unless they have viewalltransactions.
        if ($txn->userid != $USER->id) {
            require_capability('local/payments:viewalltransactions', $context);
        }

        $invoice = $DB->get_record('local_payments_invoices', ['transaction_id' => $params['transaction_id']]);
        $course = $DB->get_record('course', ['id' => $txn->courseid], 'fullname');

        return [
            'invoice_number' => $invoice ? $invoice->invoice_number : '',
            'amount' => (float) $txn->amount,
            'original_amount' => (float) ($txn->original_amount ?? $txn->amount),
            'currency' => $txn->currency,
            'status' => $invoice ? $invoice->status : '',
            'order_id' => $txn->order_id,
            'course_name' => $course->fullname ?? '',
            'payment_date' => (int) $txn->timecreated,
            'invoice_date' => $invoice ? (int) $invoice->timecreated : 0,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'invoice_number' => new external_value(PARAM_TEXT, 'Invoice number'),
            'amount' => new external_value(PARAM_FLOAT, 'Amount'),
            'original_amount' => new external_value(PARAM_FLOAT, 'Original amount'),
            'currency' => new external_value(PARAM_TEXT, 'Currency'),
            'status' => new external_value(PARAM_TEXT, 'Invoice status'),
            'order_id' => new external_value(PARAM_TEXT, 'Order ID'),
            'course_name' => new external_value(PARAM_TEXT, 'Course name'),
            'payment_date' => new external_value(PARAM_INT, 'Payment timestamp'),
            'invoice_date' => new external_value(PARAM_INT, 'Invoice timestamp'),
        ]);
    }
}
