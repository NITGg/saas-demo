<?php
namespace local_payments\task;

defined('MOODLE_INTERNAL') || die();

class expire_pending_payments extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_expire_pending', 'local_payments');
    }

    public function execute() {
        global $DB;

        if (!get_config('local_payments', 'auto_expire_enabled')) {
            return;
        }

        $expired = $DB->get_records_select(
            'local_payments_transactions',
            "status = :status AND expires_at < :now AND expires_at > 0",
            ['status' => \local_payments\status_machine::PENDING, 'now' => time()]
        );

        $count = 0;
        foreach ($expired as $txn) {
            if (\local_payments\status_machine::can_transition($txn->status, \local_payments\status_machine::EXPIRED)) {
                $DB->update_record('local_payments_transactions', (object) [
                    'id' => $txn->id,
                    'status' => \local_payments\status_machine::EXPIRED,
                    'timemodified' => time(),
                ]);

                $DB->insert_record('local_payments_audit_logs', (object) [
                    'transaction_id' => $txn->id,
                    'userid' => $txn->userid,
                    'action' => 'status_changed',
                    'old_value' => $txn->status,
                    'new_value' => \local_payments\status_machine::EXPIRED,
                    'timecreated' => time(),
                ]);

                $count++;
            }
        }

        if ($count > 0) {
            mtrace("Expired {$count} pending payment(s).");
        }
    }
}
