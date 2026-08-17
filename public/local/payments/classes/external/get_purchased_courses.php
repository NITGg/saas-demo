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

class get_purchased_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $lang = '', string $alang = ''): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);

        $context = \context_system::instance();
        self::validate_context($context);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $sql = "SELECT t.id, t.courseid, t.amount, t.currency, t.order_id, t.timecreated,
                       c.fullname, c.summary, c.startdate
                FROM {local_payments_transactions} t
                JOIN {course} c ON c.id = t.courseid
                WHERE t.userid = :userid AND t.status = :status
                ORDER BY t.timecreated DESC";

        $records = $DB->get_records_sql($sql, [
            'userid' => $USER->id,
            'status' => \local_payments\status_machine::COMPLETED,
        ]);

        $result = [];
        foreach ($records as $r) {
            $result[] = [
                'courseid' => (int) $r->courseid,
                'course_name' => $r->fullname,
                'amount' => (float) $r->amount,
                'currency' => $r->currency,
                'order_id' => $r->order_id,
                'purchased_at' => (int) $r->timecreated,
                'enrolled' => \local_payments\enrollment_handler::is_enrolled($USER->id, (int) $r->courseid),
            ];
        }

        return $result;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'course_name' => new external_value(PARAM_TEXT, 'Course name'),
                'amount' => new external_value(PARAM_FLOAT, 'Amount paid'),
                'currency' => new external_value(PARAM_TEXT, 'Currency'),
                'order_id' => new external_value(PARAM_TEXT, 'Order ID'),
                'purchased_at' => new external_value(PARAM_INT, 'Purchase timestamp'),
                'enrolled' => new external_value(PARAM_BOOL, 'Currently enrolled'),
            ])
        );
    }
}
