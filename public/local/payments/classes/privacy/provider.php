<?php
namespace local_payments\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_payments_transactions', [
            'userid' => 'privacy:metadata:transactions:userid',
            'courseid' => 'privacy:metadata:transactions:courseid',
            'amount' => 'privacy:metadata:transactions:amount',
            'currency' => 'privacy:metadata:transactions:currency',
            'status' => 'privacy:metadata:transactions:status',
            'ip_address' => 'privacy:metadata:transactions:ip_address',
            'customer_email' => 'privacy:metadata:transactions:customer_email',
        ], 'privacy:metadata:transactions');

        $collection->add_database_table('local_payments_invoices', [
            'userid' => 'privacy:metadata:invoices:userid',
            'invoice_number' => 'privacy:metadata:invoices:invoice_number',
            'amount' => 'privacy:metadata:invoices:amount',
        ], 'privacy:metadata:invoices');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                FROM {local_payments_transactions} t
                JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :contextlevel
                WHERE t.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'contextlevel' => CONTEXT_COURSE]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT userid FROM {local_payments_transactions} WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $transactions = $DB->get_records('local_payments_transactions', [
                'userid' => $userid, 'courseid' => $context->instanceid,
            ]);

            $data = [];
            foreach ($transactions as $txn) {
                $data[] = (object) [
                    'order_id' => $txn->order_id,
                    'amount' => $txn->amount,
                    'currency' => $txn->currency,
                    'status' => $txn->status,
                    'timecreated' => \core_privacy\local\request\transform::datetime($txn->timecreated),
                ];
            }

            if (!empty($data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_payments')],
                    (object) ['transactions' => $data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $DB->delete_records('local_payments_transactions', ['courseid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $DB->delete_records('local_payments_transactions', [
                'userid' => $userid, 'courseid' => $context->instanceid,
            ]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_payments_transactions',
            "userid {$insql} AND courseid = :courseid", $inparams);
    }
}
